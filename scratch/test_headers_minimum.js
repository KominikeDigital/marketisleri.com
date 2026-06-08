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
        const listUrl = 'https://aktuelbrosurler.com/metrotoptancimarket/brosurler';
        const r1 = await makeRequest(listUrl);
        const cookie1 = r1.headers['set-cookie'] ? r1.headers['set-cookie'].map(c => c.split(';')[0]).join('; ') : '';
        
        const $ = cheerio.load(r1.body.toString('utf8'));
        const firstHref = $('a.brosur-link').first().attr('href');
        const detailUrl = firstHref.startsWith('http') ? firstHref : new URL(firstHref, listUrl).toString();
        
        const r2 = await makeRequest(detailUrl, { 'Cookie': cookie1, 'Referer': listUrl });
        const cookie2 = r2.headers['set-cookie'] ? r2.headers['set-cookie'].map(c => c.split(';')[0]).join('; ') : cookie1;
        
        const detailHtml = r2.body.toString('utf8');
        const iframeMatch = detailHtml.match(/brosur\.aspx\?id=([a-f0-9]+)/i);
        if (!iframeMatch) return;
        const iframeUrl = `https://aktuelbrosurler.com/brosur.aspx?id=${iframeMatch[1]}`;
        
        const r3 = await makeRequest(iframeUrl, { 'Cookie': cookie2, 'Referer': detailUrl });
        const cookie3 = r3.headers['set-cookie'] ? r3.headers['set-cookie'].map(c => c.split(';')[0]).join('; ') : cookie2;
        
        const iframeHtml = r3.body.toString('utf8');
        const pageRegex = /'l':\s*'([^']+)'/;
        const match = pageRegex.exec(iframeHtml);
        if (!match) return;
        const imgUrl = match[1].replace(/\\u0026/g, '&');
        console.log('Target Image URL:', imgUrl);
        
        const baseHeaders = {
            'Cookie': cookie3,
            'Referer': iframeUrl,
            'Accept': 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'
        };

        const tests = [
            {
                name: 'Test A: Only sec-fetch-site: same-origin',
                headers: {
                    ...baseHeaders,
                    'sec-fetch-site': 'same-origin'
                }
            },
            {
                name: 'Test B: Only sec-fetch-dest: image',
                headers: {
                    ...baseHeaders,
                    'sec-fetch-dest': 'image'
                }
            },
            {
                name: 'Test C: Only sec-fetch-mode: no-cors',
                headers: {
                    ...baseHeaders,
                    'sec-fetch-mode': 'no-cors'
                }
            },
            {
                name: 'Test D: sec-fetch-site and sec-fetch-dest',
                headers: {
                    ...baseHeaders,
                    'sec-fetch-site': 'same-origin',
                    'sec-fetch-dest': 'image'
                }
            },
            {
                name: 'Test E: sec-fetch-site, sec-fetch-dest, sec-fetch-mode (No sec-ch-ua)',
                headers: {
                    ...baseHeaders,
                    'sec-fetch-site': 'same-origin',
                    'sec-fetch-dest': 'image',
                    'sec-fetch-mode': 'no-cors'
                }
            }
        ];

        for (const test of tests) {
            console.log(`\nTesting: ${test.name}`);
            const res = await makeRequest(imgUrl, test.headers);
            console.log(`Status: ${res.statusCode}`);
            if (res.statusCode === 200 && res.body.length > 1000) {
                console.log(`SUCCESS! Length: ${res.body.length}`);
            } else {
                console.log(`FAILED. Body: ${res.body.toString('utf8').substring(0, 100)}`);
            }
        }
        
    } catch (e) {
        console.error(e);
    }
}
run();
