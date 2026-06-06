const https = require('https');

function checkHeaders(url) {
    console.log(`\nChecking: ${url}`);
    const parsedUrl = new URL(url);
    const options = {
        hostname: parsedUrl.hostname,
        path: parsedUrl.pathname + parsedUrl.search,
        method: 'GET',
        headers: {
            'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        }
    };
    
    https.get(options, (res) => {
        console.log(`Status: ${res.statusCode}`);
        console.log('Headers:');
        for (const [key, val] of Object.entries(res.headers)) {
            console.log(`  ${key}: ${val}`);
        }
        res.destroy(); // close connection
    }).on('error', (e) => {
        console.error('Error:', e.message);
    });
}

checkHeaders('https://kurumsal.sokmarket.com.tr/firsatlar/carsamba/');
checkHeaders('https://kurumsal.sokmarket.com.tr/firsatlar/hafta-sonu/');
