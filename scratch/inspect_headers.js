const https = require('https');

const userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

function fetchPage(url, referer = '', cookies = '') {
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const headers = {
            'User-Agent': userAgent,
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language': 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Connection': 'keep-alive'
        };
        if (referer) headers['Referer'] = referer;
        if (cookies) headers['Cookie'] = cookies;

        const options = {
            hostname: parsedUrl.hostname,
            path: parsedUrl.pathname + parsedUrl.search,
            headers: headers
        };

        https.get(options, (res) => {
            console.log(`\n--- Response for ${url} ---`);
            console.log(`Status: ${res.statusCode}`);
            console.log(`Headers:`, res.headers);
            
            let data = [];
            res.on('data', chunk => data.push(chunk));
            res.on('end', () => {
                resolve({
                    statusCode: res.statusCode,
                    headers: res.headers,
                    body: Buffer.concat(data)
                });
            });
        }).on('error', reject);
    });
}

async function run() {
    try {
        console.log('Fetching Step 1...');
        const r1 = await fetchPage('https://aktuelbrosurler.com/metrotoptancimarket/brosurler');
        
        console.log('Fetching Step 2...');
        const r2 = await fetchPage(
            'https://aktuelbrosurler.com/metro-market/brosurler/dunya-kupasi-nin-lezzet-kadrosu-sahada_be49f268842c41978617b1f24758bee8',
            'https://aktuelbrosurler.com/metrotoptancimarket/brosurler'
        );

        console.log('Fetching Step 3...');
        const r3 = await fetchPage(
            'https://aktuelbrosurler.com/brosur.aspx?id=be49f268842c41978617b1f24758bee8',
            'https://aktuelbrosurler.com/metro-market/brosurler/dunya-kupasi-nin-lezzet-kadrosu-sahada_be49f268842c41978617b1f24758bee8'
        );
    } catch (e) {
        console.error(e);
    }
}
run();
