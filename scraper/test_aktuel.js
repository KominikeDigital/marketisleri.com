const https = require('https');
const cheerio = require('cheerio');

function fetchPage(url) {
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
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve(data));
        }).on('error', reject);
    });
}

async function run() {
    try {
        const html = await fetchPage('https://aktuelbrosurler.com/metrotoptancimarket/brosurler');
        const $ = cheerio.load(html);
        const cards = $('a.brosur-link');
        console.log('Number of cards found:', cards.length);
        if (cards.length > 0) {
            console.log('HTML of first card:\n', $.html(cards.first()));
        }
    } catch (e) {
        console.error(e);
    }
}
run();
