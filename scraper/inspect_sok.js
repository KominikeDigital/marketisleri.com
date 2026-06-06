const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'sok.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- Inspecting ŞOK html ---');
console.log('Title:', $('title').text());

// Check for __NEXT_DATA__ script tag
const nextDataScript = $('#__NEXT_DATA__').html();
if (nextDataScript) {
    console.log('__NEXT_DATA__ found! Length:', nextDataScript.length);
    try {
        const parsed = JSON.parse(nextDataScript);
        fs.writeFileSync(path.join(__dirname, 'sok_next_data.json'), JSON.stringify(parsed, null, 2));
        console.log('Saved __NEXT_DATA__ JSON to scraper/sok_next_data.json');
        
        // Let's inspect keys in props
        if (parsed.props) {
            console.log('Props keys:', Object.keys(parsed.props));
            if (parsed.props.pageProps) {
                console.log('pageProps keys:', Object.keys(parsed.props.pageProps));
                // If there's an interesting key, print it
                const pp = parsed.props.pageProps;
                if (pp.campaigns) {
                    console.log('Campaigns found! Count:', pp.campaigns.length);
                }
                if (pp.data) {
                    console.log('pageProps.data keys:', Object.keys(pp.data));
                }
            }
        }
    } catch (e) {
        console.error('Failed to parse __NEXT_DATA__:', e.message);
    }
} else {
    console.log('No __NEXT_DATA__ script found.');
}

// Print some images
console.log('\nFirst 20 images:');
let count = 0;
$('img').each((i, el) => {
    if (count < 20) {
        console.log(`- src: ${$(el).attr('src')}, alt: "${$(el).attr('alt')}"`);
        count++;
    }
});

// Print links containing "haftanin-firsatlari" or "kampanya"
console.log('\nLinks containing "firsat" or "kampanya" or "brosur":');
$('a').each((i, el) => {
    const href = $(el).attr('href') || '';
    const text = $(el).text().trim();
    if (href.includes('firsat') || href.includes('kampanya') || href.includes('brosur')) {
        console.log(`- href: ${href}, text: "${text}"`);
    }
});
