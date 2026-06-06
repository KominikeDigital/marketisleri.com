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
    const testUrl = 'https://www.a101.com.tr/aldin-aldin-gelecek-hafta-brosuru';
    try {
        const html = await fetchPage(testUrl);
        fs.writeFileSync(path.join(__dirname, 'a101_detail.html'), html);
        console.log(`Fetched ${testUrl}. Length: ${html.length}`);
        
        const $ = cheerio.load(html);
        console.log('Title:', $('title').text().trim());
        
        // Find header, dates or descriptive text in details
        console.log('\nh1 text:', $('h1').text().trim());
        console.log('h2 text:', $('h2').text().trim());
        
        // Let's print out all images containing "cdn2.a101.com.tr" or other files
        console.log('\nImages in page:');
        $('img').each((i, el) => {
            const src = $(el).attr('src') || '';
            const alt = $(el).attr('alt') || '';
            if (src.includes('cdn2.a101.com.tr') || src.includes('files/')) {
                console.log(`- src: "${src}", alt: "${alt}"`);
            }
        });
        
        // If there are no images from cdn2, print general images
        console.log('\nFirst 10 general images:');
        let count = 0;
        $('img').each((i, el) => {
            if (count < 10) {
                console.log(`- general src: "${$(el).attr('src')}", alt: "${$(el).attr('alt')}"`);
                count++;
            }
        });
    } catch (e) {
        console.error('Error fetching detail:', e.message);
    }
}

run();
