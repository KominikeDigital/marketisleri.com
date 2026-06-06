const fs = require('fs');
const path = require('path');

const html = fs.readFileSync(path.join(__dirname, 'a101.html'), 'utf8');

console.log('--- Searching A101 html ---');
console.log('HTML length:', html.length);

const keywords = ['brosur', 'börüşür', 'katalog', 'afis', 'resim', 'pdf', 'image', 'jpg', 'png', 'webp', 'aldin', 'aldın', 'kampanya', 'ekstra'];
keywords.forEach(kw => {
    const regex = new RegExp(kw, 'gi');
    const matches = html.match(regex);
    console.log(`Keyword "${kw}": ${matches ? matches.length : 0} matches`);
});

// Let's print out all script tags and their contents (first 200 chars of each)
const cheerio = require('cheerio');
const $ = cheerio.load(html);
console.log('\nScript tags:');
$('script').each((i, el) => {
    const src = $(el).attr('src') || '';
    const content = $(el).html() || '';
    console.log(`Script ${i}: src="${src}", content length=${content.length}`);
    if (content.length > 0) {
        console.log(`  Content preview: ${content.substring(0, 150).replace(/\s+/g, ' ')}...`);
    }
});

// Let's look at all div classes
console.log('\nDiv classes:');
const classes = new Set();
$('div').each((i, el) => {
    const cls = $(el).attr('class');
    if (cls) cls.split(/\s+/).forEach(c => classes.add(c));
});
console.log('Total unique classes:', classes.size);
const arr = Array.from(classes);
console.log('Sample classes:', arr.slice(0, 30));
