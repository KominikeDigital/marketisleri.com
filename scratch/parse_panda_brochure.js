const https = require('https');
const cheerio = require('../scraper/node_modules/cheerio');

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

async function main() {
    const url = 'https://indirimcipanda.com/brosur/migros-migroskop-brosuru-04-17-haziran-2026';
    try {
        const { status, html } = await fetchPage(url);
        console.log(`Status: ${status}`);
        
        const $ = cheerio.load(html);
        console.log("Page title:", $('title').text());
        
        // Regex search for image URLs
        const imgUrls = html.match(/https?:\/\/[^\s'"]+?\.(jpg|jpeg|png|webp|gif)/gi) || [];
        console.log(`Found ${imgUrls.length} image URLs in source.`);
        
        // Filter unique cloudfront or next/image URLs
        const uniqueUrls = [...new Set(imgUrls)].filter(u => u.includes('cloudfront') || u.includes('_next/image') || u.includes('panda'));
        console.log("Unique matching image URLs:");
        console.log(uniqueUrls);
    } catch (e) {
        console.error(e);
    }
}

main();
