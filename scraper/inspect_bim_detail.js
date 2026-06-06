const https = require('https');
const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

function fetchPage(url) {
    return new Promise((resolve, reject) => {
        https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            }
        }, (res) => {
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => { resolve(data); });
        }).on('error', (err) => { reject(err); });
    });
}

async function run() {
    const url = 'https://www.bim.com.tr/Categories/680/afisler.aspx?top=1&Bim_AfisKey=1265';
    try {
        const html = await fetchPage(url);
        fs.writeFileSync(path.join(__dirname, 'bim_detail_1265.html'), html);
        console.log('Fetched BİM detail page. Length:', html.length);
        
        const $ = cheerio.load(html);
        
        // Find all elements with data-bigimg
        console.log('\nAll elements with data-bigimg:');
        $('[data-bigimg]').each((i, el) => {
            const bigImg = $(el).attr('data-bigimg');
            const parentClass = $(el).parent().attr('class') || '';
            const parentTagName = $(el).parent()[0].name;
            console.log(`- Tag: <${el.name}>, parent: <${parentTagName} class="${parentClass}">, data-bigimg: "${bigImg}"`);
        });
        
        // Find if there is a wrapper for the current brochure images
        // Let's check some common classes/ids
        console.log('\nPossible containers/sections:');
        $('div').each((i, el) => {
            const cls = $(el).attr('class') || '';
            const id = $(el).attr('id') || '';
            if (cls.includes('slider') || cls.includes('gallery') || cls.includes('content') || cls.includes('container') || id.includes('slider') || id.includes('gallery')) {
                const imgCount = $(el).find('[data-bigimg]').length;
                if (imgCount > 0) {
                    console.log(`- Container <div class="${cls}" id="${id}"> contains ${imgCount} data-bigimg elements.`);
                }
            }
        });
    } catch (e) {
        console.error('Error fetching BİM detail:', e.message);
    }
}

run();
