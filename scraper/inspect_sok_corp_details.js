const https = require('https');
const cheerio = require('cheerio');
const fs = require('fs');
const path = require('path');

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

async function inspect(label, url) {
    console.log(`\n--- Inspecting ${label} (${url}) ---`);
    try {
        const html = await fetchPage(url);
        console.log('Length:', html.length);
        const $ = cheerio.load(html);
        console.log('Title:', $('title').text().trim());
        
        // Save to file for further inspection if needed
        fs.writeFileSync(path.join(__dirname, `${label.toLowerCase().replace(/\s+/g, '_')}.html`), html);
        
        // Print headings
        console.log('Headings:');
        $('h1, h2, h3').each((i, el) => {
            console.log(`  - <${el.name}>: "${$(el).text().trim()}"`);
        });
        
        // Print images
        console.log('Images (first 10):');
        let imgCount = 0;
        $('img').each((i, el) => {
            if (imgCount < 10) {
                const src = $(el).attr('src') || '';
                const alt = $(el).attr('alt') || '';
                console.log(`  - src: "${src}", alt: "${alt}"`);
                imgCount++;
            }
        });
        
        // Print links containing pdf or image or download
        console.log('Links:');
        $('a').each((i, el) => {
            const href = $(el).attr('href') || '';
            const text = $(el).text().trim().replace(/\s+/g, ' ');
            if (href.includes('pdf') || href.includes('download') || href.includes('jpg') || href.includes('png') || text.includes('İndir') || text.includes('Katalog')) {
                console.log(`  - href: "${href}", text: "${text}"`);
            }
        });
        
    } catch (e) {
        console.error(`Error inspecting ${label}:`, e.message);
    }
}

async function run() {
    await inspect('Sok_Weekly', 'https://kurumsal.sokmarket.com.tr/haftanin-firsatlari/firsatlar');
    await inspect('Sok_Weekend', 'https://kurumsal.sokmarket.com.tr/firsatlar/haftasonu-firsatlari');
}

run();
