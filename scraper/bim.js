const https = require('https');
const fs = require('fs');
const path = require('path');
const db = require('./db');
const cheerio = require('cheerio');

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
// Parse Turkish brochure titles/ranges to YYYY-MM-DD
const months = {
    'ocak': 1, 'şubat': 2, 'mart': 3, 'nisan': 4, 'mayıs': 5, 'haziran': 6,
    'temmuz': 7, 'ağustos': 8, 'eylül': 9, 'ekim': 10, 'kasım': 11, 'aralık': 12
};

function parseTurkishDateRange(titleText) {
    const now = new Date();
    let year = now.getFullYear();
    const currentMonth = now.getMonth() + 1; // 1-indexed

    const text = titleText.toLowerCase().trim().replace(/\s+/g, ' ');
    
    // Helper to format date
    const formatDate = (y, m, d) => {
        const mStr = String(m).padStart(2, '0');
        const dStr = String(d).padStart(2, '0');
        return `${y}-${mStr}-${dStr}`;
    };

    // Helper to add days
    const addDays = (dateStr, days) => {
        const d = new Date(dateStr);
        d.setDate(d.getDate() + days);
        return d.toISOString().split('T')[0];
    };

    // Regex 1: Range across months (e.g. "24 Mart-31 Aralık", "30 Mayıs - 5 Haziran")
    const rangeMonthsRegex = /(\d+)\s*([a-zşğçıöü]+)\s*-\s*(\d+)\s*([a-zşğçıöü]+)/;
    let match = text.match(rangeMonthsRegex);
    if (match) {
        const startDay = parseInt(match[1], 10);
        const startMonthName = match[2];
        const endDay = parseInt(match[3], 10);
        const endMonthName = match[4];
        
        const startMonth = months[startMonthName];
        const endMonth = months[endMonthName];
        
        if (startMonth && endMonth) {
            let startYear = year;
            let endYear = year;
            if (startMonth === 12 && endMonth === 1) {
                endYear = startYear + 1;
            }
            return {
                startDate: formatDate(startYear, startMonth, startDay),
                endDate: formatDate(endYear, endMonth, endDay)
            };
        }
    }

    // Regex 2: Range within same month (e.g. "03-29 Haziran", "05-08 Haziran")
    const rangeSameMonthRegex = /(\d+)\s*-\s*(\d+)\s+([a-zşğçıöü]+)/;
    match = text.match(rangeSameMonthRegex);
    if (match) {
        const startDay = parseInt(match[1], 10);
        const endDay = parseInt(match[2], 10);
        const monthName = match[3];
        const month = months[monthName];
        
        if (month) {
            return {
                startDate: formatDate(year, month, startDay),
                endDate: formatDate(year, month, endDay)
            };
        }
    }

    // Regex 3: Single date with suffix (e.g. "4 Haziran'dan itibaren")
    const relativeDateRegex = /(\d+)\s*([a-zşğçıöü]+)(?:'?[a-zşğçıöü]+)?\s*dan\s+itibaren|(\d+)\s*([a-zşğçıöü]+)(?:'?[a-zşğçıöü]+)?\s*tan\s+itibaren/i;
    match = text.match(relativeDateRegex);
    if (match) {
        const day = parseInt(match[1] || match[3], 10);
        const monthName = match[2] || match[4];
        const month = months[monthName];
        if (month) {
            const startDate = formatDate(year, month, day);
            const endDate = addDays(startDate, 7);
            return { startDate, endDate };
        }
    }

    // Regex 4: Single Date (e.g. "25 Mayıs Pazartesi")
    const singleDateRegex = /(\d+)\s+([a-zşğçıöü]+)/;
    match = text.match(singleDateRegex);
    if (match) {
        const day = parseInt(match[1], 10);
        const monthName = match[2];
        const month = months[monthName];
        
        if (month) {
            let brochureYear = year;
            if (currentMonth === 12 && month === 1) {
                brochureYear += 1;
            } else if (currentMonth === 1 && month === 12) {
                brochureYear -= 1;
            }
            const startDate = formatDate(brochureYear, month, day);
            const endDate = addDays(startDate, 7);
            return { startDate, endDate };
        }
    }

    // Default Fallback
    const startDate = formatDate(year, currentMonth, now.getDate());
    const endDate = addDays(startDate, 7);
    return { startDate, endDate };
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
        const dates = parseTurkishDateRange(b.title);
        const startDate = dates.startDate;
        const endDate = dates.endDate;
        
        // Check if brochure already exists
        const existRows = await db.query(
            "SELECT id FROM brochures WHERE market_id = ? AND start_date = ?",
            [marketId, startDate]
        );
        
        if (existRows.length > 0) {
            console.log(`[BİM Scraper] Atlanıyor (Zaten kayıtlı): "${b.title}" (${startDate})`);
            continue;
        }
        
        console.log(`🌟 [BİM Scraper] Yeni katalog bulundu! İndiriliyor: "${b.title}" (${startDate} -> ${endDate})`);
        
        // Fetch brochure details page
        const detailUrl = `https://www.bim.com.tr/Categories/680/afisler.aspx?top=1&Bim_AfisKey=${b.key}`;
        const detailHtml = await fetchPage(detailUrl);
        
        // Segment page images using cheerio (only extract images from matching brochure block)
        const $ = cheerio.load(detailHtml);
        const imageUrls = [];
        
        $('.row.item').each((i, row) => {
            const rowTitle = $(row).prev().text().trim().toLowerCase();
            const brochureTitleLower = b.title.toLowerCase().trim();
            
            if (rowTitle && (rowTitle.includes(brochureTitleLower) || brochureTitleLower.includes(rowTitle))) {
                $(row).find('[data-bigimg]').each((j, a) => {
                    const imgUrl = $(a).attr('data-bigimg');
                    if (imgUrl && !imageUrls.includes(imgUrl)) {
                        imageUrls.push(imgUrl);
                    }
                });
            }
        });
        
        // Fallback: If no matching block was found, extract all data-bigimg elements from the page
        if (imageUrls.length === 0) {
            console.log(`[BİM Scraper] Segment eşleşmesi bulunamadı, tüm sayfa genelindeki görseller taranıyor...`);
            $('[data-bigimg]').each((j, a) => {
                const imgUrl = $(a).attr('data-bigimg');
                if (imgUrl && !imageUrls.includes(imgUrl)) {
                    imageUrls.push(imgUrl);
                }
            });
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
