SET NAMES utf8mb4;
SET time_zone = '+03:00';

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS markets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    logo VARCHAR(255),
    description TEXT,
    category_id INT,
    CONSTRAINT fk_markets_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS brochures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    market_id INT,
    title VARCHAR(255) NOT NULL,
    cover_image VARCHAR(255),
    pdf_path VARCHAR(255) DEFAULT NULL,
    source_name VARCHAR(50) DEFAULT NULL,
    source_url VARCHAR(500) DEFAULT NULL,
    source_uid VARCHAR(100) DEFAULT NULL,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_brochures_market
        FOREIGN KEY (market_id) REFERENCES markets(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS brochure_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brochure_id INT,
    page_number INT,
    image_path VARCHAR(255) NOT NULL,
    CONSTRAINT fk_pages_brochure
        FOREIGN KEY (brochure_id) REFERENCES brochures(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS brochure_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brochure_id INT NOT NULL,
    page_number INT NOT NULL DEFAULT 1,
    product_name VARCHAR(500) NOT NULL,
    price DECIMAL(10,2) DEFAULT NULL,
    original_price DECIMAL(10,2) DEFAULT NULL,
    unit VARCHAR(100) DEFAULT NULL,
    product_url VARCHAR(1000) DEFAULT NULL,
    product_image VARCHAR(500) DEFAULT NULL,
    product_images TEXT,
    rating VARCHAR(20) DEFAULT NULL,
    review_count VARCHAR(50) DEFAULT NULL,
    description TEXT,
    x_pct FLOAT DEFAULT NULL,
    y_pct FLOAT DEFAULT NULL,
    w_pct FLOAT DEFAULT NULL,
    h_pct FLOAT DEFAULT NULL,
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_brochure
        FOREIGN KEY (brochure_id) REFERENCES brochures(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    key_name VARCHAR(100) PRIMARY KEY,
    value_text TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (id, name, icon) VALUES
(1, 'Süpermarket', 'shopping_cart'),
(2, 'Yapı Market', 'home_repair_service'),
(3, 'Teknoloji', 'devices'),
(4, 'Kozmetik', 'spa'),
(5, 'Moda', 'checkroom'),
(6, 'Anne & Bebek', 'child_care')
ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon);

INSERT INTO markets (id, name, slug, logo, description, category_id) VALUES
(1, 'BİM', 'bim', 'bim.png', 'BİM Aktüel Ürünler ve İndirim Broşürleri', 1),
(2, 'A101', 'a101', 'a101.png', 'A101 Aldın Aldın İndirim Kataloğu', 1),
(3, 'ŞOK', 'sok', 'sok.png', 'ŞOK Haftanın Fırsatları Kataloğu', 1),
(4, 'Teknosa', 'teknosa', 'teknosa.png', 'Teknosa Teknoloji Kampanyaları', 3)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    slug = VALUES(slug),
    logo = VALUES(logo),
    description = VALUES(description),
    category_id = VALUES(category_id);

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
('seo_title_home', 'Tüm Market Broşürleri Tek Yerde | marketisleri.com'),
('seo_description_home', 'BİM, A101, ŞOK, Migros ve diğer süpermarketlerin en güncel broşürleri, aktüel ürün katalogları ve haftalık indirimleri tek bir yerde!'),
('seo_keywords_home', 'market broşürleri, aktüel ürünler, bim aktüel, a101 aktüel, şok katalog, haftalık indirimler, indirim broşürleri')
ON DUPLICATE KEY UPDATE value_text = VALUES(value_text);
