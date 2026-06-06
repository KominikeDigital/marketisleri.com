const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'a101.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- List of All Links in A101 html ---');
$('a').each((i, el) => {
    const href = $(el).attr('href') || '';
    const text = $(el).text().trim().replace(/\s+/g, ' ');
    const classes = $(el).attr('class') || '';
    if (href) {
        console.log(`- href: "${href}", text: "${text}", class: "${classes}"`);
    }
});
