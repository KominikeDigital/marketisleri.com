const https = require('https');
const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

function fetchPage(url, depth = 0) {
    if (depth > 5) {
        return Promise.reject(new Error('Too many redirects'));
    }
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const options = {
            hostname: parsedUrl.hostname,
            path: parsedUrl.pathname + parsedUrl.search,
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language': 'tr-TR,tr;q=0.8,en-US;q=0.5,en;q=0.3',
                'Referer': 'https://google.com'
            }
        };
        https.get(options, (res) => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                let redirectUrl = res.headers.location;
                if (!redirectUrl.startsWith('http')) {
                    const origin = `${parsedUrl.protocol}//${parsedUrl.host}`;
                    redirectUrl = new URL(redirectUrl, origin).toString();
                }
                console.log(`Redirecting: ${url} -> ${redirectUrl} (Status: ${res.statusCode})`);
                return fetchPage(redirectUrl, depth + 1).then(resolve).catch(reject);
            }
            
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => { resolve(data); });
        }).on('error', (err) => { reject(err); });
    });
}

async function run() {
    console.log('--- Fetching A101 ---');
    try {
        const a101Html = await fetchPage('https://www.a101.com.tr/aldin-aldin/');
        fs.writeFileSync(path.join(__dirname, 'a101.html'), a101Html);
        console.log('A101 HTML written to scraper/a101.html (length:', a101Html.length, ')');
        
        const $ = cheerio.load(a101Html);
        console.log('A101 Title:', $('title').text().trim());
        
        // Find elements that look like brochure pages or container for images
        // Usually, A101 stores images inside a container or carousel.
        // Let's print out all images with class or standard pattern
        console.log('A101 Images (first 25):');
        let count = 0;
        $('img').each((i, el) => {
            const src = $(el).attr('src') || '';
            const alt = $(el).attr('alt') || '';
            const dataSrc = $(el).attr('data-src') || '';
            if (src.includes('files/') || src.includes('campaign') || dataSrc.includes('files/')) {
                console.log(`- img src: ${src}, data-src: ${dataSrc}, alt: "${alt}"`);
                count++;
            }
        });
        if (count === 0) {
            // print any img tags
            $('img').slice(0, 15).each((i, el) => {
                console.log(`- general img src: ${$(el).attr('src')}, alt: "${$(el).attr('alt')}"`);
            });
        }
    } catch (e) {
        console.error('A101 error:', e.stack);
    }

    console.log('\n--- Fetching ŞOK campaign page ---');
    try {
        const sokHtml = await fetchPage('https://www.sokmarket.com.tr/haftanin-firsatlari-market-sgrp-146401');
        fs.writeFileSync(path.join(__dirname, 'sok_firsat.html'), sokHtml);
        console.log('ŞOK campaign HTML written to scraper/sok_firsat.html (length:', sokHtml.length, ')');
        
        const $ = cheerio.load(sokHtml);
        console.log('ŞOK Title:', $('title').text().trim());
        
        // Let's search for __NEXT_DATA__ on this page!
        const nextDataScript = $('#__NEXT_DATA__').html();
        if (nextDataScript) {
            console.log('__NEXT_DATA__ found! Length:', nextDataScript.length);
            try {
                const parsed = JSON.parse(nextDataScript);
                fs.writeFileSync(path.join(__dirname, 'sok_firsat_next_data.json'), JSON.stringify(parsed, null, 2));
                console.log('Saved __NEXT_DATA__ JSON to scraper/sok_firsat_next_data.json');
            } catch (e) {
                console.error('Failed to parse __NEXT_DATA__:', e.message);
            }
        }
        
        console.log('ŞOK Images (first 25):');
        let count = 0;
        $('img').each((i, el) => {
            const src = $(el).attr('src') || '';
            const alt = $(el).attr('alt') || '';
            console.log(`- img src: ${src}, alt: "${alt}"`);
            count++;
        });
    } catch (e) {
        console.error('ŞOK error:', e.stack);
    }
}

run();
