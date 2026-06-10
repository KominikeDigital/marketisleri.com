<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_user = "admin";
$admin_pass = "161224";

date_default_timezone_set('Europe/Istanbul');

// Self-healing .htaccess creation
$htaccess_path = __DIR__ . '/.htaccess';
if (!is_file($htaccess_path)) {
    $htaccess_content = "DirectoryIndex index.php index.html\n" .
        "Options -Indexes\n\n" .
        "<IfModule mod_rewrite.c>\n" .
        "    RewriteEngine On\n\n" .
        "    RewriteRule ^sitemap\\.xml$ sitemap.php [L]\n\n" .
        "    RewriteRule ^(?:config(?:\\.local)?\\.php|database\\.(?:db|sql)|\\.env|\\.DS_Store)$ - [F,L]\n" .
        "    RewriteRule ^(?:scraper|scratch)(?:/|$) - [F,L]\n" .
        "</IfModule>\n\n" .
        "<FilesMatch \"^(config(\\.local)?\\.php|database\\.(db|sql)|\\.env|\\.DS_Store|package(-lock)?\\.json)$\">\n" .
        "    <IfModule mod_authz_core.c>\n" .
        "        Require all denied\n" .
        "    </IfModule>\n" .
        "    <IfModule !mod_authz_core.c>\n" .
        "        Order allow,deny\n" .
        "        Deny from all\n" .
        "    </IfModule>\n" .
        "</FilesMatch>\n";
    @file_put_contents($htaccess_path, $htaccess_content);
}

// Environment detection based on config.local.php existence
$is_local = is_file(__DIR__ . '/config.local.php');

if ($is_local) {
    // Local development settings (SQLite)
    $db_driver = 'sqlite';
    $db_host = 'localhost';
    $db_name = 'VERITABANI_ADINIZ';
    $db_user = 'VERITABANI_KULLANICINIZ';
    $db_pass = 'VERITABANI_SIFRENIZ';
    $db_path = __DIR__ . '/database.db';
} else {
    // cPanel Production settings (MySQL)
    $db_driver = 'mysql';
    $db_host = 'localhost';
    $db_name = 'marketis_market';
    $db_user = 'marketis_market';
    $db_pass = 'CkWN1Opjn(*N2o0;';
    $db_path = __DIR__ . '/database.db';
}

$local_config = __DIR__ . '/config.local.php';
if (is_file($local_config)) {
    require $local_config;
}

function config_value($constant, $env_name, $current_value) {
    if (defined($constant)) {
        return constant($constant);
    }

    $env_value = getenv($env_name);
    if ($env_value !== false && $env_value !== '') {
        return $env_value;
    }

    return $current_value;
}

$admin_user = config_value('ADMIN_USER', 'ADMIN_USER', $admin_user);
$admin_pass = config_value('ADMIN_PASS', 'ADMIN_PASS', $admin_pass);
$db_driver = strtolower((string) config_value('DB_DRIVER', 'DB_DRIVER', $db_driver));
$db_host = config_value('DB_HOST', 'DB_HOST', $db_host);
$db_name = config_value('DB_NAME', 'DB_NAME', $db_name);
$db_user = config_value('DB_USER', 'DB_USER', $db_user);
$db_pass = config_value('DB_PASS', 'DB_PASS', $db_pass);
$db_path = config_value('DB_PATH', 'DB_PATH', $db_path);

function current_site_url() {
    $configured_site_url = config_value('SITE_URL', 'SITE_URL', '');
    if ($configured_site_url !== '') {
        return rtrim($configured_site_url, '/');
    }

    if (empty($_SERVER['HTTP_HOST'])) {
        return 'https://marketisleri.com';
    }

    $host = $_SERVER['HTTP_HOST'];
    $is_https =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    return ($is_https ? 'https' : 'http') . '://' . $host;
}

$site_url = current_site_url();

function mysql_configured($db_name, $db_user, $db_pass) {
    if ($db_name === '' || $db_user === '') {
        return false;
    }

    return $db_name !== 'VERITABANI_ADINIZ' &&
        $db_user !== 'VERITABANI_KULLANICINIZ' &&
        $db_pass !== 'VERITABANI_SIFRENIZ';
}

function initialize_database($pdo, $driver) {
    if ($driver === 'mysql') {
        $schema = [
            "CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                icon VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS markets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                logo VARCHAR(255),
                description TEXT,
                category_id INT,
                CONSTRAINT fk_markets_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS brochures (
                id INT AUTO_INCREMENT PRIMARY KEY,
                market_id INT,
                title VARCHAR(255) NOT NULL,
                cover_image VARCHAR(255),
                pdf_path VARCHAR(255) DEFAULT NULL,
                start_date DATE,
                end_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_brochures_market FOREIGN KEY (market_id) REFERENCES markets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS brochure_pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                brochure_id INT,
                page_number INT,
                image_path VARCHAR(255) NOT NULL,
                CONSTRAINT fk_pages_brochure FOREIGN KEY (brochure_id) REFERENCES brochures(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS settings (
                key_name VARCHAR(100) PRIMARY KEY,
                value_text TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS subscribers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS brochure_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                brochure_id INT NOT NULL,
                page_number INT NOT NULL DEFAULT 1,
                product_name VARCHAR(500) NOT NULL,
                price DECIMAL(10,2) DEFAULT NULL,
                original_price DECIMAL(10,2) DEFAULT NULL,
                unit VARCHAR(100) DEFAULT NULL,
                x_pct FLOAT DEFAULT NULL,
                y_pct FLOAT DEFAULT NULL,
                w_pct FLOAT DEFAULT NULL,
                h_pct FLOAT DEFAULT NULL,
                analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_products_brochure FOREIGN KEY (brochure_id) REFERENCES brochures(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS price_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                product_name VARCHAR(500) NOT NULL,
                target_price DECIMAL(10,2) DEFAULT NULL,
                last_notified_price DECIMAL(10,2) DEFAULT NULL,
                market_id INT DEFAULT NULL,
                is_active TINYINT DEFAULT 1,
                token VARCHAR(64) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                notified_at TIMESTAMP NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
    } else {
        $schema = [
            "CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                icon TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS markets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                logo TEXT,
                description TEXT,
                category_id INTEGER,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS brochures (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                market_id INTEGER,
                title TEXT NOT NULL,
                cover_image TEXT,
                pdf_path TEXT,
                start_date DATE,
                end_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (market_id) REFERENCES markets(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS brochure_pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                brochure_id INTEGER,
                page_number INTEGER,
                image_path TEXT NOT NULL,
                FOREIGN KEY (brochure_id) REFERENCES brochures(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS settings (
                key_name TEXT PRIMARY KEY,
                value_text TEXT
            )",
            "CREATE TABLE IF NOT EXISTS subscribers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS brochure_products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                brochure_id INTEGER NOT NULL,
                page_number INTEGER NOT NULL DEFAULT 1,
                product_name TEXT NOT NULL,
                price REAL DEFAULT NULL,
                original_price REAL DEFAULT NULL,
                unit TEXT DEFAULT NULL,
                x_pct REAL DEFAULT NULL,
                y_pct REAL DEFAULT NULL,
                w_pct REAL DEFAULT NULL,
                h_pct REAL DEFAULT NULL,
                analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (brochure_id) REFERENCES brochures(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS price_alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                product_name TEXT NOT NULL,
                target_price REAL DEFAULT NULL,
                last_notified_price REAL DEFAULT NULL,
                market_id INTEGER DEFAULT NULL,
                is_active INTEGER DEFAULT 1,
                token TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                notified_at TIMESTAMP DEFAULT NULL
            )"
        ];
    }

    foreach ($schema as $statement) {
        $pdo->exec($statement);
    }

    $category_count = (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($category_count === 0) {
        $pdo->exec("
            INSERT INTO categories (name, icon) VALUES
            ('Süpermarket', 'shopping_cart'),
            ('Yapı Market', 'home_repair_service'),
            ('Teknoloji', 'devices'),
            ('Kozmetik', 'spa'),
            ('Moda', 'checkroom'),
            ('Anne & Bebek', 'child_care')
        ");
    }

    $market_count = (int) $pdo->query("SELECT COUNT(*) FROM markets")->fetchColumn();
    if ($market_count === 0) {
        $pdo->exec("
            INSERT INTO markets (name, slug, logo, description, category_id) VALUES
            ('BİM', 'bim', 'bim.png', 'BİM Aktüel Ürünler ve İndirim Broşürleri', 1),
            ('A101', 'a101', 'a101.png', 'A101 Aldın Aldın İndirim Kataloğu', 1),
            ('ŞOK', 'sok', 'sok.png', 'ŞOK Haftanın Fırsatları Kataloğu', 1),
            ('Teknosa', 'teknosa', 'teknosa.png', 'Teknosa Teknoloji Kampanyaları', 3)
        ");
    }

    $settings_count = (int) $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($settings_count === 0) {
        $pdo->exec("
            INSERT INTO settings (key_name, value_text) VALUES
            ('social_facebook', ''),
            ('social_instagram', ''),
            ('social_twitter', ''),
            ('social_youtube', ''),
            ('smtp_host', ''),
            ('smtp_port', ''),
            ('smtp_user', ''),
            ('smtp_pass', ''),
            ('smtp_secure', ''),
            ('smtp_from_email', ''),
            ('smtp_from_name', ''),
            ('gemini_api_key', '')
        ");
    }
}

$use_mysql = $db_driver === 'mysql' || ($db_driver === 'auto' && mysql_configured($db_name, $db_user, $db_pass));
$active_db_driver = $use_mysql ? 'mysql' : 'sqlite';

try {
    if ($use_mysql) {
        if (!mysql_configured($db_name, $db_user, $db_pass)) {
            die("MySQL bilgileri eksik. config.local.php dosyasını oluşturun ya da config.php içindeki cPanel veritabanı bilgilerini doldurun.");
        }

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    } else {
        $database_dir = dirname($db_path);
        if (!is_dir($database_dir)) {
            mkdir($database_dir, 0755, true);
        }

        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->exec("PRAGMA foreign_keys = ON;");
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    initialize_database($pdo, $active_db_driver);
} catch (PDOException $e) {
    $driver_name = $use_mysql ? 'MySQL' : 'SQLite';
    die($driver_name . " veritabanı bağlantı/kurulum hatası: " . $e->getMessage());
}

// Check and insert new SEO settings if missing
try {
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = ?");
    
    $seo_defaults = [
        'seo_title_home' => 'Tüm Market Broşürleri Tek Yerde | marketisleri.com',
        'seo_description_home' => 'BİM, A101, ŞOK, Migros ve diğer süpermarketlerin en güncel broşürleri, aktüel ürün katalogları ve haftalık indirimleri tek bir yerde!',
        'seo_keywords_home' => 'market broşürleri, aktüel ürünler, bim aktüel, a101 aktüel, şok katalog, haftalık indirimler, indirim broşürleri'
    ];
    
    $insert_stmt = $pdo->prepare("INSERT INTO settings (key_name, value_text) VALUES (?, ?)");
    foreach ($seo_defaults as $key => $val) {
        $check_stmt->execute([$key]);
        if ($check_stmt->fetchColumn() == 0) {
            $insert_stmt->execute([$key, $val]);
        }
    }
} catch (PDOException $e) {
    // Fail silently
}

// Database Migrations for Scraper settings in markets table
$columns_to_add = [
    'scraper_url' => 'TEXT DEFAULT NULL',
    'scraper_container' => 'VARCHAR(255) DEFAULT NULL',
    'scraper_title' => 'VARCHAR(255) DEFAULT NULL',
    'scraper_cover' => 'VARCHAR(255) DEFAULT NULL',
    'scraper_detail_link' => 'VARCHAR(255) DEFAULT NULL',
    'scraper_page_image' => 'VARCHAR(255) DEFAULT NULL',
    'scraper_active' => 'INT DEFAULT 0'
];

foreach ($columns_to_add as $col => $type) {
    try {
        $pdo->query("SELECT $col FROM markets LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE markets ADD COLUMN $col $type");
        } catch (PDOException $ex) {
            // Fail silently
        }
    }
}

// Database Migrations for Popular Markets feature
try {
    $pdo->query("SELECT is_popular FROM markets LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE markets ADD COLUMN is_popular INT DEFAULT 0");
        $pdo->exec("UPDATE markets SET is_popular = 1 WHERE slug IN ('bim', 'a101', 'sok', 'teknosa')");
    } catch (PDOException $ex) {
        // Fail silently
    }
}

// Database Migrations for Brochure AI Analysis status
try {
    $pdo->query("SELECT analyzed_at FROM brochures LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE brochures ADD COLUMN analyzed_at TIMESTAMP NULL DEFAULT NULL");
    } catch (PDOException $ex) {
        // Fail silently
    }
}

// Ensure and configure scraper markets
$ensure_market = function($pdo, $name, $slug, $logo, $desc, $cat_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM markets WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetchColumn() == 0) {
        try {
            $insert = $pdo->prepare("INSERT INTO markets (name, slug, logo, description, category_id) VALUES (?, ?, ?, ?, ?)");
            $insert->execute([$name, $slug, $logo, $desc, $cat_id]);
        } catch (PDOException $e) {
            // Fallback without category_id if foreign key fails
            try {
                $insert = $pdo->prepare("INSERT INTO markets (name, slug, logo, description) VALUES (?, ?, ?, ?)");
                $insert->execute([$name, $slug, $logo, $desc]);
            } catch (PDOException $ex) {
                // Fail silently
            }
        }
    }
};

try {
    $ensure_market($pdo, 'Metro', 'metro', 'metro.png', 'Metro İndirim ve Fırsatları', 1);
} catch (Exception $e) {}

try {
    // Check both standard and production specific slug for Tarım Kredi
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM markets WHERE slug = 'tar-m-kredi-market-1780824588' OR slug = 'tarim-kredi-market'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $ensure_market($pdo, 'Tarım Kredi Market', 'tarim-kredi-market', 'tarim-kredi-market.png', 'Tarım Kredi Market İndirim ve Fırsatları', 1);
    }
} catch (Exception $e) {}

// Auto-ensure all major markets exist in DB
$all_markets_to_ensure = [
    ['Akyurt Süpermarket',        'akyurt-supermarket',      'akyurt-supermarket.png',       'Akyurt Süpermarket İndirim Kataloğu',           1],
    ['Ali Pehlivanoğlu',          'ali-pehlivanoglu',        'ali-pehlivanoglu.png',          'Ali Pehlivanoğlu İndirim Broşürleri',           1],
    ['Altun Market',              'altun-market',            'altun-market.png',              'Altun Market Aktüel Ürünler',                   1],
    ['Altunbilekler Market',      'altunbilekler-market',    'altunbilekler-market.png',      'Altunbilekler Market İndirim Broşürü',          1],
    ['Anpa Gross',                'anpa-gross',              'anpa-gross.png',                'Anpa Gross İndirim Broşürleri',                 1],
    ['Arden Market',              'arden-market',            'arden-market.png',              'Arden Market Aktüel Ürünler',                   1],
    ['Aypa Market',               'aypa-market',             'aypa-market.png',               'Aypa Market İndirim Kataloğu',                  1],
    ['Barış Gross Market',        'baris-gross-market',      'baris-gross-market.png',        'Barış Gross Market İndirim Broşürü',            1],
    ['Başdaş Market',             'basdas-market',           'basdas-market.png',             'Başdaş Market Aktüel Ürünler',                  1],
    ['Başgimpa',                  'basgimpa',                'basgimpa.png',                  'Başgimpa İndirim Broşürleri',                   1],
    ['Beykoz Market',             'beykoz-market',           'beykoz-market.png',             'Beykoz Market İndirim Kataloğu',                1],
    ['Biçen Market',              'bicen-market',            'bicen-market.png',              'Biçen Market Aktüel Ürünler',                   1],
    ['Bizim Toptan',              'bizim-toptan',            'bizim-toptan.png',              'Bizim Toptan İndirim Broşürleri',               1],
    ['Bonveno',                   'bonveno',                 'bonveno.png',                   'Bonveno Market Aktüel Ürünler',                 1],
    ['Çağdaş Market',             'cagdas-market',           'cagdas-market.png',             'Çağdaş Market İndirim Kataloğu',                1],
    ['Çağrı Market',              'cagri-market',            'cagri-market.png',              'Çağrı Market Aktüel Ürünler',                   1],
    ['Çarşı Market',              'carsi-market',            'carsi-market.png',              'Çarşı Market İndirim Broşürü',                  1],
    ['Damla Hipermarket',         'damla-hipermarket',       'damla-hipermarket.png',         'Damla Hipermarket Aktüel Ürünler',              1],
    ['Egeşok Market',             'egesok-market',           'egesok-market.png',             'Egeşok Market İndirim Kataloğu',                1],
    ['Esenlik Market',            'esenlik-market',          'esenlik-market.png',            'Esenlik Market Aktüel Ürünler',                 1],
    ['Essen Market',              'essen-market',            'essen-market.png',              'Essen Market İndirim Broşürü',                  1],
    ['Etik Hipermarket',          'etik-hipermarket',        'etik-hipermarket.png',          'Etik Hipermarket Aktüel Ürünler',               1],
    ['Hakmar',                    'hakmar',                  'hakmar.png',                    'Hakmar İndirim Broşürleri',                     1],
    ['Hakmar Ekspres',            'hakmar-ekspres',          'hakmar-ekspres.png',            'Hakmar Ekspres Aktüel Ürünler',                 1],
    ['Migros',                    'migros',                  'migros.png',                    'Migros Haftanın Fırsatları Kataloğu',           1],
    ['CarrefourSA',               'carrefoursa',             'carrefoursa.png',               'CarrefourSA İndirim ve Kampanya Broşürleri',    1],
    ['Onur Market',               'onur-market',             'onur-market.png',               'Onur Market İndirim Broşürü',                   1],
    ['Özhan Marketler',           'ozhan-marketler',         'ozhan-marketler.png',           'Özhan Marketler Aktüel Ürünler',                1],
    ['Sembol Center',             'sembol-center',           'sembol-center.png',             'Sembol Center İndirim Kataloğu',                1],
    ['Serra Grup Market',         'serra-grup-market',       'serra-grup-market.png',         'Serra Grup Market Aktüel Ürünler',              1],
    ['Şevikoğlu Market',          'sevikoglu-market',        'sevikoglu-market.png',          'Şevikoğlu Market İndirim Broşürü',              1],
    ['Seyhanlar Market',          'seyhanlar-market',        'seyhanlar-market.png',          'Seyhanlar Market Aktüel Ürünler',               1],
    ['Show Hipermarket',          'show-hipermarket',        'show-hipermarket.png',          'Show Hipermarket İndirim Kataloğu',             1],
    ['Sultan Market',             'sultan-market',           'sultan-market.png',             'Sultan Market Aktüel Ürünler',                  1],
    ['Tahtakale Spot',            'tahtakale-spot',          'tahtakale-spot.png',            'Tahtakale Spot İndirim Broşürü',                1],
    ['Tema Market',               'tema-market',             'tema-market.png',               'Tema Market Aktüel Ürünler',                    1],
    ['Üçler Market',              'ucler-market',            'ucler-market.png',              'Üçler Market İndirim Kataloğu',                 1],
    ['Ulukardeşler',              'ulukardesler',            'ulukardesler.png',              'Ulukardeşler Market Aktüel Ürünler',            1],
    ['Yunus Market',              'yunus-market',            'yunus-market.png',              'Yunus Market İndirim Broşürü',                  1],
    ['Zırhlı Toptan Market',      'zirhlı-toptan',           'zirhlı-toptan.png',             'Zırhlı Toptan Market Aktüel Ürünler',           1],
    ['Şehzade Market',            'sehzade-market',          'sehzade-market.png',            'Şehzade Market İndirim Kataloğu',               1],
    ['MacroCenter',               'macrocenter',             'macrocenter.png',               'MacroCenter Aktüel Ürünler',                    1],
    ['Namlı Hipermarket',         'namli-hipermarket',       'namli-hipermarket.png',         'Namlı Hipermarket İndirim Broşürü',             1],
    ['Oruç Market',               'oruc-market',             'oruc-market.png',               'Oruç Market Aktüel Ürünler',                    1],
    ['Özdilek',                   'ozdilek',                 'ozdilek.png',                   'Özdilek İndirim ve Kampanya Broşürleri',         2],
    ['File Market',               'file',                    'file.png',                      'File Market Aktüel Ürünler',                    1],
    ['Tespo Cash & Carry',        'tespo-cash-carry',        'tespo-cash-carry.png',          'Tespo Cash & Carry İndirim Broşürleri',         1],
];

foreach ($all_markets_to_ensure as $m) {
    try { $ensure_market($pdo, $m[0], $m[1], $m[2], $m[3], $m[4]); } catch (Exception $e) {}
}

// Database Migrations for Market Logos matching physical files
try {
    $logo_mapping = [
        'bim'                            => 'bim-1780746538.png',
        'a101'                           => 'a101-1780746532.jpg',
        'sok'                            => 'sok-1780746544.png',
        'migros'                         => 'migros-1780746581.png',
        'carrefoursa'                    => 'carrefoursa-1780746620.png',
        'akyurt-supermarket'             => 'Akyurt.jpg',
        'ali-pehlivanoglu'               => 'Ali Pehlivanoğlu.jpg',
        'altun-market'                   => 'Altun.png',
        'altunbilekler-market'           => 'Altunbilekler.jpg',
        'anpa-gross'                     => 'Anpa Gross.jpg',
        'arden-market'                   => 'Arden.jpg',
        'aypa-market'                    => 'Aypa.jpg',
        'baris-gross-market'             => 'Barış Gross.jpg',
        'basdas-market'                  => 'Başdaş Market.jpg',
        'basgimpa'                       => 'Başgimpa.jpg',
        'beykoz-market'                  => 'Beykoz Market.jpg',
        'bicen-market'                   => 'Biçen.png',
        'bizim-toptan'                   => 'Bizim Toptan Satış Mağazaları.jpg',
        'bonveno'                        => 'BonVeno.jpg',
        'cagdas-market'                  => 'Çağdaş.png',
        'cagri-market'                   => 'Çağrı.jpg',
        'carsi-market'                   => 'Çarşı Market.png',
        'damla-hipermarket'              => 'Damla Hipermarketleri.jpg',
        'egesok-market'                  => 'Egeşok.jpg',
        'esenlik-market'                 => 'Esenlik.jpg',
        'essen-market'                   => 'Essen.jpg',
        'etik-hipermarket'               => 'Etik.jpg',
        'hakmar'                         => 'Hakmar Alışveriş Merkezleri.png',
        'hakmar-ekspres'                 => 'Hakmar Express.jpg',
        'onur-market'                    => 'Onur.jpg',
        'ozhan-marketler'                => 'Özhan.jpg',
        'sembol-center'                  => 'Sembol Center.jpg',
        'serra-grup-market'              => 'Serra Grup.jpg',
        'sevikoglu-market'               => 'Şevikoğlu.jpg',
        'seyhanlar-market'               => 'Seyhanlar.png',
        'show-hipermarket'               => 'Show .png',
        'sultan-market'                  => 'Sultan.jpg',
        'tahtakale-spot'                 => 'Tahtakale Spot.jpg',
        'tema-market'                    => 'Tema Mağazalar Zinciri.jpg',
        'ucler-market'                   => 'Üçler.png',
        'ulukardesler'                   => 'Snowy Ulu Kardeşler.png',
        'yunus-market'                   => 'Yunus.jpg',
        'zirhlı-toptan'                  => 'Zırhlı Toptan Market.jpg',
        'sehzade-market'                 => 'Şehzade.jpg',
        'macrocenter'                    => 'Macrocenter.jpg',
        'namli-hipermarket'              => 'Namlı Hipermarketleri.jpg',
        'oruc-market'                    => 'Oruç.jpg',
        'ozdilek'                        => 'Özdilek.jpg',
        'file'                           => 'File.jpg',
        'tespo-cash-carry'               => 'Tespo Cash & Carry.png'
    ];

    $update_logo_stmt = $pdo->prepare("UPDATE markets SET logo = ? WHERE slug = ?");
    foreach ($logo_mapping as $slug => $logo_file) {
        $update_logo_stmt->execute([$logo_file, $slug]);
    }
} catch (Exception $e) { /* ignore */ }

// Full scrapers map: local DB slug => aktuelbrosurler.com path
$scrapers = [
    'bim'                            => 'https://aktuelbrosurler.com/bim/brosurler',
    'a101'                           => 'https://aktuelbrosurler.com/a101/brosurler',
    'sok'                            => 'https://aktuelbrosurler.com/sok/brosurler',
    'migros'                         => 'https://aktuelbrosurler.com/migros/brosurler',
    'carrefoursa'                    => 'https://aktuelbrosurler.com/carrefour/brosurler',
    'tarim-kredi-market'             => 'https://aktuelbrosurler.com/tarim-kredi-kooperatif_market/brosurler',
    'tar-m-kredi-market-1780824588'  => 'https://aktuelbrosurler.com/tarim-kredi-kooperatif_market/brosurler',
    'metro'                          => 'https://aktuelbrosurler.com/metrotoptancimarket/brosurler',
    'ozdilek'                        => 'https://aktuelbrosurler.com/ozdilek/brosurler',
    'file'                           => 'https://aktuelbrosurler.com/file-market/brosurler',
    'bizim-toptan-satis-magazalari'  => 'https://aktuelbrosurler.com/bizimtoptanmarket/brosurler',
    'bizim-toptan'                   => 'https://aktuelbrosurler.com/bizimtoptanmarket/brosurler',
    'tespo-cash-carry'               => 'https://aktuelbrosurler.com/tespo/brosurler',
    'akyurt-supermarket'             => 'https://aktuelbrosurler.com/akyurtsupermarket/brosurler',
    'ali-pehlivanoglu'               => 'https://aktuelbrosurler.com/alipehlivanoglu/brosurler',
    'altun-market'                   => 'https://aktuelbrosurler.com/altunmarket/brosurler',
    'altunbilekler-market'           => 'https://aktuelbrosurler.com/altunbileklermarket/brosurler',
    'anpa-gross'                     => 'https://aktuelbrosurler.com/anpa-gross/brosurler',
    'arden-market'                   => 'https://aktuelbrosurler.com/arden-market/brosurler',
    'aypa-market'                    => 'https://aktuelbrosurler.com/aypa_market/brosurler',
    'baris-gross-market'             => 'https://aktuelbrosurler.com/barisgrossmarket/brosurler',
    'basdas-market'                  => 'https://aktuelbrosurler.com/basdasmarket/brosurler',
    'basgimpa'                       => 'https://aktuelbrosurler.com/basgimpa/brosurler',
    'beykoz-market'                  => 'https://aktuelbrosurler.com/beykoz_market/brosurler',
    'bicen-market'                   => 'https://aktuelbrosurler.com/bicenmarket/brosurler',
    'bonveno'                        => 'https://aktuelbrosurler.com/Bonveno/brosurler',
    'cagdas-market'                  => 'https://aktuelbrosurler.com/cagdas-market/brosurler',
    'cagri-market'                   => 'https://aktuelbrosurler.com/cagrimarket/brosurler',
    'carsi-market'                   => 'https://aktuelbrosurler.com/carsi-market/brosurler',
    'damla-hipermarket'              => 'https://aktuelbrosurler.com/damla_hipermarket/brosurler',
    'egesok-market'                  => 'https://aktuelbrosurler.com/egesok/brosurler',
    'esenlik-market'                 => 'https://aktuelbrosurler.com/esenlik_market/brosurler',
    'essen-market'                   => 'https://aktuelbrosurler.com/essen_market/brosurler',
    'etik-hipermarket'               => 'https://aktuelbrosurler.com/etik-hipermarket/brosurler',
    'hakmar'                         => 'https://aktuelbrosurler.com/hakmar/brosurler',
    'hakmar-ekspres'                 => 'https://aktuelbrosurler.com/hakmar-ekspres/brosurler',
    'onur-market'                    => 'https://aktuelbrosurler.com/onurmarket/brosurler',
    'ozhan-marketler'                => 'https://aktuelbrosurler.com/ozhanmarketler/brosurler',
    'sembol-center'                  => 'https://aktuelbrosurler.com/sembolcenter/brosurler',
    'serra-grup-market'              => 'https://aktuelbrosurler.com/grup_serra_market/brosurler',
    'sevikoglu-market'               => 'https://aktuelbrosurler.com/sevikoglu_market/brosurler',
    'seyhanlar-market'               => 'https://aktuelbrosurler.com/seyhanlargrospermarket/brosurler',
    'show-hipermarket'               => 'https://aktuelbrosurler.com/show_hipermarket/brosurler',
    'sultan-market'                  => 'https://aktuelbrosurler.com/sultanmarket/brosurler',
    'tahtakale-spot'                 => 'https://aktuelbrosurler.com/tahtakale-spot/brosurler',
    'tema-market'                    => 'https://aktuelbrosurler.com/tema-market/brosurler',
    'ucler-market'                   => 'https://aktuelbrosurler.com/uclermarket/brosurler',
    'ulukardesler'                   => 'https://aktuelbrosurler.com/ulukardesler/brosurler',
    'yunus-market'                   => 'https://aktuelbrosurler.com/yunusmarket/brosurler',
    'sehzade-market'                 => 'https://aktuelbrosurler.com/sehzademarket/brosurler',
    'macrocenter'                    => 'https://aktuelbrosurler.com/macrocenter/brosurler',
    'namli-hipermarket'              => 'https://aktuelbrosurler.com/namlihipermarket/brosurler',
    'oruc-market'                    => 'https://aktuelbrosurler.com/oruc-market/brosurler',
];

foreach ($scrapers as $slug => $url) {
    try {
        $update_stmt = $pdo->prepare("UPDATE markets SET 
            scraper_url = ?, 
            scraper_container = 'a.brosur-link', 
            scraper_title = '.excerpt p', 
            scraper_cover = '.media-wrapper', 
            scraper_detail_link = '', 
            scraper_page_image = '', 
            scraper_active = 1 
            WHERE slug = ?");
        $update_stmt->execute([$url, $slug]);
    } catch (PDOException $e) {
        // Fail silently for this market, continue with others
    }
}


// Auto cleanup function: removes brochures expired for more than 30 days along with their files
function cleanup_expired_brochures($pdo) {
    $one_month_ago = date('Y-m-d', strtotime('-30 days'));
    try {
        // Fetch expired brochures
        $stmt = $pdo->prepare("SELECT id, cover_image, pdf_path FROM brochures WHERE end_date < ?");
        $stmt->execute([$one_month_ago]);
        $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($expired)) {
            $uploads_dir = __DIR__ . '/uploads';
            
            foreach ($expired as $b) {
                $b_id = $b['id'];
                
                // Delete cover image
                if (!empty($b['cover_image']) && file_exists($uploads_dir . '/brochures/' . $b['cover_image'])) {
                    @unlink($uploads_dir . '/brochures/' . $b['cover_image']);
                }
                
                // Delete PDF
                if (!empty($b['pdf_path']) && file_exists($uploads_dir . '/brochures/pdfs/' . $b['pdf_path'])) {
                    @unlink($uploads_dir . '/brochures/pdfs/' . $b['pdf_path']);
                }
                
                // Delete page images
                $pages_stmt = $pdo->prepare("SELECT image_path FROM brochure_pages WHERE brochure_id = ?");
                $pages_stmt->execute([$b_id]);
                while ($img = $pages_stmt->fetchColumn()) {
                    if (!empty($img) && file_exists($uploads_dir . '/brochures/pages/' . $img)) {
                        @unlink($uploads_dir . '/brochures/pages/' . $img);
                    }
                }
                
                // Delete DB records
                $pdo->prepare("DELETE FROM brochure_pages WHERE brochure_id = ?")->execute([$b_id]);
                $pdo->prepare("DELETE FROM brochures WHERE id = ?")->execute([$b_id]);
            }
        }
    } catch (Exception $e) {
        // Fail silently
    }
}

// Global send email wrapper supporting SMTP (sockets) and native PHP mail() fallbacks
function send_email_notification($to, $subject, $message, $pdo) {
    // Fetch settings
    $settings_stmt = $pdo->query("SELECT * FROM settings WHERE key_name LIKE 'smtp_%'");
    $smtp = [];
    while ($row = $settings_stmt->fetch()) {
        $smtp[$row['key_name']] = $row['value_text'];
    }

    $host = $smtp['smtp_host'] ?? '';
    $port = intval($smtp['smtp_port'] ?? 0);
    $user = $smtp['smtp_user'] ?? '';
    $pass = $smtp['smtp_pass'] ?? '';
    $secure = strtolower($smtp['smtp_secure'] ?? '');
    $from = $smtp['smtp_from_email'] ?? 'no-reply@marketisleri.com';
    $from_name = $smtp['smtp_from_name'] ?? 'marketisleri.com';

    // If SMTP host is not configured, fall back to native PHP mail()
    if (empty($host)) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <$from>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        return @mail($to, "=?UTF-8?B?" . base64_encode($subject) . "?=", $message, $headers);
    }

    // SMTP Socket Sending
    try {
        $socket_protocol = ($secure === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($socket_protocol . $host, $port, $errno, $errstr, 10);
        if (!$socket) {
            throw new Exception("SMTP socket connection failed: $errstr ($errno)");
        }

        $parse = function($socket, $response) {
            $reply = '';
            while (substr($reply, 3, 1) != ' ') {
                if (!($reply = fgets($socket, 256))) {
                    throw new Exception("Error reading from server");
                }
            }
            if (substr($reply, 0, 3) != $response) {
                throw new Exception("Expected $response, got $reply");
            }
        };

        $parse($socket, '220');
        
        fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $parse($socket, '250');

        if ($secure === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $parse($socket, '220');
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new Exception("TLS negotiation failed");
            }
            fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
            $parse($socket, '250');
        }

        if (!empty($user) && !empty($pass)) {
            fputs($socket, "AUTH LOGIN\r\n");
            $parse($socket, '334');
            fputs($socket, base64_encode($user) . "\r\n");
            $parse($socket, '334');
            fputs($socket, base64_encode($pass) . "\r\n");
            $parse($socket, '235');
        }

        fputs($socket, "MAIL FROM: <$from>\r\n");
        $parse($socket, '250');
        fputs($socket, "RCPT TO: <$to>\r\n");
        $parse($socket, '250');
        fputs($socket, "DATA\r\n");
        $parse($socket, '354');

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <$from>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        fputs($socket, $headers . "\r\n" . $message . "\r\n.\r\n");
        $parse($socket, '250');

        fputs($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    } catch (Exception $e) {
        // Return false on error
        return false;
    }
}

// Execute cleanup
cleanup_expired_brochures($pdo);
?>
