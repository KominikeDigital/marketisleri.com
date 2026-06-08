const https = require('https');
const fs = require('fs');
const path = require('path');
const cheerio = require('../scraper/node_modules/cheerio');

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
        // Step 1: Fetch Metro main brochures list
        console.log('Step 1: Requesting main catalog list...');
        const listUrl = 'https://aktuelbrosurler.com/metrotoptancimarket/brosurler';
        const r1 = await fetchPage(listUrl);
        
        const $ = cheerio.load(r1.body.toString('utf8'));
        const cards = $('a.brosur-link');
        console.log(`Found ${cards.length} brochures on list page.`);
        if (cards.length === 0) {
            console.log('No brochure links found.');
            return;
        }
        
        // Let's get the first brochure URL
        let firstHref = cards.first().attr('href');
        if (!firstHref.startsWith('http')) {
            firstHref = new URL(firstHref, listUrl).toString();
        }
        console.log('First brochure detail URL:', firstHref);
        
        // Step 2: Request the active brochure detail page
        console.log('\nStep 2: Requesting active brochure detail page...');
        const r2 = await fetchPage(firstHref, listUrl, r1.cookies);
        
        // Extract the iframe reader URL
        const detailHtml = r2.body.toString('utf8');
        const iframeMatch = detailHtml.match(/brosur\.aspx\?id=([a-f0-9]+)/i);
        if (!iframeMatch) {
            console.log('No iframe reader URL found in detail page.');
            return;
        }
        
        const iframeId = iframeMatch[1];
        const iframeUrl = `https://aktuelbrosurler.com/brosur.aspx?id=${iframeId}`;
        console.log('Found iframe URL:', iframeUrl);
        
        // Step 3: Request iframe page
        console.log('\nStep 3: Requesting iframe page...');
        const r3 = await fetchPage(iframeUrl, firstHref, r2.cookies);
        
        const iframeHtml = r3.body.toString('utf8');
        console.log('Iframe HTML length:', iframeHtml.length);
        
        // Extract page URLs from iframe HTML
        const pageRegex = /'l':\s*'([^']+)'/g;
        const pageUrls = [];
        let match;
        while ((match = pageRegex.exec(iframeHtml)) !== null) {
            let imgUrl = match[1].replace(/\\u0026/g, '&');
            if (imgUrl && !pageUrls.includes(imgUrl)) {
                pageUrls.push(imgUrl);
            }
        }
        
        console.log(`Found ${pageUrls.length} image URLs in iframe.`);
        if (pageUrls.length === 0) {
            console.log('No image URLs found in iframe.');
            return;
        }
        
        const targetImgUrl = pageUrls[0];
        console.log('\nStep 4: Requesting first image page:', targetImgUrl);
        const r4 = await fetchPage(targetImgUrl, iframeUrl, r3.cookies);
        
        console.log('Image Response Status:', r4.statusCode);
        console.log('Image Content-Type:', r4.headers['content-type']);
        console.log('Image Body Length:', r4.body.length);
        
        if (r4.statusCode === 200 && r4.body.length > 1000) {
            const ext = targetImgUrl.includes('resim=kapak.webp') ? 'webp' : 'webp'; // usually webp
            const savePath = `scratch/test_active_page.${ext}`;
            fs.writeFileSync(savePath, r4.body);
            console.log(`SUCCESS: Saved image to ${savePath}`);
        } else {
            console.log('Failed body:', r4.body.toString('utf8'));
        }
        
    } catch (e) {
        console.error('Error:', e);
    }
}
run();
