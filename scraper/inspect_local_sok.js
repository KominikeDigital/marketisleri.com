const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

function inspectLocalFile(filename) {
    console.log(`\n========================================`);
    console.log(`Inspecting Local File: ${filename}`);
    console.log(`========================================`);
    
    const filePath = path.join(__dirname, filename);
    if (!fs.existsSync(filePath)) {
        console.log(`File does not exist: ${filePath}`);
        return;
    }
    
    const html = fs.readFileSync(filePath, 'utf8');
    console.log(`File size: ${(html.length / 1024 / 1024).toFixed(2)} MB`);
    
    const $ = cheerio.load(html);
    console.log(`Title: ${$('title').text().trim()}`);
    
    // Find all links containing "pdf"
    console.log('\n--- Links containing "pdf" ---');
    $('a').each((i, el) => {
        const href = $(el).attr('href') || '';
        const text = $(el).text().trim().replace(/\s+/g, ' ');
        if (href.toLowerCase().includes('.pdf') || text.toLowerCase().includes('pdf')) {
            console.log(`[Link] href: "${href}", text: "${text}"`);
        }
    });

    // Find all iframes
    console.log('\n--- Iframes ---');
    $('iframe').each((i, el) => {
        console.log(`[Iframe] src: "${$(el).attr('src')}"`);
    });

    // Find all embed / object tags
    console.log('\n--- Embed / Object tags ---');
    $('embed, object').each((i, el) => {
        console.log(`[${el.name}] src/data: "${$(el).attr('src') || $(el).attr('data') || $(el).attr('value')}"`);
    });

    // Find any scripts that might have a PDF URL or look like PDF viewer config
    console.log('\n--- Script contents search ---');
    $('script').each((i, el) => {
        const scriptContent = $(el).html() || '';
        if (scriptContent.includes('.pdf') || scriptContent.includes('pdfPath') || scriptContent.includes('PDF')) {
            console.log(`[Script ${i}] matches. Preview (100 chars):`, scriptContent.trim().substring(0, 150).replace(/\s+/g, ' '));
            // Let's print out lines containing .pdf
            const lines = scriptContent.split('\n');
            lines.forEach((line, lineNo) => {
                if (line.includes('.pdf') || line.includes('pdf') || line.includes('/uploads/')) {
                    console.log(`   Line ${lineNo}: ${line.trim()}`);
                }
            });
        }
    });

    // Find images in uploads
    console.log('\n--- Images containing "/uploads/" ---');
    let imgCount = 0;
    $('img').each((i, el) => {
        const src = $(el).attr('src') || '';
        const alt = $(el).attr('alt') || '';
        if (src.includes('/uploads/')) {
            console.log(`[Image] src: "${src}", alt: "${alt}"`);
            imgCount++;
        }
    });
    console.log(`Total uploads images: ${imgCount}`);
}

inspectLocalFile('sok_wednesday_details.html');
inspectLocalFile('sok_weekend_details.html');
