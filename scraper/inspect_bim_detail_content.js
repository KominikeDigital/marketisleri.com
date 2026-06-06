const https = require('https');
const crypto = require('crypto');

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

async function run() {
    try {
        console.log('Fetching raw html for 1265...');
        const html1265 = await fetchPage('https://www.bim.com.tr/Categories/680/afisler.aspx?top=1&Bim_AfisKey=1265');
        const hash1265 = crypto.createHash('md5').update(html1265).digest('hex');
        console.log(`Length: ${html1265.length}, Hash: ${hash1265}`);
        
        console.log('Fetching raw html for 1256...');
        const html1256 = await fetchPage('https://www.bim.com.tr/Categories/680/afisler.aspx?top=1&Bim_AfisKey=1256');
        const hash1256 = crypto.createHash('md5').update(html1256).digest('hex');
        console.log(`Length: ${html1256.length}, Hash: ${hash1256}`);
        
        console.log('Fetching raw html for 1248...');
        const html1248 = await fetchPage('https://www.bim.com.tr/Categories/680/afisler.aspx?top=1&Bim_AfisKey=1248');
        const hash1248 = crypto.createHash('md5').update(html1248).digest('hex');
        console.log(`Length: ${html1248.length}, Hash: ${hash1248}`);
        
        console.log(`\nIs 1265 HTML identical to 1256 HTML? ${html1265 === html1256}`);
        console.log(`Is 1265 HTML identical to 1248 HTML? ${html1265 === html1248}`);
        
    } catch (e) {
        console.error('Error:', e.message);
    }
}

run();
