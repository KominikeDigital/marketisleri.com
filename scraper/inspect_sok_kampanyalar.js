const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'sok_kampanyalar.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- Inspecting all images in ŞOK Kampanyalar page ---');
$('img').each((i, el) => {
    const src = $(el).attr('src') || '';
    const alt = $(el).attr('alt') || '';
    console.log(`Image ${i+1}: src="${src}", alt="${alt}"`);
});

console.log('\n--- Inspecting all links on page ---');
$('a').each((i, el) => {
    const href = $(el).attr('href') || '';
    const text = $(el).text().trim().replace(/\s+/g, ' ');
    if (href.includes('afis') || href.includes('brosur') || href.includes('katalog') || href.includes('kampanya') || href.includes('firsat')) {
        console.log(`Link: href="${href}", text="${text}"`);
    }
});
