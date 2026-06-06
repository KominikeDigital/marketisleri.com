const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'sok_firsat.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- Inspecting ŞOK Haftanın Fırsatları Page ---');
console.log('Title:', $('title').text().trim());

// Print all links in sok_firsat.html
console.log('\nAll links containing "brosur", "katalog", "afis", "firsat", "pdf":');
$('a').each((i, el) => {
    const href = $(el).attr('href') || '';
    const text = $(el).text().trim().replace(/\s+/g, ' ');
    if (href.includes('brosur') || href.includes('katalog') || href.includes('afis') || href.includes('firsat') || href.includes('pdf')) {
        console.log(`- href: "${href}", text: "${text}"`);
    }
});

// Let's print out all images in the HTML that might represent brochure pages
console.log('\nImages (excluding product-thumb and badge-icon):');
$('img').each((i, el) => {
    const src = $(el).attr('src') || '';
    const alt = $(el).attr('alt') || '';
    if (!src.includes('product-thumb') && !src.includes('badge-icon') && !src.includes('logo')) {
        console.log(`- src: "${src}", alt: "${alt}"`);
    }
});

// Let's print the first 3000 chars of the page content to understand the layout
const text = $('body').text().trim().replace(/\s+/g, ' ');
console.log('\nPage text preview (first 1000 chars):');
console.log(text.substring(0, 1000));
