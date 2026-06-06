const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const months = ['ocak', 'şubat', 'mart', 'nisan', 'mayıs', 'haziran', 'temmuz', 'ağustos', 'eylül', 'ekim', 'kasım', 'aralık'];

function searchDates(filename) {
    console.log(`\n--- Searching dates in ${filename} ---`);
    const html = fs.readFileSync(path.join(__dirname, filename), 'utf8');
    const $ = cheerio.load(html);
    
    // Get all text content
    const text = $('body').text().toLowerCase();
    
    // Find any mention of months
    months.forEach(m => {
        if (text.includes(m)) {
            console.log(`Found month: "${m}"`);
            // Find surrounding text (100 chars before and after)
            const index = text.indexOf(m);
            const start = Math.max(0, index - 50);
            const end = Math.min(text.length, index + m.length + 50);
            console.log(`  Context: "${text.substring(start, end).replace(/\s+/g, ' ')}"`);
        }
    });

    // Let's print all paragraph and span text
    $('p, span, div').each((i, el) => {
        const txt = $(el).text().trim().replace(/\s+/g, ' ');
        if (txt.match(/\d+/) && txt.length < 200) {
            // Check if it looks like a date range
            if (txt.includes('-') || txt.includes('/') || months.some(m => txt.toLowerCase().includes(m))) {
                console.log(`  Matching tag <${el.name}>: "${txt}"`);
            }
        }
    });
}

searchDates('sok_weekly.html');
searchDates('sok_weekend.html');
