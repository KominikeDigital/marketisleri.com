const https = require('https');
const cheerio = require('cheerio');

function fetchPage(url) {
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

async function getImages(key) {
    const url = `https://www.bim.com.tr/Categories/680/afisler.aspx?top=1&Bim_AfisKey=${key}`;
    const html = await fetchPage(url);
    const $ = cheerio.load(html);
    const images = [];
    $('[data-bigimg]').each((i, el) => {
        images.push($(el).attr('data-bigimg'));
    });
    return images;
}

async function run() {
    try {
        console.log('Fetching images for 1265 ("03-29 Haziran")...');
        const img1265 = await getImages('1265');
        console.log(`Key 1265 has ${img1265.length} images.`);
        
        console.log('\nFetching images for 1256 ("03 Haziran Çarşamba")...');
        const img1256 = await getImages('1256');
        console.log(`Key 1256 has ${img1256.length} images.`);
        
        console.log('\nFetching images for 1266 ("03 Haziran-24 Ağustos")...');
        const img1266 = await getImages('1266');
        console.log(`Key 1266 has ${img1266.length} images.`);
        
        // Find overlap
        const overlap = img1265.filter(x => img1256.includes(x));
        console.log(`\nOverlap between 1265 and 1256: ${overlap.length} images.`);
        
    } catch (e) {
        console.error('Error:', e.message);
    }
}

run();
