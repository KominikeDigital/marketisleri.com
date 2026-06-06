const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

function inspectFile(filename) {
    console.log(`\n--- Inspecting ${filename} ---`);
    const html = fs.readFileSync(path.join(__dirname, filename), 'utf8');
    const $ = cheerio.load(html);
    
    // Print all img tags with their src and alt
    $('img').each((i, el) => {
        const src = $(el).attr('src') || '';
        const alt = $(el).attr('alt') || '';
        if (src.includes('uploads') || src.includes('Assets') || alt) {
            console.log(`  Img ${i+1}: src="${src}", alt="${alt}"`);
        }
    });
    
    // Print all anchor tags with their href and text
    $('a').each((i, el) => {
        const href = $(el).attr('href') || '';
        const text = $(el).text().trim().replace(/\s+/g, ' ');
        if (href.includes('uploads') || href.includes('pdf') || href.includes('firsat') || text) {
            console.log(`  Link ${i+1}: href="${href}", text="${text}"`);
        }
    });
}

inspectFile('sok_weekly.html');
inspectFile('sok_weekend.html');
