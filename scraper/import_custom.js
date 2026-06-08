const fs = require('fs');
const path = require('path');
const https = require('https');
const db = require('./db');

function fetchPage(url) {
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const options = {
            hostname: parsedUrl.hostname,
            path: parsedUrl.pathname + parsedUrl.search,
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Language': 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7'
            }
        };
        
        https.get(options, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ status: res.statusCode, html: data }));
        }).on('error', reject);
    });
}

function downloadFile(url, destPath) {
    return new Promise((resolve, reject) => {
        const dir = path.dirname(destPath);
        if (!fs.existsSync(dir)){
            fs.mkdirSync(dir, { recursive: true });
        }
        
        const file = fs.createWriteStream(destPath);
        const req = https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            },
            timeout: 20000
        }, (response) => {
            if (response.statusCode !== 200) {
                file.close(() => {
                    fs.unlink(destPath, () => {});
                    reject(new Error(`Görsel indirilemedi, HTTP durum kodu: ${response.statusCode}`));
                });
                return;
            }
            response.pipe(file);
            file.on('finish', () => {
                file.close(resolve);
            });
        });
        
        req.on('timeout', () => {
            req.destroy();
            file.close(() => {
                fs.unlink(destPath, () => {});
                reject(new Error('Görsel indirme isteği zaman aşımına uğradı.'));
            });
        });
        
        req.on('error', (err) => {
            file.close(() => {
                fs.unlink(destPath, () => {});
                reject(err);
            });
        });
    });
}

async function importMigros() {
    console.log('\n--- 1. MİGROS ÖZEL İNDİRME BAŞLATILIYOR ---');
    const url = 'https://indirimcipanda.com/brosur/migros-migroskop-brosuru-04-17-haziran-2026';
    
    // Fetch market ID
    const marketRows = await db.query("SELECT id FROM markets WHERE slug = 'migros'");
    if (marketRows.length === 0) {
        console.error('❌ Hata: Veritabanında Migros bulunamadı!');
        return;
    }
    const marketId = marketRows[0].id;
    
    // Fetch page HTML
    console.log(`Panda Migros sayfası yükleniyor: ${url}`);
    const { html } = await fetchPage(url);
    
    // Extract cloudfront images using regex
    const imgUrls = html.match(/https?:\/\/[^\s'"]+?\.(jpg|jpeg|png|webp|gif)/gi) || [];
    const uniqueUrls = [...new Set(imgUrls)].filter(u => u.includes('cloudfront') && u.includes('migros'));
    
    // Sort pages in correct order
    uniqueUrls.sort((a, b) => {
        const orderA = parseInt((a.match(/order(\d+)/) || [0, 0])[1], 10);
        const orderB = parseInt((b.match(/order(\d+)/) || [0, 0])[1], 10);
        return orderA - orderB;
    });
    
    if (uniqueUrls.length === 0) {
        console.error('❌ Hata: Sayfadan Migros görsel linkleri ayıklanamadı!');
        return;
    }
    
    console.log(`Ayıklanan sayfa sayısı: ${uniqueUrls.length}`);
    
    const title = 'Migros Migroskop 04 - 17 Haziran 2026';
    const startDate = '2026-06-04';
    const endDate = '2026-06-17';
    
    // Check if brochure already exists
    const exist = await db.query(
        "SELECT id FROM brochures WHERE market_id = ? AND start_date = ? AND title = ?",
        [marketId, startDate, title]
    );
    if (exist.length > 0) {
        console.log('✓ Migros broşürü zaten kayıtlı, atlanıyor.');
        return;
    }
    
    const timestamp = Math.floor(Date.now() / 1000);
    const coverUrl = uniqueUrls[0];
    const coverName = `migros_custom_cover_${timestamp}.webp`;
    const coverDest = path.join(__dirname, '../uploads/brochures', coverName);
    
    console.log(`Kapak resmi indiriliyor: ${coverUrl}`);
    await downloadFile(coverUrl, coverDest);
    
    // Insert brochure
    const insertResult = await db.query(
        "INSERT INTO brochures (market_id, title, cover_image, start_date, end_date) VALUES (?, ?, ?, ?, ?)",
        [marketId, title, coverName, startDate, endDate]
    );
    const brochureId = insertResult.insertId;
    
    console.log(`Broşür veritabanına eklendi. ID: ${brochureId}`);
    
    // Download pages
    for (let i = 0; i < uniqueUrls.length; i++) {
        const pageNum = i + 1;
        const pageUrl = uniqueUrls[i];
        const pageName = `migros_custom_page_${pageNum}_${timestamp}.webp`;
        const pageDest = path.join(__dirname, '../uploads/brochures/pages', pageName);
        
        console.log(`Sayfa ${pageNum}/${uniqueUrls.length} indiriliyor: ${pageUrl}`);
        try {
            await downloadFile(pageUrl, pageDest);
            await db.query(
                "INSERT INTO brochure_pages (brochure_id, page_number, image_path) VALUES (?, ?, ?)",
                [brochureId, pageNum, pageName]
            );
        } catch (err) {
            console.error(`❌ Sayfa ${pageNum} indirilemedi: ${err.message}`);
        }
    }
    console.log('✔ Migros broşürü indirme tamamlandı.');
}

async function importTarimKredi() {
    console.log('\n--- 2. TARIM KREDİ ÖZEL İNDİRME BAŞLATILIYOR ---');
    
    // Fetch market ID (both standard and production slugs)
    const marketRows = await db.query(
        "SELECT id FROM markets WHERE slug = 'tar-m-kredi-market-1780824588' OR slug = 'tarim-kredi-market'"
    );
    if (marketRows.length === 0) {
        console.error('❌ Hata: Veritabanında Tarım Kredi Market bulunamadı!');
        return;
    }
    const marketId = marketRows[0].id;
    
    const title = 'Tarım Kredi Market İndirim Bülteni 01 - 08 Haziran 2026';
    const startDate = '2026-06-01';
    const endDate = '2026-06-08';
    
    // Check if brochure already exists
    const exist = await db.query(
        "SELECT id FROM brochures WHERE market_id = ? AND start_date = ? AND title = ?",
        [marketId, startDate, title]
    );
    if (exist.length > 0) {
        console.log('✓ Tarım Kredi broşürü zaten kayıtlı, atlanıyor.');
        return;
    }
    
    const pages = [
        'https://d32aaotujjatu1.cloudfront.net/brochures/tarim-kredi-kooperatif/01-06-08-06-2026-order01-1068.webp',
        'https://d32aaotujjatu1.cloudfront.net/brochures/tarim-kredi-kooperatif/01-06-08-06-2026-order02-5418.webp'
    ];
    
    const timestamp = Math.floor(Date.now() / 1000);
    const coverUrl = pages[0];
    const coverName = `tarim_kredi_custom_cover_${timestamp}.webp`;
    const coverDest = path.join(__dirname, '../uploads/brochures', coverName);
    
    console.log(`Kapak resmi indiriliyor: ${coverUrl}`);
    await downloadFile(coverUrl, coverDest);
    
    // Insert brochure
    const insertResult = await db.query(
        "INSERT INTO brochures (market_id, title, cover_image, start_date, end_date) VALUES (?, ?, ?, ?, ?)",
        [marketId, title, coverName, startDate, endDate]
    );
    const brochureId = insertResult.insertId;
    
    console.log(`Broşür veritabanına eklendi. ID: ${brochureId}`);
    
    // Download pages
    for (let i = 0; i < pages.length; i++) {
        const pageNum = i + 1;
        const pageUrl = pages[i];
        const pageName = `tarim_kredi_custom_page_${pageNum}_${timestamp}.webp`;
        const pageDest = path.join(__dirname, '../uploads/brochures/pages', pageName);
        
        console.log(`Sayfa ${pageNum}/${pages.length} indiriliyor: ${pageUrl}`);
        try {
            await downloadFile(pageUrl, pageDest);
            await db.query(
                "INSERT INTO brochure_pages (brochure_id, page_number, image_path) VALUES (?, ?, ?)",
                [brochureId, pageNum, pageName]
            );
        } catch (err) {
            console.error(`❌ Sayfa ${pageNum} indirilemedi: ${err.message}`);
        }
    }
    console.log('✔ Tarım Kredi broşürü indirme tamamlandı.');
}

async function run() {
    try {
        await importMigros();
        await importTarimKredi();
        console.log('\n🌟 Özel aktarımlar tamamlandı.');
        process.exit(0);
    } catch (e) {
        console.error('❌ Beklenmeyen hata:', e);
        process.exit(1);
    }
}

run();
