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

async function run() {
    try {
        const html = await fetchPage('https://www.sokmarket.com.tr/kampanyalar');
        console.log('Fetched ŞOK Kampanyalar Page. Length:', html.length);
        fs.writeFileSync(path.join(__dirname, 'sok_kampanyalar.html'), html);
        
        const $ = cheerio.load(html);
        console.log('Title:', $('title').text().trim());
        
        // Print all links
        console.log('\nLinks in Kampanyalar page:');
        $('a').each((i, el) => {
            const href = $(el).attr('href') || '';
            const text = $(el).text().trim().replace(/\s+/g, ' ');
            if (href.includes('brosur') || href.includes('katalog') || href.includes('firsat') || href.includes('kampanya') || text) {
                console.log(`- href: "${href}", text: "${text}"`);
            }
        });
        
        // Check for images
        console.log('\nImages in Kampanyalar page (excluding icons):');
        $('img').each((i, el) => {
            const src = $(el).attr('src') || '';
            const alt = $(el).attr('alt') || '';
            if (!src.includes('logo') && !src.includes('badge') && !src.includes('service-types')) {
                console.log(`- src: "${src}", alt: "${alt}"`);
            }
        });
    } catch (e) {
        console.error('Error fetching ŞOK Kampanyalar:', e.message);
    }
}

run();
