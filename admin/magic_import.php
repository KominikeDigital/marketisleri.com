<?php
// admin/magic_import.php - Magic Brochure Importer
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

// Helper to resolve relative URL to absolute URL
function resolveUrl($base, $rel) {
    if (empty($rel)) return $base;
    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
    if ($rel[0] == '/' && isset($rel[1]) && $rel[1] == '/') {
        return parse_url($base, PHP_URL_SCHEME) . ':' . $rel;
    }
    
    $parts = parse_url($base);
    $host = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port'])) {
        $host .= ':' . $parts['port'];
    }
    
    if ($rel[0] == '/') {
        return $host . $rel;
    }
    
    $path = dirname($parts['path'] ?? '/');
    if ($path == '/') $path = '';
    return $host . $path . '/' . ltrim($rel, '/');
}

// Helper to download file using cURL
function downloadFile($url, $dest) {
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code === 200 && $data) {
        return file_put_contents($dest, $data) !== false;
    }
    return false;
}

// Helper to scrape HTML images
function scrapeWebpageImages($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code !== 200 || !$html) {
        return [];
    }
    
    $images = [];
    
    // 1. Specific parser: indirimcipanda.com
    if (strpos($url, 'indirimcipanda.com') !== false) {
        preg_match_all('/https?:\/\/[^\s\'"]+?\.(?:jpg|jpeg|png|webp|gif)/i', $html, $matches);
        if (!empty($matches[0])) {
            $unique_urls = array_unique($matches[0]);
            foreach ($unique_urls as $img_url) {
                if (strpos($img_url, 'cloudfront') !== false && (strpos($img_url, 'brochures') !== false || strpos($img_url, 'brosur') !== false)) {
                    $images[] = $img_url;
                }
            }
        }
        
        // Sort using order digits
        usort($images, function($a, $b) {
            preg_match('/order(\d+)/i', $a, $matchA);
            preg_match('/order(\d+)/i', $b, $matchB);
            $orderA = isset($matchA[1]) ? intval($matchA[1]) : 0;
            $orderB = isset($matchB[1]) ? intval($matchB[1]) : 0;
            return $orderA - $orderB;
        });
    } else {
        // 2. Generic page parser
        preg_match_all('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|webp|gif))["\']/i', $html, $matches);
        if (!empty($matches[1])) {
            $unique_urls = array_unique($matches[1]);
            foreach ($unique_urls as $img_url) {
                $absolute_url = resolveUrl($url, $img_url);
                $lower_url = strtolower($absolute_url);
                if (strpos($lower_url, 'logo') === false && 
                    strpos($lower_url, 'icon') === false && 
                    strpos($lower_url, 'avatar') === false && 
                    strpos($lower_url, 'header') === false &&
                    strpos($lower_url, 'footer') === false &&
                    strpos($lower_url, 'theme') === false &&
                    strpos($lower_url, 'banner') === false) {
                    $images[] = $absolute_url;
                }
            }
        }
    }
    
    return $images;
}

// Handle Form Submission
if (isset($_POST['import'])) {
    $market_id = intval($_POST['market_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $import_url = trim($_POST['import_url'] ?? '');
    
    if ($market_id === 0) {
        $error = "Lütfen bir market seçin.";
    } else {
        try {
            // Fetch Market Details
            $m_stmt = $pdo->prepare("SELECT * FROM markets WHERE id = ?");
            $m_stmt->execute([$market_id]);
            $market = $m_stmt->fetch();
            if (!$market) {
                throw new Exception("Seçilen market veritabanında bulunamadı.");
            }
            
            // Resolve missing Dates
            if (empty($start_date)) {
                $start_date = date('Y-m-d');
            }
            if (empty($end_date)) {
                $end_date = date('Y-m-d', strtotime('+7 days'));
            }
            
            // Resolve missing Title
            if (empty($title)) {
                $title = $market['name'] . ' ' . date('d.m.Y', strtotime($start_date)) . ' Kataloğu';
            }
            
            $cover_name = '';
            $pdf_name = null;
            $pages_to_insert = []; // Array of downloaded file names
            $content_type = 'images';
            
            // 1. Process custom Cover image upload if provided
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['cover_image']['tmp_name'];
                $file_name = $_FILES['cover_image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (in_array($file_ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                    $cover_name = 'cover-' . time() . '-' . rand(100, 999) . '.' . $file_ext;
                    move_uploaded_file($file_tmp, '../uploads/brochures/' . $cover_name);
                }
            }
            
            // 2. Identify the asset source type
            
            // CASE A: URL Link provided
            if (!empty($import_url)) {
                // Direct PDF URL
                if (preg_match('/\.pdf$/i', $import_url)) {
                    $content_type = 'pdf';
                    $pdf_name = 'brochure-magic-' . time() . '.pdf';
                    $pdf_dest = '../uploads/brochures/pdfs/' . $pdf_name;
                    
                    if (!downloadFile($import_url, $pdf_dest)) {
                        throw new Exception("PDF linkinden dosya indirilemedi: " . htmlspecialchars($import_url));
                    }
                } 
                // Direct Image URL
                elseif (preg_match('/\.(jpg|jpeg|png|webp)$/i', $import_url)) {
                    $content_type = 'images';
                    $file_ext = strtolower(pathinfo($import_url, PATHINFO_EXTENSION));
                    $img_filename = 'page-magic-1-' . time() . '.' . $file_ext;
                    $img_dest = '../uploads/brochures/pages/' . $img_filename;
                    
                    if (!downloadFile($import_url, $img_dest)) {
                        throw new Exception("Görsel linkinden resim indirilemedi: " . htmlspecialchars($import_url));
                    }
                    $pages_to_insert[] = $img_filename;
                    
                    // If no cover uploaded, use this image as cover too
                    if (empty($cover_name)) {
                        $cover_name = 'cover-magic-' . time() . '.' . $file_ext;
                        copy($img_dest, '../uploads/brochures/' . $cover_name);
                    }
                } 
                // Web Page Scraper
                else {
                    $content_type = 'images';
                    $scraped_images = scrapeWebpageImages($import_url);
                    if (empty($scraped_images)) {
                        throw new Exception("Girdiğiniz linkten hiçbir broşür görseli ayıklanamadı. Lütfen linki kontrol edin.");
                    }
                    
                    $timestamp = time();
                    $page_num = 1;
                    
                    foreach ($scraped_images as $img_url) {
                        $file_ext = strtolower(pathinfo($img_url, PATHINFO_EXTENSION));
                        if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                            $file_ext = 'webp'; // fallback extension
                        }
                        
                        $img_filename = 'page-magic-' . $page_num . '-' . $timestamp . '.' . $file_ext;
                        $img_dest = '../uploads/brochures/pages/' . $img_filename;
                        
                        if (downloadFile($img_url, $img_dest)) {
                            $pages_to_insert[] = $img_filename;
                            
                            // Set cover if none exists
                            if (empty($cover_name) && $page_num === 1) {
                                $cover_name = 'cover-magic-' . $timestamp . '.' . $file_ext;
                                copy($img_dest, '../uploads/brochures/' . $cover_name);
                            }
                            $page_num++;
                        }
                    }
                    
                    if (empty($pages_to_insert)) {
                        throw new Exception("Siteden görseller ayıklandı fakat sunucuya indirilemedi.");
                    }
                }
            } 
            // CASE B: Local PDF upload
            elseif (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $content_type = 'pdf';
                $file_tmp = $_FILES['pdf_file']['tmp_name'];
                $file_name = $_FILES['pdf_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if ($file_ext === 'pdf') {
                    $pdf_name = 'brochure-magic-' . time() . '.pdf';
                    move_uploaded_file($file_tmp, '../uploads/brochures/pdfs/' . $pdf_name);
                } else {
                    throw new Exception("Seçtiğiniz dosya PDF formatında olmalıdır.");
                }
            } 
            // CASE C: Local Images upload
            elseif (isset($_FILES['image_pages']) && !empty($_FILES['image_pages']['name'][0])) {
                $content_type = 'images';
                $files = $_FILES['image_pages'];
                $count = count($files['name']);
                
                $file_array = [];
                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_array[] = [
                            'name' => $files['name'][$i],
                            'tmp_name' => $files['tmp_name'][$i]
                        ];
                    }
                }
                
                // Sort naturally by filename
                usort($file_array, function($a, $b) {
                    return strnatcmp($a['name'], $b['name']);
                });
                
                $timestamp = time();
                $page_num = 1;
                
                foreach ($file_array as $file) {
                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (in_array($file_ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                        $img_filename = 'page-magic-' . $page_num . '-' . $timestamp . '.' . $file_ext;
                        $img_dest = '../uploads/brochures/pages/' . $img_filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $img_dest)) {
                            $pages_to_insert[] = $img_filename;
                            
                            // Set cover if none exists
                            if (empty($cover_name) && $page_num === 1) {
                                $cover_name = 'cover-magic-' . $timestamp . '.' . $file_ext;
                                copy($img_dest, '../uploads/brochures/' . $cover_name);
                            }
                            $page_num++;
                        }
                    }
                }
                
                if (empty($pages_to_insert)) {
                    throw new Exception("Seçtiğiniz görseller yüklenirken bir hata oluştu.");
                }
            } 
            else {
                throw new Exception("Lütfen bir Broşür Linki (URL) girin, bir PDF yükleyin veya Görseller seçin.");
            }
            
            // 3. Cover Fallback (in case of PDF / direct imports where cover is missing)
            if (empty($cover_name)) {
                // Copy market logo as cover if available
                if ($market['logo'] && file_exists('../uploads/markets/' . $market['logo'])) {
                    $m_logo_ext = pathinfo($market['logo'], PATHINFO_EXTENSION);
                    $cover_name = 'cover-logo-magic-' . time() . '.' . $m_logo_ext;
                    copy('../uploads/markets/' . $market['logo'], '../uploads/brochures/' . $cover_name);
                } else {
                    $cover_name = 'default_cover.png'; // final fallback
                }
            }
            
            // 4. Database Insertion
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO brochures (market_id, title, cover_image, start_date, end_date, pdf_path) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$market_id, $title, $cover_name, $start_date, $end_date, $pdf_name]);
            $brochure_id = $pdo->lastInsertId();
            
            if ($content_type === 'images' && !empty($pages_to_insert)) {
                $ins_page = $pdo->prepare("INSERT INTO brochure_pages (brochure_id, page_number, image_path) VALUES (?, ?, ?)");
                $page_order = 1;
                foreach ($pages_to_insert as $page_img) {
                    $ins_page->execute([$brochure_id, $page_order, $page_img]);
                    $page_order++;
                }
            }
            
            $pdo->commit();
            $success = "Broşür sihirli bir şekilde başarıyla içe aktarıldı! (Sistem ID: " . $brochure_id . ")";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Hata oluştu: " . $e->getMessage();
        }
    }
}

// Fetch Markets
$markets = $pdo->query("SELECT * FROM markets ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sihirli Broşür Ekle - marketisleri.com</title>
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
                <span class="material-symbols-outlined text-lg">space_dashboard</span>
                Dashboard
            </a>
            <a href="markets.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">storefront</span>
                Marketler
            </a>
            <a href="brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">menu_book</span>
                Broşürler
            </a>
            <a href="magic_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">auto_fix</span>
                Sihirli Broşür Ekle
            </a>
            <a href="subscribers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">mail</span>
                Aboneler
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">settings</span>
                Ayarlar
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-950/20 hover:text-red-300 transition-all font-semibold">
                <span class="material-symbols-outlined text-lg">logout</span>
                Oturumu Kapat
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        <!-- Header -->
        <header class="h-20 bg-slate-900/40 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-8 shrink-0">
            <h1 class="font-title text-2xl font-bold text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-red-500 animate-pulse">auto_fix</span>
                Sihirli Broşür Ekle (Magic Importer)
            </h1>
        </header>

        <!-- Container -->
        <div class="p-8 space-y-8 max-w-4xl w-full mx-auto">
            <!-- Messages -->
            <?php if ($success): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm p-4 rounded-2xl flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-200 text-sm p-4 rounded-2xl flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full bg-red-500 shrink-0"></span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-xl space-y-6">
                <div>
                    <h3 class="font-title text-xl font-bold text-white mb-2">Hızlı / Sihirli Broşür Yükleyici</h3>
                    <p class="text-slate-400 text-sm">
                        Bu alandan bir katalog sayfası linki verebilir veya PDF/Çoklu Görseller yükleyebilirsiniz. Sistem, link tipini (PDF, görsel veya web sayfası) otomatik algılayıp broşür sayfalarını indirerek ilgili markete ekleyecektir.
                    </p>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Market Selector -->
                        <div>
                            <label for="market_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Market / Marka *</label>
                            <select id="market_id" name="market_id" required
                                    class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition text-sm">
                                <option value="">Bir Market Seçin</option>
                                <?php foreach ($markets as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Katalog Başlığı (Opsiyonel)</label>
                            <input type="text" id="title" name="title"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition text-sm"
                                   placeholder="Boş bırakılırsa otomatik oluşturulur">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Start Date -->
                        <div>
                            <label for="start_date" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Başlangıç Tarihi (Opsiyonel)</label>
                            <input type="date" id="start_date" name="start_date"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition text-sm">
                        </div>

                        <!-- End Date -->
                        <div>
                            <label for="end_date" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Bitiş Tarihi (Opsiyonel)</label>
                            <input type="date" id="end_date" name="end_date"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition text-sm">
                        </div>
                    </div>

                    <!-- Cover Image Upload -->
                    <div>
                        <label for="cover_image" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Katalog Kapak Resmi (Opsiyonel)</label>
                        <input type="file" id="cover_image" name="cover_image" accept="image/*"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 text-slate-300 rounded-xl px-4 py-2 outline-none text-sm file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-800 file:text-white hover:file:bg-slate-700">
                        <p class="text-xs text-slate-500 mt-2">Boş bırakılırsa yüklenen görsellerin ilki, PDF'lerde ise market logosu kapak resmi olarak atanır.</p>
                    </div>

                    <div class="border-t border-slate-800 pt-6">
                        <h4 class="font-title text-base font-bold text-white mb-4">Broşür Kaynağı (Sadece birini kullanın)</h4>
                        
                        <div class="space-y-4">
                            <!-- Link URL -->
                            <div>
                                <label for="import_url" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Seçenek 1: Broşür Sayfası veya Asset Linki (URL)</label>
                                <input type="url" id="import_url" name="import_url"
                                       class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition text-sm"
                                       placeholder="Örn: https://indirimcipanda.com/brosur/migros... veya direkt PDF / Resim linki">
                                <p class="text-xs text-slate-500 mt-2">
                                    <strong>Desteklenen tipler:</strong> `indirimcipanda.com` broşür sayfaları, direkt `.pdf` linkleri, direkt `.jpg/.png` görsel linkleri.
                                </p>
                            </div>

                            <div class="flex items-center justify-center py-2">
                                <span class="text-xs font-bold text-slate-600 uppercase">VEYA</span>
                            </div>

                            <!-- PDF Upload -->
                            <div>
                                <label for="pdf_file" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Seçenek 2: PDF Dosyası Yükle</label>
                                <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf"
                                       class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 text-slate-300 rounded-xl px-4 py-2 outline-none text-sm file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-800 file:text-white hover:file:bg-slate-700">
                            </div>

                            <div class="flex items-center justify-center py-2">
                                <span class="text-xs font-bold text-slate-600 uppercase">VEYA</span>
                            </div>

                            <!-- Images Upload -->
                            <div>
                                <label for="image_pages" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Seçenek 3: Çoklu Görsel Dosyaları Yükle</label>
                                <input type="file" id="image_pages" name="image_pages[]" multiple accept="image/*"
                                       class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 text-slate-300 rounded-xl px-4 py-2 outline-none text-sm file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-800 file:text-white hover:file:bg-slate-700">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-slate-800">
                        <button type="reset" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold px-5 py-2.5 rounded-xl transition">
                            Formu Temizle
                        </button>
                        <button type="submit" name="import" 
                                class="bg-red-600 hover:bg-red-500 text-white font-bold px-8 py-2.5 rounded-xl transition shadow-lg shadow-red-600/10 flex items-center gap-2">
                            <span class="material-symbols-outlined text-base">auto_fix</span>
                            Sihirli Broşürü İçe Aktar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
