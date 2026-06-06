const fs = require('fs');
const path = require('path');

const html = fs.readFileSync(path.join(__dirname, 'sok.html'), 'utf8');

console.log('--- Searching for images.ceptesok.com in sok.html raw source ---');
const regex = /https:\/\/images\.ceptesok\.com\/[^\s"']+/g;
const matches = html.match(regex) || [];

console.log('Total matches found:', matches.length);

const unique = Array.from(new Set(matches));
console.log('Unique matches count:', unique.length);

console.log('\nFirst 50 unique URLs:');
unique.slice(0, 50).forEach((url, i) => {
    console.log(`${i+1}: ${url}`);
});
