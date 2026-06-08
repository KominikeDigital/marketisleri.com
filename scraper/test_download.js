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
            const setCookies = res.headers['set-cookie'] || [];
            let newCookies = cookies;
            if (setCookies.length > 0) {
                const cookieParts = setCookies.map(c => c.split(';')[0]);
                newCookies = cookieParts.join('; ');
            }

            let data = [];
            res.on('data', chunk => data.push(chunk));
            res.on('end', () => {
                resolve({
                    statusCode: res.statusCode,
                    headers: res.headers,
                    cookies: newCookies,
                    body: Buffer.concat(data)
                });
            });
        }).on('error', reject);
    });
}

async function run() {
    try {
        console.log('Step 1: Requesting main catalog list...');
        const r1 = await fetchPage('https://aktuelbrosurler.com/metrotoptancimarket/brosurler');
        
        console.log('\nStep 2: Requesting brochure detail page...');
        const r2 = await fetchPage(
            'https://aktuelbrosurler.com/metro-market/brosurler/dunya-kupasi-nin-lezzet-kadrosu-sahada_be49f268842c41978617b1f24758bee8',
            'https://aktuelbrosurler.com/metrotoptancimarket/brosurler',
            r1.cookies
        );

        console.log('\nStep 3: Requesting iframe page...');
        const r3 = await fetchPage(
            'https://aktuelbrosurler.com/brosur.aspx?id=be49f268842c41978617b1f24758bee8',
            'https://aktuelbrosurler.com/metro-market/brosurler/dunya-kupasi-nin-lezzet-kadrosu-sahada_be49f268842c41978617b1f24758bee8',
            r2.cookies
        );
        
        const iframeHtml = r3.body.toString('utf8');
        console.log('Iframe length:', iframeHtml.length);
        
        // Find any brosur.ashx or similar link in iframeHtml
        const matches = [];
        const regex = /brosur\.ashx\?[^'"]+/gi;
        let match;
        while ((match = regex.exec(iframeHtml)) !== null) {
            matches.push(match[0]);
        }
        
        console.log('Found brosur.ashx links in iframe HTML:', matches.length);
        if (matches.length > 0) {
            console.log('First 3 matches:');
            console.log(matches.slice(0, 3));
        } else {
            console.log('Sample of iframe HTML around FlipHTML5 variables or data:');
            const lines = iframeHtml.split('\n');
            for (let line of lines) {
                if (line.includes('fliphtml5') || line.includes('pages') || line.includes('.jpg') || line.includes('.webp') || line.includes('config')) {
                    console.log(line.trim().substring(0, 300));
                }
            }
        }
        
    } catch (e) {
        console.error(e);
    }
}
run();
