const https = require('https');

function checkRedirect(key) {
    return new Promise((resolve, reject) => {
        const url = `https://www.bim.com.tr/Categories/680/afisler.aspx?top=1&Bim_AfisKey=${key}`;
        https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            }
        }, (res) => {
            console.log(`Key: ${key}`);
            console.log(`  Status Code: ${res.statusCode}`);
            console.log(`  Location Header: ${res.headers.location || 'None'}`);
            
            let data = '';
            res.on('data', chunk => { data += chunk; });
            res.on('end', () => {
                // Find page title
                const titleMatch = data.match(/<title>([^<]+)<\/title>/i);
                console.log(`  Page Title: "${titleMatch ? titleMatch[1].trim() : 'N/A'}"`);
                resolve();
            });
        }).on('error', err => reject(err));
    });
}

async function run() {
    await checkRedirect('1265');
    await checkRedirect('1256');
    await checkRedirect('1248');
}

run();
