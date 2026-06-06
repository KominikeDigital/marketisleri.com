const sqlite3 = require('sqlite3').verbose();
const path = require('path');

const dbFile = path.join(__dirname, '../database.db');
const db = new sqlite3.Database(dbFile);

db.all("SELECT * FROM markets", [], (err, rows) => {
    if (err) {
        console.error('Error fetching markets:', err.message);
        return;
    }
    console.log('--- MARKETS ---');
    console.log(rows);
    db.close();
});
