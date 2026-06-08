const sqlite3 = require('../scraper/node_modules/sqlite3').verbose();
const path = require('path');

const dbFile = path.join(__dirname, '../database.db');
const db = new sqlite3.Database(dbFile);

db.run("DELETE FROM brochures WHERE id = 32", [], function(err) {
    if (err) {
        console.error('Error deleting:', err.message);
    } else {
        console.log(`Successfully deleted incomplete brochure ID 32. Affected rows: ${this.changes}`);
    }
    db.close();
});
