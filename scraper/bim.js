const https = require('https');
const fs = require('fs');
const path = require('path');
const db = require('./db');

// Helper to make HTTPS requests
function fetchPage(url) {
    return new Promise((resolve, reject) => {
        https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            }
        }, (res) => {
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => { resolve(data); });
        }).on('error', (err) => { reject(err); });
    });
}

// Helper to download files asynchronously
function downloadFile(url, destPath) {
    return new Promise((resolve, reject) => {
        const dir = path.dirname(destPath);
        if (!fs.existsSync(dir)){
            fs.mkdirSync(dir, { recursive: true });
        }
        
        const file = fs.createWriteStream(destPath);
        https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            }
        }, (response) => {
            if (response.statusCode !== 200) {
                reject(new Error(`Görsel indirilemedi, HTTP durum kodu: ${response.statusCode}`));
                return;
            }
            response.pipe(file);
            file.on('finish', () => {
                file.close(resolve);
            });
        }).on('error', (err) => {
            fs.unlink(destPath, () => {}); // delete partial file on failure
            reject(err);
        });
    });
}

// Parse Turkish brochure titles (e.g. "09 Haziran Salı") to YYYY-MM-DD
function parseTurkishDate(titleText) {
    const months = {
        'ocak': 1, 'şubat': 2, 'mart': 3, 'nisan': 4,
        'mayıs': 5, 'haziran': 6, 'temmuz': 7, 'ağustos': 8,
        'eylül': 9, 'ekim': 10, 'kasım': 11, 'aralık': 12
    };
    
    const cleaned = titleText.toLowerCase().trim();
    const parts = cleaned.split(/\s+/);
    
    let day = 1;
    let month = 6;
    const now = new Date();
    let year = now.getFullYear();

    if (parts.length >= 2) {
        const dayPart = parts[0];
        if (dayPart.includes('-')) {
            day = parseInt(dayPart.split('-')[0], 10);
        } else {
            day = parseInt(dayPart, 10);
        }
        
        const monthName = parts[1];
        if (months[monthName]) {
            month = months[monthName];
        }
    }
    
    // Year rollover detection (e.g. crawling in December for a January brochure)
    const currentMonth = now.getMonth() + 1; // 1-indexed
    if (currentMonth === 12 && month === 1) {
        year += 1;
    } else if (currentMonth === 1 && month === 12) {
        year -= 1;
    }
    
    const dayStr = String(day).padStart(2, '0');
    const monthStr = String(month).padStart(2, '0');
    return `${year}-${monthStr}-${dayStr}`;
}

async function scrapeBim() {
    console.log('[BİM Scraper] Tarama işlemi başlıyor...');
    
    // Find BİM market ID
    const marketRows = await db.query("SELECT id FROM markets WHERE slug = 'bim'");
    if (marketRows.length === 0) {
        console.error('❌ Hata: Veritabanında slug değeri "bim" olan bir market bulunamadı. Lütfen önce admin panelden BİM marketini ekleyin.');
        return;
    }
    const marketId = marketRows[0].id;
    
    const mainUrl = 'https://www.bim.com.tr/Categories/680/afisler.aspx';
    const mainHtml = await fetchPage(mainUrl);
    
    // Clean HTML to bypass escaping differences
    const cleanHtml = mainHtml.replace(/&amp;/g, '&');
    
    // Find brochure links
    const searchRegex = /\/Categories\/680\/afisler\.aspx\?top=1&Bim_AfisKey=(\d+)">([^<]+)<\/a>/g;
    const brochuresFound = [];
    let match;
    
    while ((match = searchRegex.exec(cleanHtml)) !== null) {
        brochuresFound.push({
            key: match[1],
            title: match[2].trim()
        });
    }
    
    console.log(`[BİM Scraper] Sitede ${brochuresFound.length} adet katalog linki bulundu.`);
    
    let newBrochuresCount = 0;
    
    // Process top 5 brochures (usually covers the upcoming and current brochures)
    for (const b of brochuresFound.slice(0, 5)) {
        const startDate = parseTurkishDate(b.title);
        
        // Calculate end date (7 days validity)
        const startTimestamp = new Date(startDate).getTime();
        const endTimestamp = startTimestamp + 7 * 24 * 60 * 60 * 1000;
        const endDate = new Date(endTimestamp).toISOString().split('T')[0];
        
        // Check if brochure already exists
        const existRows = await db.query(
            "SELECT id FROM brochures WHERE market_id = ? AND start_date = ?",
            [marketId, startDate]
        );
        
        if (existRows.length > 0) {
            console.log(`[BİM Scraper] Atlanıyor (Zaten kayıtlı): "${b.title}" (${startDate})`);
            continue;
        }
        
        console.log(`🌟 [BİM Scraper] Yeni katalog bulundu! İndiriliyor: "${b.title}" (${startDate})`);
        
        // Fetch brochure details page
        const detailUrl = `https://www.bim.com.tr/Categories/680/afisler.aspx?top=1&Bim_AfisKey=${b.key}`;
        const detailHtml = await fetchPage(detailUrl);
        
        // Extract page images
        const imgRegex = /data-bigimg="([^"]+)"/g;
        const imageUrls = [];
        let imgMatch;
        
        while ((imgMatch = imgRegex.exec(detailHtml)) !== null) {
            const url = imgMatch[1];
            if (!imageUrls.includes(url)) {
                imageUrls.push(url);
            }
        }
        
        if (imageUrls.length === 0) {
            console.warn(`⚠️ [BİM Scraper] Detay sayfasında görsel bulunamadı (Key: ${b.key}).`);
            continue;
        }
        
        console.log(`[BİM Scraper] Katalogda ${imageUrls.length} sayfa tespit edildi. İndirme başlatılıyor...`);
        
        const uploadsDir = path.join(__dirname, '../uploads');
        const brochureTitle = `${b.title} Aktüel Ürünler`;
        const timestamp = Math.floor(Date.now() / 1000);
        
        // 1. Download cover image (use first page as cover)
        const coverName = `bim_${b.key}_cover_${timestamp}.jpg`;
        const coverDest = path.join(uploadsDir, 'brochures', coverName);
        console.log(`  -> Kapak indiriliyor: ${coverName}`);
        await downloadFile(imageUrls[0], coverDest);
        
        // 2. Insert brochure record to DB
        const insertResult = await db.query(
            "INSERT INTO brochures (market_id, title, cover_image, start_date, end_date) VALUES (?, ?, ?, ?, ?)",
            [marketId, brochureTitle, coverName, startDate, endDate]
        );
        const brochureId = insertResult.insertId;
        
        // 3. Download page images and insert page records
        for (let i = 0; i < imageUrls.length; i++) {
            const pageNum = i + 1;
            const pageName = `bim_${b.key}_page_${pageNum}_${timestamp}.jpg`;
            const pageDest = path.join(uploadsDir, 'brochures/pages', pageName);
            
            console.log(`  -> Sayfa ${pageNum} indiriliyor: ${pageName}`);
            await downloadFile(imageUrls[i], pageDest);
            
            await db.query(
                "INSERT INTO brochure_pages (brochure_id, page_number, image_path) VALUES (?, ?, ?)",
                [brochureId, pageNum, pageName]
            );
        }
        
        console.log(`✓ [BİM Scraper] Başarıyla veritabanına kaydedildi. ID: ${brochureId}`);
        newBrochuresCount++;
    }
    
    console.log(`[BİM Scraper] Tarama tamamlandı. ${newBrochuresCount} adet yeni broşür sisteme eklendi.`);
}

module.exports = {
    scrapeBim
};
