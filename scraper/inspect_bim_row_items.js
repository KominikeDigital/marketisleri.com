const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'bim_detail_1265.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- Inspecting BİM .row.item Elements ---');

const items = $('.row.item');
console.log(`Total .row.item elements: ${items.length}`);

items.each((index, el) => {
    console.log(`\n--- Item ${index + 1} ---`);
    
    // Check classes or ids
    console.log(`Class: "${$(el).attr('class')}", Id: "${$(el).attr('id') || ''}"`);
    
    // Look for any headers or titles inside or immediately preceding this item
    const prevH3 = $(el).prevAll('h1, h2, h3, h4, h5, h6').first().text().trim();
    const prevText = $(el).prev().text().trim().replace(/\s+/g, ' ');
    console.log(`Previous heading: "${prevH3}"`);
    console.log(`Directly preceding element text: "${prevText.substring(0, 100)}"`);
    
    // Count images inside
    const images = $(el).find('[data-bigimg]');
    console.log(`Number of data-bigimg images inside: ${images.length}`);
    if (images.length > 0) {
        console.log('Sample image URLs:');
        images.slice(0, 3).each((i, img) => {
            console.log(`  - ${$(img).attr('data-bigimg')}`);
        });
    }
});
