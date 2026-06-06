const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'sok.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- List of all links in ŞOK homepage ---');
$('a').each((i, el) => {
    const href = $(el).attr('href') || '';
    const text = $(el).text().trim().replace(/\s+/g, ' ');
    if (href) {
        console.log(`- href: "${href}", text: "${text}"`);
    }
});
