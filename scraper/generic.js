const https = require('https');
const fs = require('fs');
const path = require('path');
const db = require('./db');
const cheerio = require('cheerio');

// Helper to make HTTPS requests and handle redirects
function fetchPage(url, depth = 0) {
    if (depth > 5) return Promise.reject(new Error('Too many redirects'));
    return new Promise((resolve, reject) => {
        try {
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
        } catch (e) {
            reject(e);
        }
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
            fs.unlink(destPath, () => {});
            reject(err);
        });
    });
}

// Helper to parse Turkish dates in a string
const months = {
    'ocak': 1, 'şubat': 2, 'mart': 3, 'nisan': 4, 'mayıs': 5, 'haziran': 6,
    'temmuz': 7, 'ağustos': 8, 'eylül': 9, 'ekim': 10, 'kasım': 11, 'aralık': 12
};

function parseTurkishDateRange(titleText) {
    const now = new Date();
    let year = now.getFullYear();
    const currentMonth = now.getMonth() + 1;

    const text = titleText.toLowerCase().trim().replace(/\s+/g, ' ');
    
    const formatDate = (y, m, d) => {
        const mStr = String(m).padStart(2, '0');
        const dStr = String(d).padStart(2, '0');
        return `${y}-${mStr}-${dStr}`;
    };

    const addDays = (dateStr, days) => {
        const d = new Date(dateStr);
        d.setDate(d.getDate() + days);
        return d.toISOString().split('T')[0];
    };

    // Range across months (e.g. "24 Mart-31 Aralık", "30 Mayıs - 5 Haziran")
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

    // Range within same month (e.g. "03-29 Haziran", "05-08 Haziran")
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

    // Single Date (e.g. "25 Mayıs Pazartesi")
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

// Main logic to parse attribute values (handles src, data-src, href, etc.)
function getElementValue($, element, selector, attribute = null) {
    let el = selector ? $(element).find(selector) : $(element);
    if (el.length === 0) return '';
    
    if (attribute) {
        return el.attr(attribute) || '';
    }
    
    // Auto-detect common attributes if none provided
    const tagName = el.prop('tagName').toLowerCase();
    if (tagName === 'img') {
        return el.attr('src') || el.attr('data-src') || el.attr('data-original') || '';
    }
    if (tagName === 'a') {
        return el.attr('href') || '';
    }
    
    return el.text().trim();
}

async function scrapeGenericActiveMarkets() {
    console.log('[Generic Scraper] Aktif dinamik marketler taranıyor...');
    
    // Fetch all active markets with scraping rules configured
    const activeMarkets = await db.query(
        "SELECT * FROM markets WHERE scraper_active = 1 AND scraper_url IS NOT NULL AND scraper_url != ''"
    );
    
    if (activeMarkets.length === 0) {
        console.log('[Generic Scraper] Aktif dinamik market bulunamadı.');
        return;
    }
    
    console.log(`[Generic Scraper] ${activeMarkets.length} adet aktif dinamik market bulundu.`);
    
    for (const market of activeMarkets) {
        console.log(`\n🔍 [Generic Scraper] ${market.name} için tarama başlatıldı: ${market.scraper_url}`);
        
        try {
            const html = await fetchPage(market.scraper_url);
            const $ = cheerio.load(html);
            const containerSelector = market.scraper_container;
            
            if (!containerSelector) {
                console.warn(`⚠️ Hata: ${market.name} için container seçici tanımlanmamış.`);
                continue;
            }
            
            const cards = $(containerSelector);
            console.log(`[Generic Scraper] ${cards.length} adet broşür kartı tespit edildi.`);
            
            let newBrochuresCount = 0;
            const uploadsDir = path.join(__dirname, '../uploads');
            const timestamp = Math.floor(Date.now() / 1000);
            
            const parsedUrl = new URL(market.scraper_url);
            const origin = `${parsedUrl.protocol}//${parsedUrl.host}`;
            
            for (let i = 0; i < cards.length; i++) {
                const card = cards[i];
                
                // Get Title
                let title = getElementValue($, card, market.scraper_title);
                if (!title) {
                    title = `${market.name} Kataloğu`;
                } else {
                    title = `${market.name} ${title}`;
                }
                
                // Parse Dates
                const dates = parseTurkishDateRange(title);
                const startDate = dates.startDate;
                const endDate = dates.endDate;
                
                // Get Cover Image URL
                let coverUrl = getElementValue($, card, market.scraper_cover);
                if (!coverUrl) {
                    console.warn(`  -> Atlanıyor: Kapak resmi bulunamadı.`);
                    continue;
                }
                if (coverUrl.startsWith('//')) coverUrl = 'https:' + coverUrl;
                if (!coverUrl.startsWith('http')) coverUrl = new URL(coverUrl, origin).toString();
                
                // Get Details Link URL (optional, if none is provided we just use cover as the only page)
                let detailUrl = '';
                if (market.scraper_detail_link) {
                    detailUrl = getElementValue($, card, market.scraper_detail_link);
                    if (detailUrl) {
                        if (detailUrl.startsWith('//')) detailUrl = 'https:' + detailUrl;
                        if (!detailUrl.startsWith('http')) detailUrl = new URL(detailUrl, origin).toString();
                    }
                }
                
                // Check if brochure already exists
                const existRows = await db.query(
                    "SELECT id FROM brochures WHERE market_id = ? AND start_date = ? AND title = ?",
                    [market.id, startDate, title]
                );
                
                if (existRows.length > 0) {
                    continue;
                }
                
                console.log(`🌟 [Generic Scraper] Yeni broşür bulundu: "${title}" (${startDate})`);
                
                // 1. Download cover
                const coverName = `${market.slug}_gen_${i}_cover_${timestamp}.jpg`;
                const coverDest = path.join(uploadsDir, 'brochures', coverName);
                
                try {
                    await downloadFile(coverUrl, coverDest);
                } catch (err) {
                    console.error(`  ❌ Kapak indirilemedi (${coverUrl}):`, err.message);
                    continue;
                }
                
                // 2. Insert brochure
                let insertResult;
                try {
                    insertResult = await db.query(
                        "INSERT INTO brochures (market_id, title, cover_image, start_date, end_date) VALUES (?, ?, ?, ?, ?)",
                        [market.id, title, coverName, startDate, endDate]
                    );
                } catch (err) {
                    console.error(`  ❌ Veritabanına katalog eklenemedi:`, err.message);
                    fs.unlink(coverDest, () => {});
                    continue;
                }
                const brochureId = insertResult.insertId;
                
                // 3. Handle pages
                let pageImages = [];
                if (detailUrl && market.scraper_page_image) {
                    console.log(`  -> Detay sayfası taranıyor: ${detailUrl}`);
                    try {
                        const detailHtml = await fetchPage(detailUrl);
                        const $d = cheerio.load(detailHtml);
                        
                        $d(market.scraper_page_image).each((j, imgEl) => {
                            let imgUrl = $d(imgEl).attr('src') || $d(imgEl).attr('data-src') || $d(imgEl).attr('data-original') || '';
                            if (imgUrl && !pageImages.includes(imgUrl)) {
                                if (imgUrl.startsWith('//')) imgUrl = 'https:' + imgUrl;
                                if (!imgUrl.startsWith('http')) imgUrl = new URL(imgUrl, origin).toString();
                                pageImages.push(imgUrl);
                            }
                        });
                    } catch (err) {
                        console.warn(`  ⚠️ Detay sayfası yüklenemedi:`, err.message);
                    }
                }
                
                // Fallback: if no pages found, use the cover image as page 1
                if (pageImages.length === 0) {
                    pageImages.push(coverUrl);
                }
                
                console.log(`  -> Toplam ${pageImages.length} sayfa resmi indiriliyor...`);
                for (let pNum = 1; pNum <= pageImages.length; pNum++) {
                    const pageUrl = pageImages[pNum - 1];
                    const pageName = `${market.slug}_gen_${i}_page_${pNum}_${timestamp}.jpg`;
                    const pageDest = path.join(uploadsDir, 'brochures/pages', pageName);
                    
                    try {
                        await downloadFile(pageUrl, pageDest);
                        await db.query(
                            "INSERT INTO brochure_pages (brochure_id, page_number, image_path) VALUES (?, ?, ?)",
                            [brochureId, pNum, pageName]
                        );
                    } catch (err) {
                        console.error(`    ❌ Sayfa ${pNum} indirilemedi (${pageUrl}):`, err.message);
                    }
                }
                
                console.log(`  ✓ Başarıyla kaydedildi. ID: ${brochureId}`);
                newBrochuresCount++;
            }
            
            console.log(`[Generic Scraper] ${market.name} taraması bitti. ${newBrochuresCount} yeni broşür eklendi.`);
        } catch (e) {
            console.error(`❌ [Generic Scraper] ${market.name} taranırken beklenmedik hata:`, e.stack || e.message);
        }
    }
}

module.exports = {
    scrapeGenericActiveMarkets
};
