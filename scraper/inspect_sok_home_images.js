const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'sok.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- Inspecting All Images in ŞOK Homepage ---');
console.log('Title:', $('title').text().trim());

$('img').each((i, el) => {
    console.log(`- src: "${$(el).attr('src')}", alt: "${$(el).attr('alt') || ''}"`);
});
