const https = require('https');
const fs = require('fs');
const path = require('path');
const db = require('./db');
const cheerio = require('cheerio');

// Helper to make HTTPS requests and handle redirects
function fetchPage(url, depth = 0) {
    if (depth > 5) return Promise.reject(new Error('Too many redirects'));
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const options = {
            hostname: parsedUrl.hostname,
            path: parsedUrl.pathname + parsedUrl.search,
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language': 'tr-TR,tr;q=0.8,en-US;q=0.5,en;q=0.3'
            }
        };
        https.get(options, (res) => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                let redirectUrl = res.headers.location;
                if (!redirectUrl.startsWith('http')) {
                    const origin = `${parsedUrl.protocol}//${parsedUrl.host}`;
                    redirectUrl = new URL(redirectUrl, origin).toString();
                }
                return fetchPage(redirectUrl, depth + 1).then(resolve).catch(reject);
            }
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

async function scrapeA101() {
    console.log('[A101 Scraper] Tarama işlemi başlıyor...');
    
    // Find A101 market ID
    const marketRows = await db.query("SELECT id FROM markets WHERE slug = 'a101'");
    if (marketRows.length === 0) {
        console.error('❌ Hata: Veritabanında slug değeri "a101" olan bir market bulunamadı.');
        return;
    }
    const marketId = marketRows[0].id;
    
    const homeUrl = 'https://www.a101.com.tr/';
    const homeHtml = await fetchPage(homeUrl);
    const $ = cheerio.load(homeHtml);
    
    // Find matching brochure links in homepage
    const brochuresFound = [];
    const seenHrefs = new Set();
    
    $('a').each((i, el) => {
        const href = $(el).attr('href') || '';
        const text = $(el).text().trim().replace(/\s+/g, ' ');
        const lowerHref = href.toLowerCase();
        
        // Match flyer links, ignore doorway or checkout pages
        if (
            (lowerHref.startsWith('/afisler-') || lowerHref.startsWith('/afis-') || lowerHref.startsWith('/aldin-aldin-')) &&
            !lowerHref.includes('/kapida') && !lowerHref.includes('/liste') && !seenHrefs.has(href)
        ) {
            seenHrefs.add(href);
            brochuresFound.push({ href, text });
        }
    });
    
    console.log(`[A101 Scraper] Toplam ${brochuresFound.length} adet broşür linki tespit edildi.`);
    
    let newBrochuresCount = 0;
    
    for (const b of brochuresFound) {
        const key = b.href.replace(/^\//, '');
        const detailUrl = 'https://www.a101.com.tr' + b.href;
        
        console.log(`\n🔍 [A101 Scraper] Detay sayfası inceleniyor: ${detailUrl}`);
        let detailHtml = '';
        try {
            detailHtml = await fetchPage(detailUrl);
        } catch (err) {
            console.error(`❌ Detay sayfası çekilemedi (${detailUrl}):`, err.message);
            continue;
        }
        
        const $d = cheerio.load(detailHtml);
        const h1Text = $d('h1').text().trim();
        const pageTitle = $d('title').text().trim();
        
        // Parse dates: Link text first, then fallbacks
        const linkDates = parseTurkishDateRange(b.text);
        let startDate = linkDates.startDate;
        let endDate = linkDates.endDate;
        
        const todayStr = new Date().toISOString().split('T')[0];
        if (startDate === todayStr) {
            const h1Dates = parseTurkishDateRange(h1Text);
            if (h1Dates.startDate !== todayStr) {
                startDate = h1Dates.startDate;
                endDate = h1Dates.endDate;
            } else {
                const titleDates = parseTurkishDateRange(pageTitle);
                if (titleDates.startDate !== todayStr) {
                    startDate = titleDates.startDate;
                    endDate = titleDates.endDate;
                }
            }
        }
        
        // Generate a descriptive brochure title
        let cleanTitle = h1Text || pageTitle.split('|')[0].trim() || key.replace(/-/g, ' ');
        // If cleanTitle is generic, clean it up
        if (cleanTitle.toLowerCase().includes('aldın aldın gelecek hafta')) {
            cleanTitle = 'Aldın Aldın Gelecek Hafta Kataloğu';
        } else if (cleanTitle.toLowerCase().includes('aldın aldın bu hafta')) {
            cleanTitle = 'Aldın Aldın Bu Hafta Kataloğu';
        }
        
        const brochureTitle = `A101 ${cleanTitle}`;
        
        // Check if already exists in DB
        const existRows = await db.query(
            "SELECT id FROM brochures WHERE market_id = ? AND start_date = ? AND title = ?",
            [marketId, startDate, brochureTitle]
        );
        
        if (existRows.length > 0) {
            console.log(`[A101 Scraper] Atlanıyor (Zaten kayıtlı): "${brochureTitle}" (${startDate})`);
            continue;
        }
        
        // Extract brochure page images (those matching CALL/Image/get/)
        const imageUrls = [];
        $d('img').each((i, imgEl) => {
            const imgUrl = $d(imgEl).attr('src') || '';
            if (imgUrl.includes('/CALL/Image/get/') && !imageUrls.includes(imgUrl)) {
                imageUrls.push(imgUrl);
            }
        });
        
        if (imageUrls.length === 0) {
            console.warn(`⚠️ [A101 Scraper] Detay sayfasında broşür görseli bulunamadı. (${detailUrl})`);
            continue;
        }
        
        console.log(`🌟 [A101 Scraper] Yeni katalog bulundu: "${brochureTitle}" (${startDate} -> ${endDate})`);
        console.log(`[A101 Scraper] Katalogda ${imageUrls.length} sayfa bulundu. İndiriliyor...`);
        
        const uploadsDir = path.join(__dirname, '../uploads');
        const timestamp = Math.floor(Date.now() / 1000);
        
        // 1. Download cover image (first page)
        const coverName = `a101_${key}_cover_${timestamp}.jpg`;
        const coverDest = path.join(uploadsDir, 'brochures', coverName);
        console.log(`  -> Kapak indiriliyor: ${coverName}`);
        try {
            await downloadFile(imageUrls[0], coverDest);
        } catch (err) {
            console.error(`❌ Kapak indirilemedi:`, err.message);
            continue;
        }
        
        // 2. Insert brochure record to DB
        let insertResult;
        try {
            insertResult = await db.query(
                "INSERT INTO brochures (market_id, title, cover_image, start_date, end_date) VALUES (?, ?, ?, ?, ?)",
                [marketId, brochureTitle, coverName, startDate, endDate]
            );
        } catch (err) {
            console.error(`❌ Veritabanına katalog eklenemedi:`, err.message);
            continue;
        }
        const brochureId = insertResult.insertId;
        
        // 3. Download page images and insert page records
        for (let i = 0; i < imageUrls.length; i++) {
            const pageNum = i + 1;
            const pageName = `a101_${key}_page_${pageNum}_${timestamp}.jpg`;
            const pageDest = path.join(uploadsDir, 'brochures/pages', pageName);
            
            console.log(`  -> Sayfa ${pageNum} indiriliyor: ${pageName}`);
            try {
                await downloadFile(imageUrls[i], pageDest);
                await db.query(
                    "INSERT INTO brochure_pages (brochure_id, page_number, image_path) VALUES (?, ?, ?)",
                    [brochureId, pageNum, pageName]
                );
            } catch (err) {
                console.error(`❌ Sayfa ${pageNum} indirilemedi/kaydedilemedi:`, err.message);
            }
        }
        
        console.log(`✓ [A101 Scraper] Başarıyla kaydedildi. ID: ${brochureId}`);
        newBrochuresCount++;
    }
    
    console.log(`[A101 Scraper] Tarama tamamlandı. ${newBrochuresCount} adet yeni broşür eklendi.`);
}

module.exports = {
    scrapeA101
};
