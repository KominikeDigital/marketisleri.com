const https = require('https');
const cheerio = require('../scraper/node_modules/cheerio');

const userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

function makeRequest(url, headers = {}) {
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const options = {
            hostname: parsedUrl.hostname,
            path: parsedUrl.pathname + parsedUrl.search,
            headers: {
                'User-Agent': userAgent,
                ...headers
            }
        };

        https.get(options, (res) => {
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
        // Step 1: Fetch Metro main list
        console.log('Step 1: Fetching list page...');
        const listUrl = 'https://aktuelbrosurler.com/metrotoptancimarket/brosurler';
        const r1 = await makeRequest(listUrl);
        const cookie1 = r1.headers['set-cookie'] ? r1.headers['set-cookie'].map(c => c.split(';')[0]).join('; ') : '';
        
        const $ = cheerio.load(r1.body.toString('utf8'));
        const firstHref = $('a.brosur-link').first().attr('href');
        const detailUrl = firstHref.startsWith('http') ? firstHref : new URL(firstHref, listUrl).toString();
        console.log('Detail URL:', detailUrl);
        
        // Step 2: Fetch detail page
        console.log('Step 2: Fetching detail page...');
        const r2 = await makeRequest(detailUrl, { 'Cookie': cookie1, 'Referer': listUrl });
        const cookie2 = r2.headers['set-cookie'] ? r2.headers['set-cookie'].map(c => c.split(';')[0]).join('; ') : cookie1;
        
        // Step 3: Fetch iframe
        const detailHtml = r2.body.toString('utf8');
        const iframeMatch = detailHtml.match(/brosur\.aspx\?id=([a-f0-9]+)/i);
        if (!iframeMatch) {
            console.log('No iframe found.');
            return;
        }
        const iframeUrl = `https://aktuelbrosurler.com/brosur.aspx?id=${iframeMatch[1]}`;
        console.log('Iframe URL:', iframeUrl);
        
        const r3 = await makeRequest(iframeUrl, { 'Cookie': cookie2, 'Referer': detailUrl });
        const cookie3 = r3.headers['set-cookie'] ? r3.headers['set-cookie'].map(c => c.split(';')[0]).join('; ') : cookie2;
        
        // Extract page URL
        const iframeHtml = r3.body.toString('utf8');
        const pageRegex = /'l':\s*'([^']+)'/;
        const match = pageRegex.exec(iframeHtml);
        if (!match) {
            console.log('No image URL found in iframe.');
            return;
        }
        const imgUrl = match[1].replace(/\\u0026/g, '&');
        console.log('Target Image URL:', imgUrl);
        
        // Let's test combinations
        const tests = [
            {
                name: '1. Standard headers (same as test_cookie_flow)',
                headers: {
                    'Cookie': cookie3,
                    'Referer': iframeUrl,
                    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8'
                }
            },
            {
                name: '2. With Accept: image/webp',
                headers: {
                    'Cookie': cookie3,
                    'Referer': iframeUrl,
                    'Accept': 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'
                }
            },
            {
                name: '3. With Sec-Fetch headers',
                headers: {
                    'Cookie': cookie3,
                    'Referer': iframeUrl,
                    'Accept': 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'sec-ch-ua': '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                    'sec-ch-ua-mobile': '?0',
                    'sec-ch-ua-platform': '"macOS"',
                    'sec-fetch-dest': 'image',
                    'sec-fetch-mode': 'no-cors',
                    'sec-fetch-site': 'same-origin'
                }
            },
            {
                name: '4. No Referer',
                headers: {
                    'Cookie': cookie3,
                    'Accept': 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'
                }
            },
            {
                name: '5. No Cookie',
                headers: {
                    'Referer': iframeUrl,
                    'Accept': 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'
                }
            },
            {
                name: '6. Referer set to Detail page instead of Iframe',
                headers: {
                    'Cookie': cookie3,
                    'Referer': detailUrl,
                    'Accept': 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'
                }
            }
        ];

        for (const test of tests) {
            console.log(`\nTesting: ${test.name}`);
            const res = await makeRequest(imgUrl, test.headers);
            console.log(`Status: ${res.statusCode}`);
            console.log(`Content-Type: ${res.headers['content-type']}`);
            console.log(`Length: ${res.body.length}`);
            if (res.statusCode !== 200 || res.body.length < 1000) {
                console.log(`Body: ${res.body.toString('utf8').substring(0, 100)}`);
            } else {
                console.log(`SUCCESS! Received image data of length ${res.body.length}`);
            }
        }
        
    } catch (e) {
        console.error(e);
    }
}
run();
