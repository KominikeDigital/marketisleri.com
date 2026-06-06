const bim = require('./bim');

async function main() {
    console.log('====================================================');
    console.log(`⏰ [Scraper] Çalışma zamanı: ${new Date().toLocaleString('tr-TR')}`);
    console.log('====================================================');
    
    try {
        // Run BİM Scraper
        await bim.scrapeBim();
        
        // In the future, you can add other scrapers here:
        // await a101.scrapeA101();
        // await sok.scrapeSok();
        
        console.log('✅ [Scraper] Tüm market tarama görevleri tamamlandı.');
        process.exit(0);
    } catch (e) {
        console.error('❌ [Scraper] Beklenmeyen bir hata oluştu:', e.message);
        process.exit(1);
    }
}

main();
