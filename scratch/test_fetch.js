const https = require('https');
const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

function fetchPage(url) {
    return new Promise((resolve, reject) => {
        const parsedUrl = new URL(url);
        const options = {
            hostname: parsedUrl.hostname,
            path: parsedUrl.pathname + parsedUrl.search,
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language': 'tr-TR,tr;q=0.8,en-US;q=0.5,en;q=0.3'
            }
        };
        https.get(options, (res) => {
            // Handle redirects if any
            if (res.statusCode === 301 || res.statusCode === 302) {
                console.log(`Redirecting to ${res.headers.location}`);
                return fetchPage(res.headers.location).then(resolve).catch(reject);
            }
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => { resolve(data); });
        }).on('error', (err) => { reject(err); });
    });
}

async function run() {
    console.log('--- Fetching BİM ---');
    try {
        const bimHtml = await fetchPage('https://www.bim.com.tr/Categories/680/afisler.aspx');
        const $ = cheerio.load(bimHtml);
        console.log('Bim Brochure Links:');
        $('a[href*="Bim_AfisKey"]').each((i, el) => {
            console.log(`- Link: ${$(el).attr('href')}, Text: "${$(el).text().trim()}"`);
        });
    } catch (e) {
        console.error('Bim error:', e.message);
    }

    console.log('\n--- Fetching A101 ---');
    try {
        const a101Html = await fetchPage('https://www.a101.com.tr/aldin-aldin/');
        fs.writeFileSync(path.join(__dirname, 'a101.html'), a101Html);
        console.log('A101 HTML written to scratch/a101.html (length:', a101Html.length, ')');
        const $ = cheerio.load(a101Html);
        // Let's print some interesting links or tags
        console.log('A101 titles/links:');
        $('a').each((i, el) => {
            const href = $(el).attr('href') || '';
            const text = $(el).text().trim();
            if (href.includes('brosur') || href.includes('katalog') || href.includes('aldin-aldin') || text.includes('Aldın Aldın')) {
                if (text || href) console.log(`- Link: ${href}, Text: "${text}"`);
            }
        });
    } catch (e) {
        console.error('A101 error:', e.message);
    }

    console.log('\n--- Fetching ŞOK ---');
    try {
        const sokHtml = await fetchPage('https://sokmarket.com.tr/');
        fs.writeFileSync(path.join(__dirname, 'sok.html'), sokHtml);
        console.log('ŞOK HTML written to scratch/sok.html (length:', sokHtml.length, ')');
        const $ = cheerio.load(sokHtml);
        console.log('ŞOK links:');
        $('a').each((i, el) => {
            const href = $(el).attr('href') || '';
            const text = $(el).text().trim();
            if (href.includes('firsat') || href.includes('brosur') || href.includes('katalog') || text.includes('Fırsat')) {
                if (text || href) console.log(`- Link: ${href}, Text: "${text}"`);
            }
        });
    } catch (e) {
        console.error('ŞOK error:', e.message);
    }
}

run();
