const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'a101.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- Inspecting A101 html ---');
console.log('Title:', $('title').text());

const nextDataScript = $('#__NEXT_DATA__').html();
if (nextDataScript) {
    console.log('__NEXT_DATA__ found! Length:', nextDataScript.length);
    try {
        const parsed = JSON.parse(nextDataScript);
        fs.writeFileSync(path.join(__dirname, 'a101_next_data.json'), JSON.stringify(parsed, null, 2));
        console.log('Saved __NEXT_DATA__ JSON to scraper/a101_next_data.json');
        
        if (parsed.props && parsed.props.pageProps) {
            const pp = parsed.props.pageProps;
            console.log('pageProps keys:', Object.keys(pp));
            // Let's print out some properties to find brochures
            if (pp.campaign) {
                console.log('Campaign field found!');
            }
            // Check for common data structures
            for (const key of Object.keys(pp)) {
                if (typeof pp[key] === 'object' && pp[key] !== null) {
                    console.log(`- prop [${key}]:`, Object.keys(pp[key]));
                }
            }
        }
    } catch (e) {
        console.error('Failed to parse __NEXT_DATA__:', e.message);
    }
} else {
    console.log('No __NEXT_DATA__ script found.');
}

console.log('\nAll image tags on A101 page:');
$('img').each((i, el) => {
    console.log(`- src: ${$(el).attr('src')}, data-src: ${$(el).attr('data-src') || ''}, alt: "${$(el).attr('alt') || ''}"`);
});
