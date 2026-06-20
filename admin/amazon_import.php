<?php
// admin/amazon_import.php - Amazon Product Catalog Creator
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

// Fetch Amazon market from DB
$amazon_stmt = $pdo->query("SELECT * FROM markets WHERE slug = 'amazon' LIMIT 1");
$amazon_market = $amazon_stmt->fetch();

// If Amazon market is not found, automatically recreate it
if (!$amazon_market) {
    try {
        $pdo->exec("INSERT INTO markets (name, slug, logo, description, category_id, is_popular, scraper_active) 
                    VALUES ('Amazon', 'amazon', 'amazon.png', 'Amazon Türkiye güncel indirimleri, aktüel ürünleri ve kampanya broşürleri.', 1, 1, 0)");
        $amazon_stmt = $pdo->query("SELECT * FROM markets WHERE slug = 'amazon' LIMIT 1");
        $amazon_market = $amazon_stmt->fetch();
    } catch (PDOException $e) {
        $error = "Amazon marketi oluşturulamadı: " . $e->getMessage();
    }
}

// Fetch all markets for dropdown
$markets = $pdo->query("SELECT * FROM markets ORDER BY name ASC")->fetchAll();

// Text normalization helper for drawing on JPEGs (ASCII conversion to prevent character rendering anomalies)
function tr_to_ascii($str) {
    $map = [
        'ç' => 'c', 'Ç' => 'C', 'ğ' => 'g', 'Ğ' => 'G', 'ı' => 'i', 'I' => 'I', 'İ' => 'I',
        'ö' => 'o', 'Ö' => 'O', 'ş' => 's', 'Ş' => 'S', 'ü' => 'u', 'Ü' => 'U',
        'â' => 'a', 'î' => 'i', 'û' => 'u'
    ];
    return strtr($str, $map);
}

// Download image using cURL
function download_image_data($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200) ? $data : null;
}

// Scrape HTML from Amazon
function fetch_amazon_page($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
        ]
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $res && !str_contains($res, 'Robot Check') && !str_contains($res, 'captcha')) {
        return $res;
    }
    return null;
}

// Helper to parse price float
function clean_amazon_price($value) {
    if ($value === null) return null;
    $text = trim((string)$value);
    if ($text === '') return null;
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Extract numbers, comma and dots
    $text = preg_replace('/[^0-9,.]+/u', '', $text) ?? '';
    if ($text === '') return null;

    if (str_contains($text, ',') && str_contains($text, '.')) {
        $text = str_replace('.', '', $text);
        $text = str_replace(',', '.', $text);
    } elseif (str_contains($text, ',')) {
        $text = str_replace(',', '.', $text);
    }

    return is_numeric($text) ? (float)$text : null;
}

// HTML Product Parser
function parse_amazon_products($html, $url_base = 'https://www.amazon.com.tr') {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xp = new DOMXPath($doc);
    $products = [];

    // Layout 1: Search Result items
    $items = $xp->query('//div[@data-asin and @data-component-type="s-search-result"] | //div[contains(@class, "s-result-item") and @data-asin != ""]');
    if ($items->length > 0) {
        foreach ($items as $item) {
            $title_node = $xp->query('.//h2//a//span | .//span[contains(@class, "a-size-base-plus")]', $item)->item(0);
            $title = $title_node ? trim($title_node->textContent) : '';
            
            $price_node = $xp->query('.//span[contains(@class, "a-price-whole")]', $item)->item(0);
            $price_fraction = $xp->query('.//span[contains(@class, "a-price-fraction")]', $item)->item(0);
            
            $price = null;
            if ($price_node) {
                $raw_price = trim($price_node->textContent);
                if ($price_fraction) {
                    $raw_price .= ',' . trim($price_fraction->textContent);
                }
                $price = clean_amazon_price($raw_price);
            } else {
                $offscreen = $xp->query('.//span[contains(@class, "a-price")]//span[contains(@class, "a-offscreen")]', $item)->item(0);
                if ($offscreen) {
                    $price = clean_amazon_price($offscreen->textContent);
                }
            }

            $img_node = $xp->query('.//img[contains(@class, "s-image")]', $item)->item(0);
            $image_url = $img_node ? $img_node->getAttribute('src') : '';

            $link_node = $xp->query('.//a[contains(@class, "a-link-normal")]', $item)->item(0);
            $prod_url = $link_node ? $link_node->getAttribute('href') : '';
            if ($prod_url && !str_starts_with($prod_url, 'http')) {
                $prod_url = rtrim($url_base, '/') . '/' . ltrim($prod_url, '/');
            }

            if ($title && $price && $image_url) {
                $products[] = [
                    'title' => $title,
                    'price' => $price,
                    'image_url' => $image_url,
                    'product_url' => $prod_url
                ];
            }
        }
    }

    // Layout 2: Deal cards / Grid cards
    if (empty($products)) {
        $items = $xp->query('//div[@data-testid="grid-deal-card"] | //div[contains(@class, "DealCard-module")] | //div[contains(@class, "deal-card")]');
        foreach ($items as $item) {
            $title_node = $xp->query('.//div[contains(@class, "dealTitle")] | .//span[contains(@class, "a-truncate-full")] | .//a/span', $item)->item(0);
            $title = $title_node ? trim($title_node->textContent) : '';

            $price_node = $xp->query('.//span[contains(@class, "a-price-whole")] | .//div[contains(@class, "priceWithDiscount")]', $item)->item(0);
            $price = $price_node ? clean_amazon_price($price_node->textContent) : null;

            $img_node = $xp->query('.//img', $item)->item(0);
            $image_url = $img_node ? $img_node->getAttribute('src') : '';

            $link_node = $xp->query('.//a', $item)->item(0);
            $prod_url = $link_node ? $link_node->getAttribute('href') : '';
            if ($prod_url && !str_starts_with($prod_url, 'http')) {
                $prod_url = rtrim($url_base, '/') . '/' . ltrim($prod_url, '/');
            }

            if ($title && $price && $image_url) {
                $products[] = [
                    'title' => $title,
                    'price' => $price,
                    'image_url' => $image_url,
                    'product_url' => $prod_url
                ];
            }
        }
    }

    // Layout 3: General tag extraction scanner
    if (empty($products)) {
        $imgs = $xp->query('//img[contains(@src, "/images/I/") or contains(@data-src, "/images/I/")]');
        foreach ($imgs as $img) {
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
            if (!$src) continue;

            $parent = $img->parentNode;
            $title = '';
            $price = null;
            $prod_url = '';

            for ($depth = 0; $depth < 5; $depth++) {
                if (!$parent) break;

                if ($price === null) {
                    $p_node = $xp->query('.//span[contains(@class, "a-price-whole")]', $parent)->item(0);
                    if ($p_node) {
                        $price = clean_amazon_price($p_node->textContent);
                    } else {
                        $spans = $xp->query('.//span | .//div', $parent);
                        foreach ($spans as $span) {
                            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:TL|₺)/u', $span->textContent, $pm)) {
                                $price = clean_amazon_price($pm[0]);
                                break;
                            }
                        }
                    }
                }

                if ($title === '') {
                    $t_node = $xp->query('.//h2 | .//h3 | .//span[contains(@class, "a-text-normal")] | .//div[contains(@class, "title")]', $parent)->item(0);
                    if ($t_node) {
                        $title = trim($t_node->textContent);
                    }
                }

                if ($prod_url === '') {
                    $l_node = $xp->query('.//a[contains(@href, "/dp/") or contains(@href, "/gp/")]', $parent)->item(0);
                    if ($l_node) {
                        $prod_url = $l_node->getAttribute('href');
                        if (!str_starts_with($prod_url, 'http')) {
                            $prod_url = rtrim($url_base, '/') . '/' . ltrim($prod_url, '/');
                        }
                    }
                }
                $parent = $parent->parentNode;
            }

            if ($title && $price && $src) {
                $products[] = [
                    'title' => $title,
                    'price' => $price,
                    'image_url' => $src,
                    'product_url' => $prod_url
                ];
            }
        }
    }

    // Deduplicate list
    $unique = [];
    foreach ($products as $p) {
        $key = trim(strtolower($p['title']));
        if (!isset($unique[$key])) {
            $unique[$key] = $p;
        }
    }
    return array_values($unique);
}

// Generate the physical Grid JPEGs from products array using GD
function compile_amazon_page_image($page_products, $dest_file) {
    $width = 1200;
    $height = 1600;
    $cols = 3;
    $cell_w = 400;
    $cell_h = 400;

    $im = imagecreatetruecolor($width, $height);
    if (!$im) return false;

    // Allocate colors
    $bg_color = imagecolorallocate($im, 248, 250, 252); // bg-slate-50
    $card_bg = imagecolorallocate($im, 255, 255, 255); // White card
    $border_color = imagecolorallocate($im, 226, 232, 240); // slate-200
    $text_dark = imagecolorallocate($im, 15, 23, 42); // slate-900
    $orange_color = imagecolorallocate($im, 255, 153, 0); // Amazon Orange (#FF9900)
    $white_text = imagecolorallocate($im, 255, 255, 255);

    imagefill($im, 0, 0, $bg_color);

    foreach ($page_products as $idx => $p) {
        $col = $idx % $cols;
        $row = floor($idx / $cols);
        $cx = $col * $cell_w;
        $cy = $row * $cell_h;

        // 1. Draw Card Background
        imagefilledrectangle($im, $cx + 15, $cy + 15, $cx + $cell_w - 15, $cy + $cell_h - 15, $card_bg);
        imagerectangle($im, $cx + 15, $cy + 15, $cx + $cell_w - 15, $cy + $cell_h - 15, $border_color);

        // 2. Fetch and Draw Product Image
        $img_data = download_image_data($p['image_url']);
        if ($img_data) {
            $p_img = imagecreatefromstring($img_data);
            if ($p_img) {
                $pw = imagesx($p_img);
                $ph = imagesy($p_img);

                // Max bounds for scaled product image: 320x260
                $max_w = 320;
                $max_h = 260;
                
                $ratio = min($max_w / $pw, $max_h / $ph);
                $sw = (int)($pw * $ratio);
                $sh = (int)($ph * $ratio);

                $dx = $cx + 40 + (int)(($max_w - $sw) / 2);
                $dy = $cy + 30 + (int)(($max_h - $sh) / 2);

                imagecopyresampled($im, $p_img, $dx, $dy, 0, 0, $sw, $sh, $pw, $ph);
                imagedestroy($p_img);
            }
        }

        // 3. Draw Title (Normalized ASCII text to prevent encoding problems)
        $clean_title = tr_to_ascii($p['title']);
        if (strlen($clean_title) > 28) {
            $clean_title = substr($clean_title, 0, 25) . '...';
        }
        // imagestring font sizes: 1 to 5. font 3 is clear
        imagestring($im, 3, $cx + 30, $cy + 310, $clean_title, $text_dark);

        // 4. Draw Price Badge
        $price_str = number_format($p['price'], 2, ',', '.') . ' TL';
        imagefilledrectangle($im, $cx + 30, $cy + 340, $cx + 210, $cy + 372, $orange_color);
        
        // Center text in badge
        $char_w = 7; // approximate width of font 3 character
        $text_w = strlen($price_str) * $char_w;
        $tx = $cx + 30 + (int)((180 - $text_w) / 2);
        imagestring($im, 3, $tx, $cy + 350, $price_str, $white_text);
    }

    $ok = imagejpeg($im, $dest_file, 85);
    imagedestroy($im);
    return $ok;
}

// Form Submission handling
if (isset($_POST['import'])) {
    $market_id = intval($_POST['market_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $import_url = trim($_POST['import_url'] ?? '');
    $pasted_html = trim($_POST['pasted_html'] ?? '');
    $show_on_homepage = isset($_POST['show_on_homepage']) ? 1 : 0;

    if ($market_id === 0) {
        $error = "Lütfen bir market seçin.";
    } elseif (empty($title)) {
        $error = "Lütfen broşür için bir başlık belirtin.";
    } elseif (empty($import_url) && empty($pasted_html)) {
        $error = "Lütfen bir Amazon linki girin ya da sayfa kaynağını (HTML) yapıştırın.";
    } else {
        $html = '';
        if (!empty($pasted_html)) {
            $html = $pasted_html;
        } else {
            $html = fetch_amazon_page($import_url);
            if (!$html) {
                $error = "Amazon otomatik erişimi engelledi (Robot/CAPTCHA doğrulaması). Lütfen Amazon sayfasına tarayıcınızdan gidip sayfa kaynağını kopyalayın ve aşağıdaki 'Sayfa Kaynağı' alanına yapıştırarak tekrar deneyin.";
            }
        }

        if (empty($error)) {
            $products = parse_amazon_products($html);
            $p_count = count($products);
            
            if ($p_count === 0) {
                $error = "HTML içeriğinden herhangi bir ürün bilgisi ayıklanamadı. Lütfen yapıştırdığınız kodun tam sayfa kaynağı (HTML) olduğundan ve ürün listesi içerdiğinden emin olun.";
            } else {
                try {
                    $pdo->beginTransaction();

                    if (empty($start_date)) $start_date = date('Y-m-d');
                    if (empty($end_date)) $end_date = date('Y-m-d', strtotime('+7 days'));

                    // We will set a temporary cover, and replace it once generated
                    $cover_image = 'uploads/brochures/placeholder_cover.png';
                    $stmt = $pdo->prepare("INSERT INTO brochures (market_id, title, cover_image, start_date, end_date, show_on_homepage, analyzed_at, source_name, source_url) 
                                           VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 'amazon', ?)");
                    $stmt->execute([$market_id, $title, $cover_image, $start_date, $end_date, $show_on_homepage, $import_url]);
                    $brochure_id = $pdo->lastInsertId();

                    // Group products by 12 items per page
                    $chunked_products = array_chunk($products, 12);
                    $total_pages = count($chunked_products);
                    
                    if (!is_dir('../uploads/brochures')) {
                        mkdir('../uploads/brochures', 0755, true);
                    }

                    $generated_pages = 0;
                    foreach ($chunked_products as $p_idx => $page_products) {
                        $page_num = $p_idx + 1;
                        $filename = 'amazon-' . $brochure_id . '-' . $page_num . '-' . time() . '.jpg';
                        $dest_path = '../uploads/brochures/' . $filename;
                        $db_path = 'uploads/brochures/' . $filename;

                        // Compile image grid
                        if (compile_amazon_page_image($page_products, $dest_path)) {
                            // Insert page
                            $p_stmt = $pdo->prepare("INSERT INTO brochure_pages (brochure_id, page_number, image_path) VALUES (?, ?, ?)");
                            $p_stmt->execute([$brochure_id, $page_num, $db_path]);

                            // Insert products hotspots coordinates (each grid cell has a predefined hotspot)
                            $prod_stmt = $pdo->prepare("INSERT INTO brochure_products (brochure_id, page_number, product_name, price, original_price, unit, x_pct, y_pct, w_pct, h_pct, analyzed_at) 
                                                        VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                            
                            foreach ($page_products as $c_idx => $prod) {
                                $col = $c_idx % 3;
                                $row = floor($c_idx / 3);
                                
                                $x_pct = round((($col * 400) + 15) / 1200 * 100, 3);
                                $y_pct = round((($row * 400) + 15) / 1600 * 100, 3);
                                $w_pct = round(370 / 1200 * 100, 3);
                                $h_pct = round(370 / 1600 * 100, 3);

                                $prod_stmt->execute([
                                    $brochure_id, 
                                    $page_num, 
                                    $prod['title'], 
                                    $prod['price'], 
                                    $x_pct, 
                                    $y_pct, 
                                    $w_pct, 
                                    $h_pct
                                ]);
                            }

                            // Update brochure cover image with the first page
                            if ($page_num === 1) {
                                $cover_image = $db_path;
                                $up_stmt = $pdo->prepare("UPDATE brochures SET cover_image = ? WHERE id = ?");
                                $up_stmt->execute([$cover_image, $brochure_id]);
                            }

                            $generated_pages++;
                        }
                    }

                    if ($generated_pages > 0) {
                        $pdo->commit();
                        $success = "Başarılı! Toplam $p_count adet Amazon ürünü başarıyla içe aktarıldı ve $generated_pages sayfalık interaktif broşür oluşturuldu. <a href='../viewer.php?id=$brochure_id' target='_blank' class='underline font-bold text-white ml-2'>Broşürü Görüntüle &raquo;</a>";
                    } else {
                        $pdo->rollBack();
                        $error = "Broşür resimleri oluşturulurken bir hata oluştu.";
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Veritabanı kayıt hatası: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amazon Broşür Oluşturucu - marketisleri.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../uploads/tailwind.min.css">
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shrink-0">
        <div class="p-6 border-b border-slate-800">
            <a href="index.php" class="font-title text-xl font-black text-white flex items-center gap-2">
                <?php if (file_exists('../uploads/logo.png')): ?>
                    <img src="../uploads/logo.png" alt="marketisleri.com" class="h-8 w-auto object-contain">
                <?php else: ?>
                    <span class="text-red-500 material-symbols-outlined">dashboard</span>
                    marketisleri<span class="text-red-500">.panel</span>
                <?php endif; ?>
            </a>
        </div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">space_dashboard</span> Dashboard
            </a>
            <a href="markets.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">storefront</span> Marketler
            </a>
            <a href="brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">menu_book</span> Broşürler
            </a>
            <a href="magic_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">auto_fix</span> Sihirli Broşür Ekle
            </a>
            <a href="amazon_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">shopping_basket</span> Amazon Broşür Ekle
            </a>
            <a href="cron_setup.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">schedule</span> Otomasyon &amp; Cron
            </a>
            <a href="apply_scrapers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">build</span> Scraper Ayarları
            </a>
            <a href="analyze_brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">explore</span> Broşür AI Analizi
            </a>
            <a href="blogs.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">article</span> Blog Yazıları
            </a>
            <a href="subscribers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">mail</span> Aboneler
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">settings</span> Ayarlar
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-950/20 hover:text-red-300 transition-all font-semibold">
                <span class="material-symbols-outlined text-lg">logout</span> Oturumu Kapat
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        <header class="h-20 bg-slate-900/40 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-8 shrink-0">
            <h1 class="font-title text-2xl font-bold text-white">Amazon Broşür Oluşturucu</h1>
        </header>

        <div class="p-8 max-w-4xl w-full mx-auto space-y-6">
            <?php if ($success): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm p-4 rounded-2xl flex items-start gap-3">
                    <span class="material-symbols-outlined text-emerald-400 mt-0.5">check_circle</span>
                    <div><?= $success ?></div>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-200 text-sm p-4 rounded-2xl flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-400 mt-0.5">error</span>
                    <div><?= $error ?></div>
                </div>
            <?php endif; ?>

            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-6">
                <div class="border-b border-slate-800 pb-4">
                    <h2 class="font-title text-xl font-bold text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-500">shopping_basket</span>
                        Amazon İndirim Linki veya HTML Kodundan Broşür Üret
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Gireceğiniz bir Amazon arama/fırsat linkindeki ürünler veya yapıştıracağınız sayfa kaynağındaki (HTML) tüm indirimli ürünler otomatik olarak ayıklanır, görselleri çekilir ve 12'şerli kartlar halinde interaktif sayfalara dönüştürülür.
                    </p>
                </div>

                <form method="POST" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Hedef Market</label>
                            <select name="market_id" class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition text-sm">
                                <?php foreach ($markets as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= ($m['slug'] === 'amazon') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Broşür Başlığı *</label>
                            <input type="text" name="title" required value="Amazon Fırsat Kataloğu"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition text-sm"
                                   placeholder="Örn: Amazon Haftalık Fırsatları">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Başlangıç Tarihi</label>
                            <input type="date" name="start_date" value="<?= date('Y-m-d') ?>"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Bitiş Tarihi</label>
                            <input type="date" name="end_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Amazon Sayfa URL'si</label>
                        <input type="url" name="import_url"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition text-sm"
                               placeholder="Örn: https://www.amazon.com.tr/s?k=filtre+kahve">
                        <p class="text-[10px] text-slate-500 mt-1">Sistem otomatik olarak bu adrese cURL ile bağlanmayı dener. Bot koruması devreye girerse aşağıdaki HTML alanını kullanın.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Sayfa Kaynağı (HTML) - Bot Koruması Fallback</label>
                        <textarea name="pasted_html" rows="8"
                                  class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none font-mono text-xs transition"
                                  placeholder="Tarayıcıda sayfaya gidip sağ tıklayın, 'Sayfa Kaynağını Görüntüle' diyerek tüm kodu kopyalayıp buraya yapıştırın..."></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="show_on_homepage" id="show_on_homepage" value="1" checked
                               class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-red-600 focus:ring-red-500 focus:ring-offset-slate-900">
                        <label for="show_on_homepage" class="text-xs font-semibold text-slate-300">Anasayfada gösterilsin</label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="submit" name="import"
                                class="bg-red-600 hover:bg-red-500 text-white font-bold px-6 py-2.5 rounded-xl transition shadow-lg shadow-red-600/10 flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">settings_suggest</span>
                            Broşürü Çıkar ve Grid Sayfaları Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

</body>
</html>
