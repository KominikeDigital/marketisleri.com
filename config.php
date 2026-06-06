<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$admin_user = "admin";
$admin_pass = "161224";

// Environment detection
$is_local = false;
if (isset($_SERVER['HTTP_HOST'])) {
    $host = $_SERVER['HTTP_HOST'];
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        $is_local = true;
    }
} else {
    $is_local = true;
}

$site_url = $is_local ? (isset($_SERVER['HTTP_HOST']) ? "http://" . $_SERVER['HTTP_HOST'] : "http://localhost:8000") : "https://marketisleri.com";

if ($is_local) {
    $db_path = __DIR__ . '/database.db';
    try {
        $pdo = new PDO("sqlite:" . $db_path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA foreign_keys = ON;");
        
        // If the database file is newly created or tables do not exist, run schema initialization
        $table_check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='categories'");
        if (!$table_check->fetch()) {
            // Initialize schema
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    icon TEXT NOT NULL
                );
                CREATE TABLE IF NOT EXISTS markets (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    slug TEXT NOT NULL UNIQUE,
                    logo TEXT,
                    description TEXT,
                    category_id INTEGER,
                    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
                );
                CREATE TABLE IF NOT EXISTS brochures (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    market_id INTEGER,
                    title TEXT NOT NULL,
                    cover_image TEXT,
                    pdf_path TEXT,
                    start_date DATE,
                    end_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (market_id) REFERENCES markets(id) ON DELETE CASCADE
                );
                CREATE TABLE IF NOT EXISTS brochure_pages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    brochure_id INTEGER,
                    page_number INTEGER,
                    image_path TEXT NOT NULL,
                    FOREIGN KEY (brochure_id) REFERENCES brochures(id) ON DELETE CASCADE
                );
                CREATE TABLE IF NOT EXISTS settings (
                    key_name TEXT PRIMARY KEY,
                    value_text TEXT
                );
                CREATE TABLE IF NOT EXISTS subscribers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT NOT NULL UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ");
            
            // Seed categories
            $pdo->exec("
                INSERT INTO categories (name, icon) VALUES ('Süpermarket', 'shopping_cart');
                INSERT INTO categories (name, icon) VALUES ('Yapı Market', 'home_repair_service');
                INSERT INTO categories (name, icon) VALUES ('Teknoloji', 'devices');
                INSERT INTO categories (name, icon) VALUES ('Kozmetik', 'spa');
                INSERT INTO categories (name, icon) VALUES ('Moda', 'checkroom');
                INSERT INTO categories (name, icon) VALUES ('Anne & Bebek', 'child_care');
            ");
            
            // Seed initial markets
            $pdo->exec("
                INSERT INTO markets (name, slug, logo, description, category_id) VALUES 
                ('BİM', 'bim', 'bim.png', 'BİM Aktüel Ürünler ve İndirim Broşürleri', 1),
                ('A101', 'a101', 'a101.png', 'A101 Aldın Aldın İndirim Kataloğu', 1),
                ('ŞOK', 'sok', 'sok.png', 'ŞOK Haftanın Fırsatları Kataloğu', 1),
                ('Teknosa', 'teknosa', 'teknosa.png', 'Teknosa Teknoloji Kampanyaları', 3);
            ");
            
            // Seed settings
            $pdo->exec("
                INSERT INTO settings (key_name, value_text) VALUES ('social_facebook', '');
                INSERT INTO settings (key_name, value_text) VALUES ('social_instagram', '');
                INSERT INTO settings (key_name, value_text) VALUES ('social_twitter', '');
                INSERT INTO settings (key_name, value_text) VALUES ('social_youtube', '');
                INSERT INTO settings (key_name, value_text) VALUES ('smtp_host', '');
                INSERT INTO settings (key_name, value_text) VALUES ('smtp_port', '');
                INSERT INTO settings (key_name, value_text) VALUES ('smtp_user', '');
                INSERT INTO settings (key_name, value_text) VALUES ('smtp_pass', '');
                INSERT INTO settings (key_name, value_text) VALUES ('smtp_secure', '');
                INSERT INTO settings (key_name, value_text) VALUES ('smtp_from_email', '');
                INSERT INTO settings (key_name, value_text) VALUES ('smtp_from_name', '');
            ");
        }
    } catch (PDOException $e) {
        die("SQLite Veritabanı bağlantı/kurulum hatası: " . $e->getMessage());
    }
} else {
    // Production MySQL config
    $db_host = 'localhost';
    $db_name = 'VERITABANI_ADINIZ'; 
    $db_user = 'VERITABANI_KULLANICINIZ'; 
    $db_pass = 'VERITABANI_SIFRENIZ'; 

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("MySQL Veritabanı bağlantı hatası: " . $e->getMessage());
    }
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