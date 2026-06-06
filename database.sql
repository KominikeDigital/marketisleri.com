CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    icon VARCHAR(100)
);

CREATE TABLE markets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    slug VARCHAR(100),
    logo VARCHAR(255),
    description TEXT,
    category_id INT,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE brochures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    market_id INT,
    title VARCHAR(255),
    cover_image VARCHAR(255),
    pdf_path VARCHAR(255) DEFAULT NULL,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (market_id) REFERENCES markets(id)
);

CREATE TABLE brochure_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brochure_id INT,
    page_number INT,
    image_path VARCHAR(255),
    FOREIGN KEY (brochure_id) REFERENCES brochures(id)
);

INSERT INTO categories (name, icon) VALUES 
('Süpermarket', 'shopping_cart'), ('Yapı Market', 'home_repair_service'), 
('Teknoloji', 'devices'), ('Kozmetik', 'genetics'), ('Moda', 'styler'), ('Anne & Bebek', 'toys');

CREATE TABLE settings (
    key_name VARCHAR(100) PRIMARY KEY,
    value_text TEXT
);

CREATE TABLE subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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
('seo_keywords_home', 'market broşürleri, aktüel ürünler, bim aktüel, a101 aktüel, şok katalog, haftalık indirimler, indirim broşürleri');