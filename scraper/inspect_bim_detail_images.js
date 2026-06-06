const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'bim_detail_1265.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- Detailed Analysis of BİM Images ---');

// Let's print the parent wrapper structures of data-bigimg elements
$('a[data-bigimg]').each((i, el) => {
    const bigImg = $(el).attr('data-bigimg');
    
    // Get all classes, ids, and custom data attributes of parents up to 5 levels
    console.log(`\nImage ${i+1}: ${bigImg}`);
    let parent = $(el).parent();
    let depth = 1;
    while (parent.length && depth <= 4) {
        const tagName = parent[0].name;
        const className = parent.attr('class') || '';
        const id = parent.attr('id') || '';
        // If it's a div, print if it has headers
        let headerText = '';
        if (tagName === 'div') {
            const h = parent.find('h1, h2, h3, h4, h5, h6, .title, .subTitle').first();
            if (h.length) {
                headerText = ` (Header inside: "${h.text().trim()}")`;
            }
        }
        console.log(`  Depth ${depth}: <${tagName} class="${className}" id="${id}">${headerText}`);
        parent = parent.parent();
        depth++;
    }
});
