const https = require('https');
const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

function fetchPage(url, depth = 0) {
    if (depth > 5) return Promise.reject(new Error('Too many redirects'));
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const options = {
            hostname: parsedUrl.hostname,
            path: parsedUrl.pathname + parsedUrl.search,
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
                return fetchPage(redirectUrl, depth + 1).then(resolve).catch(reject);
            }
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => { resolve(data); });
        }).on('error', (err) => { reject(err); });
    });
}

async function run() {
    try {
        const html = await fetchPage('https://kurumsal.sokmarket.com.tr/anasayfa');
        console.log('Fetched Corporate Page. Length:', html.length);
        const $ = cheerio.load(html);
        
        console.log('Title:', $('title').text().trim());
        
        console.log('\nAll links containing campaign keywords:');
        $('a').each((i, el) => {
            const href = $(el).attr('href') || '';
            const text = $(el).text().trim().replace(/\s+/g, ' ');
            const lowerHref = href.toLowerCase();
            const lowerText = text.toLowerCase();
            if (
                lowerHref.includes('brosur') || lowerHref.includes('katalog') || lowerHref.includes('afis') || lowerHref.includes('kampanya') || lowerHref.includes('firsat') ||
                lowerText.includes('broşür') || lowerText.includes('katalog') || lowerText.includes('afiş') || lowerText.includes('kampanya') || lowerText.includes('fırsat')
            ) {
                console.log(`- href: "${href}", text: "${text}"`);
            }
        });
    } catch (e) {
        console.error('Error fetching corporate:', e.message);
    }
}

run();
