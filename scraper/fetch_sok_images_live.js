const https = require('https');
const cheerio = require('cheerio');

function fetchPage(url) {
    return new Promise((resolve, reject) => {
        https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            }
        }, (res) => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                let redirectUrl = res.headers.location;
                if (!redirectUrl.startsWith('http')) {
                    const parsedUrl = new URL(url);
                    redirectUrl = `${parsedUrl.protocol}//${parsedUrl.host}${redirectUrl}`;
                }
                return fetchPage(redirectUrl).then(resolve).catch(reject);
            }
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => { resolve(data); });
        }).on('error', (err) => { reject(err); });
    });
}

async function run() {
    try {
        const weeklyHtml = await fetchPage('https://kurumsal.sokmarket.com.tr/haftanin-firsatlari/firsatlar');
        const $w = cheerio.load(weeklyHtml);
        console.log('--- Live Weekly Images ---');
        $w('img').each((i, el) => {
            const src = $w(el).attr('src') || '';
            const alt = $w(el).attr('alt') || '';
            if (src.includes('uploads') || alt.toLowerCase().includes('fırsat')) {
                console.log(`  Img: src="${src}", alt="${alt}"`);
            }
        });
        
        const weekendHtml = await fetchPage('https://kurumsal.sokmarket.com.tr/firsatlar/haftasonu-firsatlari');
        const $we = cheerio.load(weekendHtml);
        console.log('\n--- Live Weekend Images ---');
        $we('img').each((i, el) => {
            const src = $we(el).attr('src') || '';
            const alt = $we(el).attr('alt') || '';
            if (src.includes('uploads') || alt.toLowerCase().includes('fırsat')) {
                console.log(`  Img: src="${src}", alt="${alt}"`);
            }
        });
    } catch (e) {
        console.error(e.message);
    }
}

run();
