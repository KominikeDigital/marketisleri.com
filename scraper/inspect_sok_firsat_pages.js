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
        
        fs.writeFileSync(path.join(__dirname, `${label.toLowerCase()}.html`), html);
        
        // Print headings
        console.log('Headings:');
        $('h1, h2, h3').each((i, el) => {
            console.log(`  - <${el.name}>: "${$(el).text().trim()}"`);
        });
        
        // Find date range or text indicating brochure dates
        // Let's print out text in possible date container
        const subTitle = $('.subTitle').text().trim() || $('.date').text().trim() || $('.campaign-date').text().trim();
        if (subTitle) console.log(`Date text: "${subTitle}"`);
        
        // Let's print out all images in the uploads folder
        console.log('Images in uploads:');
        $('img').each((i, el) => {
            const src = $(el).attr('src') || '';
            const alt = $(el).attr('alt') || '';
            if (src.includes('uploads')) {
                console.log(`  - src: "${src}", alt: "${alt}"`);
            }
        });
        
    } catch (e) {
        console.error(`Error inspecting ${label}:`, e.message);
    }
}

async function run() {
    await inspect('Sok_Wednesday_Details', 'https://kurumsal.sokmarket.com.tr/firsatlar/carsamba/');
    await inspect('Sok_Weekend_Details', 'https://kurumsal.sokmarket.com.tr/firsatlar/hafta-sonu/');
}

run();
