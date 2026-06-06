const sqlite3 = require('sqlite3').verbose();
const path = require('path');

const dbFile = path.join(__dirname, '../database.db');
const db = new sqlite3.Database(dbFile);

db.all("SELECT * FROM brochures", [], (err, rows) => {
    if (err) {
        console.error('Error fetching brochures:', err.message);
        return;
    }
    console.log('--- BROCHURES ---');
    console.log(rows);
    
    db.all("SELECT * FROM brochure_pages WHERE brochure_id = 5", [], (err, pages) => {
        if (err) {
            console.error('Error fetching brochure pages:', err.message);
            return;
        }
        console.log('\n--- PAGES FOR BROCHURE ID 5 ---');
        console.log(`Total pages: ${pages.length}`);
        pages.slice(0, 10).forEach(p => console.log(p));
        db.close();
    });
});
