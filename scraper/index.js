const bim = require('./bim');
const a101 = require('./a101');
const sok = require('./sok');
const generic = require('./generic');

async function main() {
    console.log('====================================================');
    console.log(`⏰ [Scraper] Çalışma zamanı: ${new Date().toLocaleString('tr-TR')}`);
    console.log('====================================================');
    
    try {
        // Run BİM Scraper
        console.log('\n--- BİM SCRAPER ---');
        await bim.scrapeBim();
        
        // Run A101 Scraper
        console.log('\n--- A101 SCRAPER ---');
        await a101.scrapeA101();
        
        // Run ŞOK Scraper
        console.log('\n--- ŞOK SCRAPER ---');
        await sok.scrapeSok();
        
        // Run Dynamic Generic Scraper
        console.log('\n--- DİNAMİK GENEL SCRAPER ---');
        await generic.scrapeGenericActiveMarkets();
        
        console.log('\n✅ [Scraper] Tüm market tarama görevleri tamamlandı.');
        process.exit(0);
    } catch (e) {
        console.error('\n❌ [Scraper] Beklenmeyen bir hata oluştu:', e.stack || e.message);
        process.exit(1);
    }
}

main();

