const https = require('https');
const fs = require('fs');
const path = require('path');
const db = require('./db');
const cheerio = require('cheerio');

// Helper to make HTTPS requests and retrieve redirects/headers
function getRedirectUrl(url) {
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const options = {
            hostname: parsedUrl.hostname,
            path: parsedUrl.pathname + parsedUrl.search,
            method: 'GET',
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            }
        };
        https.get(options, (res) => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                let redirectUrl = res.headers.location;
                if (!redirectUrl.startsWith('http')) {
                    const origin = `${parsedUrl.protocol}//${parsedUrl.host}`;
                    redirectUrl = new URL(redirectUrl, origin).toString();
                }
                res.destroy(); // close connection
                resolve(redirectUrl);
            } else {
                res.destroy();
                reject(new Error(`Redirect status code expected (3xx), got: ${res.statusCode}`));
            }
        }).on('error', (err) => { reject(err); });
    });
}

// Helper to fetch HTML content of a page
function fetchPageHtml(url) {
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
                reject(new Error(`Dosya indirilemedi, HTTP durum kodu: ${response.statusCode}`));
                return;
            }
            response.pipe(file);
            file.on('finish', () => {
                file.close(resolve);
            });
        }).on('error', (err) => {
            fs.unlink(destPath, () => {});
            reject(err);
        });
    });
}

// Calculate start and end dates mathematically for ŞOK campaigns
function getSokDates(type, referenceDate = new Date()) {
    const today = new Date(referenceDate);
    const day = today.getDay(); // 0: Sunday, 1: Monday, ..., 6: Saturday
    
    const formatDate = (d) => d.toISOString().split('T')[0];
    
    let startDate, endDate;
    
    if (type === 'weekly') { // Wednesday deals (runs Wed -> next Tue)
        if (day === 0 || day === 1 || day === 2) {
            startDate = new Date(today);
            startDate.setDate(today.getDate() - (day + 4));
        } else {
            startDate = new Date(today);
            startDate.setDate(today.getDate() - (day - 3));
        }
        endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 6);
    } else { // weekend deals (runs Sat -> next Tue)
        if (day === 0 || day === 1 || day === 2) {
            startDate = new Date(today);
            startDate.setDate(today.getDate() - (day + 1));
        } else if (day >= 3 && day <= 5) {
            startDate = new Date(today);
            startDate.setDate(today.getDate() + (6 - day));
        } else { // Saturday (6)
            startDate = new Date(today);
        }
        endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 3);
    }
    
    return {
        startDate: formatDate(startDate),
        endDate: formatDate(endDate)
    };
}

async function scrapeSok() {
    console.log('[ŞOK Scraper] Tarama işlemi başlıyor...');
    
    // Find ŞOK market ID
    const marketRows = await db.query("SELECT id FROM markets WHERE slug = 'sok'");
    if (marketRows.length === 0) {
        console.error('❌ Hata: Veritabanında slug değeri "sok" olan bir market bulunamadı.');
        return;
    }
    const marketId = marketRows[0].id;
    
    const uploadsDir = path.join(__dirname, '../uploads');
    const timestamp = Math.floor(Date.now() / 1000);
    
    // Config for ŞOK campaigns
    const campaigns = [
        {
            type: 'weekly',
            url: 'https://kurumsal.sokmarket.com.tr/firsatlar/carsamba/',
            title: 'ŞOK Haftanın Fırsatları Kataloğu',
            defaultCover: 'https://kurumsal.sokmarket.com.tr/uploads/202303221113495303.jpg'
        },
        {
            type: 'weekend',
            url: 'https://kurumsal.sokmarket.com.tr/firsatlar/hafta-sonu/',
            title: 'ŞOK Hafta Sonu Fırsatları Kataloğu',
            defaultCover: 'https://kurumsal.sokmarket.com.tr/uploads/202303221114005332.jpg'
        }
    ];
    
    let newBrochuresCount = 0;
    
    for (const camp of campaigns) {
        console.log(`\n🔍 [ŞOK Scraper] Kampanya inceleniyor: ${camp.title}`);
        
        // 1. Resolve redirect to find actual PDF URL
        let pdfAbsoluteUrl = '';
        try {
            pdfAbsoluteUrl = await getRedirectUrl(camp.url);
        } catch (err) {
            console.error(`❌ PDF linki yönlendirmesi çözülemedi (${camp.url}):`, err.message);
            continue;
        }
        
        if (!pdfAbsoluteUrl.toLowerCase().endsWith('.pdf')) {
            console.warn(`⚠️ [ŞOK Scraper] Hedef URL bir PDF dosyası değil: ${pdfAbsoluteUrl}`);
            continue;
        }
        
        const pdfFilename = path.basename(pdfAbsoluteUrl);
        
        // Check if brochure already exists in DB
        const existRows = await db.query(
            "SELECT id FROM brochures WHERE market_id = ? AND pdf_path = ?",
            [marketId, pdfFilename]
        );
        
        if (existRows.length > 0) {
            console.log(`[ŞOK Scraper] Atlanıyor (Zaten kayıtlı): "${camp.title}" (${pdfFilename})`);
            continue;
        }
        
        console.log(`🌟 [ŞOK Scraper] Yeni ŞOK kataloğu bulundu! PDF: ${pdfAbsoluteUrl}`);
        
        // Calculate dates
        const dates = getSokDates(camp.type);
        const startDate = dates.startDate;
        const endDate = dates.endDate;
        
        // 2. Determine and download cover image
        let coverUrl = camp.defaultCover;
        if (camp.type === 'weekend') {
            // Weekend deals cover image is sometimes listed live on haftasonu-firsatlari index page
            try {
                const indexHtml = await fetchPageHtml('https://kurumsal.sokmarket.com.tr/firsatlar/haftasonu-firsatlari');
                const $ = cheerio.load(indexHtml);
                let foundCover = '';
                $('img').each((i, imgEl) => {
                    const src = $(imgEl).attr('src') || '';
                    const alt = $(imgEl).attr('alt') || '';
                    if (src.includes('/uploads/') && alt.toLowerCase().includes('haftasonu')) {
                        foundCover = src;
                    }
                });
                if (foundCover) {
                    if (foundCover.startsWith('http')) {
                        coverUrl = foundCover;
                    } else {
                        coverUrl = 'https://kurumsal.sokmarket.com.tr' + foundCover;
                    }
                    console.log(`  -> Canlı kapak görseli bulundu: ${coverUrl}`);
                }
            } catch (e) {
                console.log(`  -> Canlı kapak arama başarısız oldu, varsayılana geçiliyor.`);
            }
        }
        
        const coverFilename = `sok_${camp.type}_cover_${timestamp}.jpg`;
        const coverDest = path.join(uploadsDir, 'brochures', coverFilename);
        console.log(`  -> Kapak indiriliyor: ${coverFilename}`);
        try {
            await downloadFile(coverUrl, coverDest);
        } catch (err) {
            console.error(`❌ Kapak indirilemedi:`, err.message);
            continue;
        }
        
        // 3. Download the PDF flyer
        const pdfDest = path.join(uploadsDir, 'brochures/pdfs', pdfFilename);
        console.log(`  -> PDF indiriliyor: ${pdfFilename}`);
        try {
            await downloadFile(pdfAbsoluteUrl, pdfDest);
        } catch (err) {
            console.error(`❌ PDF indirilemedi:`, err.message);
            // delete cover on failure
            fs.unlink(coverDest, () => {});
            continue;
        }
        
        // 4. Save brochure to DB
        try {
            const brochureTitle = `${camp.title} (${startDate})`;
            const insertResult = await db.query(
                "INSERT INTO brochures (market_id, title, cover_image, pdf_path, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)",
                [marketId, brochureTitle, coverFilename, pdfFilename, startDate, endDate]
            );
            console.log(`✓ [ŞOK Scraper] Başarıyla kaydedildi. ID: ${insertResult.insertId}`);
            newBrochuresCount++;
        } catch (err) {
            console.error(`❌ Veritabanına kaydedilemedi:`, err.message);
            // clean up files
            fs.unlink(coverDest, () => {});
            fs.unlink(pdfDest, () => {});
        }
    }
    
    console.log(`[ŞOK Scraper] Tarama tamamlandı. ${newBrochuresCount} adet yeni broşür eklendi.`);
}

module.exports = {
    scrapeSok,
    getSokDates
};
