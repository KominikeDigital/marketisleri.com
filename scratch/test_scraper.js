const https = require('https');
const fs = require('fs');
const path = require('path');

const listUrl = 'https://aktuelbrosurler.com/metrotoptancimarket/brosurler';

function makeRequest(url, headers = {}) {
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const options = {
            hostname: parsedUrl.hostname,
            path: parsedUrl.pathname + parsedUrl.search,
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                ...headers
            }
        };
        https.get(options, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ statusCode: res.statusCode, headers: res.headers, data }));
        }).on('error', reject);
    });
}

async function run() {
    try {
        // Step 1: Request list page
        console.log('Step 1: Fetching list page...');
        const r1 = await makeRequest(listUrl);
        const cookies = r1.headers['set-cookie'] || [];
        const cookieHeader = cookies.map(c => c.split(';')[0]).join('; ');
        console.log('Cookies received:', cookieHeader);
        
        // Find brochure ID
        const match = r1.data.match(/data-img='[^']+\?k=([a-f0-9]+)/);
        if (!match) {
            console.log('No brochure ID found.');
            return;
        }
        const bId = match[1];
        console.log('Found brochure ID:', bId);
        
        // Find image URL
        const imgMatch = r1.data.match(/data-img='([^']+)'/);
        const imgUrl = imgMatch[1].replace(/&amp;/g, '&');
        
        // Step 2: Request the reader page to initialize session
        const readerUrl = `https://aktuelbrosurler.com/brosur.aspx?id=${bId}`;
        console.log('Step 2: Fetching reader page to initialize session:', readerUrl);
        const r2 = await makeRequest(readerUrl, {
            'Cookie': cookieHeader,
            'Referer': listUrl
        });
        console.log('Reader status:', r2.statusCode);
        
        // If reader returned new cookies, append them
        const r2Cookies = r2.headers['set-cookie'] || [];
        const updatedCookieHeader = [cookieHeader, ...r2Cookies.map(c => c.split(';')[0])].filter(Boolean).join('; ');
        
        // Step 3: Fetch the image
        console.log('Step 3: Downloading image:', imgUrl);
        const r3 = await makeRequest(imgUrl, {
            'Accept': 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Accept-Language': 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Connection': 'keep-alive',
            'Cookie': updatedCookieHeader,
            'Referer': readerUrl
        });
        console.log('Image download status:', r3.statusCode);
        console.log('Image content-type:', r3.headers['content-type']);
        console.log('Image size:', r3.data.length);
        console.log('Image content (first 100 chars):', r3.data.substring(0, 100));
        
        if (r3.statusCode === 200 && r3.data.length > 1000) {
            fs.writeFileSync('scratch/test_metro_page.webp', r3.data, 'binary');
            console.log('SUCCESS: Saved image to scratch/test_metro_page.webp');
        }
    } catch (e) {
        console.error('Error:', e);
    }
}
run();
