const fs = require('fs');
const path = require('path');

// Parse config.php config variables
const configPath = path.join(__dirname, '../config.php');
let isLocal = true;
let db_host = 'localhost';
let db_name = '';
let db_user = '';
let db_pass = '';

try {
    const configContent = fs.readFileSync(configPath, 'utf8');
    
    db_host = configContent.match(/\$db_host\s*=\s*['"](.*?)['"]/)?.[1] || 'localhost';
    db_name = configContent.match(/\$db_name\s*=\s*['"](.*?)['"]/)?.[1] || '';
    db_user = configContent.match(/\$db_user\s*=\s*['"](.*?)['"]/)?.[1] || '';
    db_pass = configContent.match(/\$db_pass\s*=\s*['"](.*?)['"]/)?.[1] || '';

    // Check environment detection logic
    if (db_name === 'VERITABANI_ADINIZ' || __dirname.includes('emrecevik') || __dirname.includes('Documents/GitHub')) {
        isLocal = true;
    } else if (configContent.includes('$is_local = false')) {
        isLocal = false;
    } else if (configContent.includes('$is_local = true')) {
        isLocal = true;
    } else {
        isLocal = __dirname.includes('localhost') || __dirname.includes('127.0.0.1');
    }
} catch (e) {
    console.error('⚠️ config.php dosyası okunamadı, varsayılan SQLite moduna geçiliyor:', e.message);
}

let dbType = 'sqlite';
let sqliteConnection = null;

if (isLocal) {
    dbType = 'sqlite';
    try {
        const sqlite3 = require('sqlite3').verbose();
        const dbFile = path.join(__dirname, '../database.db');
        sqliteConnection = new sqlite3.Database(dbFile);
        console.log('✓ Scraper Veritabanı: Yerel SQLite modu aktif.');
    } catch (e) {
        console.warn('⚠️ Yerel SQLite başlatılamadı (sqlite3 yüklü olmayabilir):', e.message);
    }
} else {
    dbType = 'mysql';
    console.log(`✓ Scraper Veritabanı: cPanel MySQL modu aktif (${db_host} / ${db_name}).`);
}

/**
 * Executes a database query with parameter binding, abstracting SQLite vs MySQL differences.
 */
async function query(sql, params = []) {
    if (dbType === 'sqlite') {
        if (!sqliteConnection) {
            throw new Error('SQLite bağlantısı kurulmamış. Lütfen sqlite3 modülünün kurulu olduğundan emin olun.');
        }
        
        return new Promise((resolve, reject) => {
            // Convert MySQL-specific syntax to SQLite equivalent if needed
            let cleanedSql = sql.replace(/INSERT IGNORE/gi, 'INSERT OR IGNORE');
            
            if (cleanedSql.trim().toUpperCase().startsWith('SELECT')) {
                sqliteConnection.all(cleanedSql, params, (err, rows) => {
                    if (err) reject(err);
                    else resolve(rows);
                });
            } else {
                sqliteConnection.run(cleanedSql, params, function(err) {
                    if (err) reject(err);
                    else resolve({ insertId: this.lastID, affectedRows: this.changes });
                });
            }
        });
    } else {
        const mysql = require('mysql2/promise');
        const conn = await mysql.createConnection({
            host: db_host,
            user: db_user,
            password: db_pass,
            database: db_name,
            charset: 'utf8mb4'
        });
        try {
            const [results] = await conn.execute(sql, params);
            if (Array.isArray(results)) {
                return results;
            } else {
                return { insertId: results.insertId, affectedRows: results.affectedRows };
            }
        } finally {
            await conn.end();
        }
    }
}

module.exports = {
    query,
    dbType
};
