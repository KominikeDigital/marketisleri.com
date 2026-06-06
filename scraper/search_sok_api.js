const fs = require('fs');
const path = require('path');

const html = fs.readFileSync(path.join(__dirname, 'sok.html'), 'utf8');

console.log('--- Searching for API endpoints in sok.html ---');
const regex = /https?:\/\/[a-z0-9.-]*api[a-z0-9.-]*/gi;
const matches = html.match(regex) || [];
console.log('Matches:', Array.from(new Set(matches)));

// Search for any .json or api endpoints in the scripts
const cheerio = require('cheerio');
const $ = cheerio.load(html);

$('script').each((i, el) => {
    const src = $(el).attr('src') || '';
    const content = $(el).html() || '';
    if (content.includes('api/') || content.includes('/api') || content.includes('ceptesok.com')) {
        console.log(`Script ${i} matches:`);
        // Find strings that look like API endpoints
        const strRegex = /"([^"]*\/api\/[^"]*)"|'([^']*\/api\/[^']*)'/g;
        let m;
        while ((m = strRegex.exec(content)) !== null) {
            console.log(`  - ${m[1] || m[2]}`);
        }
    }
});
