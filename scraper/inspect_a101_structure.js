const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'a101.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- Inspecting A101 Structure ---');

$('img').each((i, el) => {
    const src = $(el).attr('src') || '';
    if (src.includes('cdn2.a101.com.tr')) {
        console.log('\n--- Image found:', src);
        
        // Print parents up to 4 levels
        let parent = $(el).parent();
        let depth = 1;
        while (parent.length && depth <= 4) {
            console.log(`Parent level ${depth}: Tag: <${parent[0].name}>, Class: "${parent.attr('class') || ''}", ID: "${parent.attr('id') || ''}"`);
            // Check if there is text inside the parent
            const text = parent.text().trim().replace(/\s+/g, ' ');
            if (text && text.length < 300) {
                console.log(`  -> Text inside: "${text}"`);
            }
            parent = parent.parent();
            depth++;
        }
    }
});
