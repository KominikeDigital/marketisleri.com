const https = require('https');

// Helper to make HTTPS requests using Node's built-in https module (no dependencies needed!)
function fetchPage(url) {
    return new Promise((resolve, reject) => {
        https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            }
        }, (res) => {
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => { resolve(data); });
        }).on('error', (err) => { reject(err); });
    });
}

// Helper to parse Turkish dates into MySQL date format YYYY-MM-DD
function parseTurkishDate(titleText) {
    const months = {
        'ocak': '01', 'şubat': '02', 'mart': '03', 'nisan': '04',
        'mayıs': '05', 'haziran': '06', 'temmuz': '07', 'ağustos': '08',
        'eylül': '09', 'ekim': '10', 'kasım': '11', 'aralık': '12'
    };
    
    // Example: "02 Haziran Salı" or "03-29 Haziran"
    const cleaned = titleText.toLowerCase().trim();
    const parts = cleaned.split(/\s+/);
    
    let day = '01';
    let month = '06';
    let year = new Date().getFullYear(); // Use current year

    if (parts.length >= 2) {
        // Check if day is a range like "03-29" -> pick the start day "03"
        const dayPart = parts[0];
        if (dayPart.includes('-')) {
            day = dayPart.split('-')[0].padStart(2, '0');
        } else {
            day = dayPart.padStart(2, '0');
        }
        
        const monthName = parts[1];
        if (months[monthName]) {
            month = months[monthName];
        }
    }
    
    return `${year}-${month}-${day}`;
}

async function runTestScraper() {
    console.log('=== BİM BROŞÜR KAZIMA PROTOTİPİ BAŞLADI ===');
    console.log('1. BİM Afişler ana sayfası yükleniyor...');
    
    const mainUrl = 'https://www.bim.com.tr/Categories/680/afisler.aspx';
    try {
        const html = await fetchPage(mainUrl);
        console.log('✓ Ana sayfa başarıyla yüklendi.');
        
        // Find brochure keys and titles using regex
        // Example match: <a href="/Categories/680/afisler.aspx?top=1&Bim_AfisKey=1262">09 Haziran Salı</a>
        const linkRegex = /\/Categories\/680\/afisler\.aspx\?top=1&amp;Bim_AfisKey=(\d+)">([^<]+)<\/a>/g;
        const matches = [];
        let match;
        
        // Fallback for different HTML escaping (& vs &amp;)
        const cleanHtml = html.replace(/&amp;/g, '&');
        const searchRegex = /\/Categories\/680\/afisler\.aspx\?top=1&Bim_AfisKey=(\d+)">([^<]+)<\/a>/g;
        
        while ((match = searchRegex.exec(cleanHtml)) !== null) {
            matches.push({
                key: match[1],
                title: match[2].trim()
            });
        }
        
        if (matches.length === 0) {
            console.log('⚠️ Sayfada broşür linki bulunamadı. Yapı değişmiş olabilir.');
            return;
        }
        
        console.log(`✓ Toplam ${matches.length} adet broşür bağlantısı bulundu:`);
        matches.slice(0, 5).forEach((m, i) => {
            console.log(`  [${i + 1}] Başlık: "${m.title}" | Key (ID): ${m.key} | Tahmini Başlangıç Tarihi: ${parseTurkishDate(m.title)}`);
        });
        
        // Pick the first brochure to simulate detail scraping
        const target = matches[0];
        console.log(`\n2. En güncel broşürün detayları indiriliyor (Key: ${target.key})...`);
        
        const detailUrl = `https://www.bim.com.tr/Categories/680/afisler.aspx?top=1&Bim_AfisKey=${target.key}`;
        const detailHtml = await fetchPage(detailUrl);
        console.log('✓ Detay sayfası yüklendi. Görseller ayıklanıyor...');
        
        // Extract data-bigimg links
        // Example: data-bigimg="https://cdn1.bim.com.tr/uploads/afisler/36f2b42e-763f-438e-90c1-5d7f058544ae.jpg"
        const imgRegex = /data-bigimg="([^"]+)"/g;
        const images = [];
        let imgMatch;
        
        while ((imgMatch = imgRegex.exec(detailHtml)) !== null) {
            const url = imgMatch[1];
            if (!images.includes(url)) {
                images.push(url);
            }
        }
        
        console.log(`✓ Broşür için ${images.length} adet tam boy sayfa görseli tespit edildi:`);
        images.forEach((img, idx) => {
            console.log(`  [Sayfa ${idx + 1}] -> ${img}`);
        });
        
        console.log('\n3. Kazıma Simulasyonu Sonucu:');
        console.log('-------------------------------------------');
        console.log(`Market Adı     : BİM`);
        console.log(`Broşür Başlığı : ${target.title} Aktüel Kataloğu`);
        console.log(`Başlangıç Tar. : ${parseTurkishDate(target.title)}`);
        console.log(`Bitiş Tarihi   : ${new Date(new Date(parseTurkishDate(target.title)).getTime() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]} (7 gün sonra)`);
        console.log(`Kapak Görseli  : ${images[0] || 'Bulunamadı'}`);
        console.log(`Sayfa Sayısı   : ${images.length}`);
        console.log('-------------------------------------------');
        console.log('✓ Simülasyon başarıyla tamamlandı. Node.js ve cPanel bu akışı arka planda tam otomatik yapmaya uygundur.');
        
    } catch (err) {
        console.error('❌ Hata oluştu:', err.message);
    }
}

runTestScraper();
