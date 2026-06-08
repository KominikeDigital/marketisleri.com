const https = require('https');
const fs = require('fs');
const path = require('path');

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
                // Merge cookies
                const cookieMap = {};
                if (cookies) {
                    cookies.split('; ').forEach(c => {
                        const [k, v] = c.split('=');
                        if (k) cookieMap[k] = v;
                    });
                }
                cookieParts.forEach(c => {
                    const [k, v] = c.split('=');
                    if (k) cookieMap[k] = v;
                });
                newCookies = Object.entries(cookieMap).map(([k, v]) => `${k}=${v}`).join('; ');
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
        console.log('Cookies after Step 1:', r1.cookies);
        
        console.log('\nStep 2: Requesting brochure detail page...');
        const r2 = await fetchPage(
            'https://aktuelbrosurler.com/metro-market/brosurler/dunya-kupasi-nin-lezzet-kadrosu-sahada_be49f268842c41978617b1f24758bee8',
            'https://aktuelbrosurler.com/metrotoptancimarket/brosurler',
            r1.cookies
        );
        console.log('Cookies after Step 2:', r2.cookies);

        console.log('\nStep 3: Requesting iframe page...');
        const r3 = await fetchPage(
            'https://aktuelbrosurler.com/brosur.aspx?id=be49f268842c41978617b1f24758bee8',
            'https://aktuelbrosurler.com/metro-market/brosurler/dunya-kupasi-nin-lezzet-kadrosu-sahada_be49f268842c41978617b1f24758bee8',
            r2.cookies
        );
        console.log('Cookies after Step 3:', r3.cookies);
        
        const iframeHtml = r3.body.toString('utf8');
        console.log('Iframe length:', iframeHtml.length);
        fs.writeFileSync('scratch/iframe.html', iframeHtml);
        console.log('Saved iframe HTML to scratch/iframe.html');
        
        // Let's try to request the first page image
        const imgUrl = 'https://aktuelbrosurler.com/brosur.ashx?k=be49f268842c41978617b1f24758bee8&resim=144740.webp&ts=1780920000&sig=2da9c8a20ad200c01742b1114cbd8ad81696be5c2862abcabc4510740971662b';
        console.log('\nStep 4: Requesting image:', imgUrl);
        const r4 = await fetchPage(
            imgUrl,
            'https://aktuelbrosurler.com/brosur.aspx?id=be49f268842c41978617b1f24758bee8',
            r3.cookies
        );
        
        console.log('Image Response Status:', r4.statusCode);
        console.log('Image Content-Type:', r4.headers['content-type']);
        console.log('Image Body Length:', r4.body.length);
        
        if (r4.statusCode === 200 && r4.body.length > 1000) {
            fs.writeFileSync('scratch/test_metro_page.webp', r4.body);
            console.log('SUCCESS: Saved image to scratch/test_metro_page.webp');
        } else {
            console.log('Failed body:', r4.body.toString('utf8'));
        }
        
    } catch (e) {
        console.error(e);
    }
}
run();
