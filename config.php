<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_user = "admin";
$admin_pass = "161224";

date_default_timezone_set('Europe/Istanbul');

// cPanel / public_html/marketisleri.com defaults. Leave these placeholders as-is to use the bundled SQLite database.
// For cPanel MySQL, either edit these variables or create config.local.php from config.local.example.php.
$db_driver = 'auto'; // auto, sqlite, mysql
$db_host = 'localhost';
$db_name = 'marketis_market';
$db_user = 'marketis_market';
$db_pass = 'CkWN1Opjn(*N2o0;';
$db_path = __DIR__ . '/database.db';

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

// Environment detection
$is_local = true;
if (isset($_SERVER['HTTP_HOST'])) {
    $host = strtolower($_SERVER['HTTP_HOST']);
    $is_local = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
}

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
            ('smtp_from_name', '')
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
try {
    $pdo->query("SELECT scraper_url FROM markets LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE markets ADD COLUMN scraper_url TEXT DEFAULT NULL");
        $pdo->exec("ALTER TABLE markets ADD COLUMN scraper_container VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE markets ADD COLUMN scraper_title VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE markets ADD COLUMN scraper_cover VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE markets ADD COLUMN scraper_detail_link VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE markets ADD COLUMN scraper_page_image VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE markets ADD COLUMN scraper_active INT DEFAULT 0");
    } catch (PDOException $ex) {
        // Fail silently
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
