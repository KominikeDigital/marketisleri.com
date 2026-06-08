const sqlite3 = require('../scraper/node_modules/sqlite3').verbose();
const path = require('path');

const dbFile = path.join(__dirname, '../database.db');
const db = new sqlite3.Database(dbFile);

console.log('Verifying SQLite Database contents...');

console.log('Current Date (Local PHP-equivalent):', new Date().toISOString().split('T')[0]);

db.all(`
    SELECT b.id, m.name as market_name, b.title, b.start_date, b.end_date,
           (SELECT COUNT(*) FROM brochure_pages WHERE brochure_id = b.id) as page_count
    FROM brochures b
    LEFT JOIN markets m ON b.market_id = m.id
    ORDER BY b.id DESC
    LIMIT 15
`, [], (err, rows) => {
    if (err) {
        console.error('Error executing query:', err.message);
        db.close();
        return;
    }
    
    console.log('\n--- LATEST 15 BROCHURES IN DATABASE ---');
    console.table(rows);
    
    db.close();
});
