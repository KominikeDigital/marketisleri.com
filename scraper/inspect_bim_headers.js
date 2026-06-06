const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const html = fs.readFileSync(path.join(__dirname, 'bim_detail_1265.html'), 'utf8');
const $ = cheerio.load(html);

console.log('--- Inspecting BİM Detail Headers & Containers ---');

// Let's print out the titles or headers in the page
console.log('\nPage Headers (h1, h2, h3, h4):');
$('h1, h2, h3, h4').each((i, el) => {
    console.log(`- <${el.name}>: "${$(el).text().trim()}"`);
});

// Let's print out all divs that have class containing "title" or "header" or "menu" or "sidebar"
console.log('\nDivs with title/header/menu/sidebar:');
$('div').each((i, el) => {
    const cls = $(el).attr('class') || '';
    const id = $(el).attr('id') || '';
    if (cls.includes('title') || cls.includes('header') || cls.includes('menu') || cls.includes('sidebar') || cls.includes('sub')) {
        console.log(`- <div class="${cls}" id="${id}">: "${$(el).text().trim().substring(0, 100).replace(/\s+/g, ' ')}"`);
    }
});

// Let's look at the elements surrounding the .smallArea elements
console.log('\nParent container of .smallArea:');
const parents = new Set();
$('.smallArea').each((i, el) => {
    const p = $(el).parent();
    const cls = p.attr('class') || '';
    const id = p.attr('id') || '';
    parents.add(`<${p[0].name} class="${cls}" id="${id}">`);
});
console.log(Array.from(parents));
