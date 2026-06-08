const fs = require('fs');
const path = require('path');

// Parse PHP config variables/constants without executing PHP.
const configPath = path.join(__dirname, '../config.php');
const localConfigPath = path.join(__dirname, '../config.local.php');
let isLocal = true;
let db_driver = 'auto';
let db_host = 'localhost';
let db_name = 'VERITABANI_ADINIZ';
let db_user = 'VERITABANI_KULLANICINIZ';
let db_pass = 'VERITABANI_SIFRENIZ';
let db_path = path.join(__dirname, '../database.db');

function readPhpValue(content, variableName, constantName, fallback) {
    const variableMatch = content.match(new RegExp(`\\$${variableName}\\s*=\\s*['"]([^'"]*)['"]`));
    const constantMatch = content.match(new RegExp(`define\\(\\s*['"]${constantName}['"]\\s*,\\s*['"]([^'"]*)['"]\\s*\\)`));

    if (constantMatch) {
        return constantMatch[1];
    }

    if (variableMatch) {
        return variableMatch[1];
    }

    return fallback;
}

function applyPhpConfig(filePath, isProduction = false) {
    if (!fs.existsSync(filePath)) {
        return;
    }

    let configContent = fs.readFileSync(filePath, 'utf8');

    if (filePath.endsWith('config.php') && isProduction) {
        const elseMatch = configContent.match(/else\s*\{([\s\S]*?)\}/);
        if (elseMatch) {
            configContent = elseMatch[1];
        }
    }

    db_driver = readPhpValue(configContent, 'db_driver', 'DB_DRIVER', db_driver).toLowerCase();
    db_host = readPhpValue(configContent, 'db_host', 'DB_HOST', db_host);
    db_name = readPhpValue(configContent, 'db_name', 'DB_NAME', db_name);
    db_user = readPhpValue(configContent, 'db_user', 'DB_USER', db_user);
    db_pass = readPhpValue(configContent, 'db_pass', 'DB_PASS', db_pass);
    db_path = readPhpValue(configContent, 'db_path', 'DB_PATH', db_path);
}

function hasMysqlConfig() {
    return db_name &&
        db_user &&
        db_name !== 'VERITABANI_ADINIZ' &&
        db_user !== 'VERITABANI_KULLANICINIZ' &&
        db_pass !== 'VERITABANI_SIFRENIZ';
}

try {
    const isProd = !fs.existsSync(localConfigPath);
    applyPhpConfig(configPath, isProd);
    applyPhpConfig(localConfigPath, false);

    db_driver = (process.env.DB_DRIVER || db_driver).toLowerCase();
    db_host = process.env.DB_HOST || db_host;
    db_name = process.env.DB_NAME || db_name;
    db_user = process.env.DB_USER || db_user;
    db_pass = process.env.DB_PASS || db_pass;
    db_path = process.env.DB_PATH || db_path;

    if (db_driver === 'sqlite') {
        isLocal = true;
    } else if (db_driver === 'mysql') {
        isLocal = false;
    } else if (!hasMysqlConfig() || __dirname.includes('emrecevik') || __dirname.includes('Documents/GitHub')) {
        isLocal = true;
    } else {
        isLocal = false;
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
        const dbFile = path.isAbsolute(db_path) ? db_path : path.join(__dirname, '..', db_path);
        sqliteConnection = new sqlite3.Database(dbFile);
        console.log(`✓ Scraper Veritabanı: SQLite modu aktif (${dbFile}).`);
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
