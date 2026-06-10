<?php
require 'config.php';

// Fetch all settings
$settings_stmt = $pdo->query("SELECT * FROM settings");
$site_settings = [];
while ($row = $settings_stmt->fetch()) {
    $site_settings[$row['key_name']] = $row['value_text'];
}
$social_settings = $site_settings; // backward compatibility

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch brochure metadata with market info
$stmt = $pdo->prepare("SELECT b.*, m.name as market_name, m.logo as market_logo, m.slug as market_slug, m.description as market_desc, m.id as market_id_val
                       FROM brochures b 
                       JOIN markets m ON b.market_id = m.id 
                       WHERE b.id = ?");
$stmt->execute([$id]);
$brochure = $stmt->fetch();

if (!$brochure) {
    die("Broşür bulunamadı! <a href='index.php'>Anasayfaya Dön</a>");
}

$today = date('Y-m-d');
$is_pdf = !empty($brochure['pdf_path']);
$pages = [];

if (!$is_pdf) {
    $pages_stmt = $pdo->prepare("SELECT * FROM brochure_pages WHERE brochure_id = ? ORDER BY page_number ASC");
    $pages_stmt->execute([$id]);
    $pages = $pages_stmt->fetchAll();
}

// Check if Gemini API key is configured
$gemini_configured = !empty($site_settings['gemini_api_key']);

// Pre-load products for first page (if analyzed)
$first_page_products = [];
if (!$is_pdf && !empty($pages)) {
    $prod_stmt = $pdo->prepare(
        "SELECT * FROM brochure_products WHERE brochure_id = ? AND page_number = 1 ORDER BY y_pct ASC, x_pct ASC"
    );
    $prod_stmt->execute([$id]);
    $first_page_products = $prod_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-CEY5MRFRRL"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-CEY5MRFRRL');
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?= htmlspecialchars($brochure['market_name']) ?> - <?= htmlspecialchars($brochure['title']) ?> | Tüm Market Broşürleri Tek Yerde</title>
    <meta name="description" content="<?= htmlspecialchars($brochure['market_name']) ?> en güncel kataloğu. Geçerlilik Tarihi: <?= date('d.m.Y', strtotime($brochure['start_date'])) ?> - <?= date('d.m.Y', strtotime($brochure['end_date'])) ?>. Aktüel indirimleri kaçırmayın!">
    
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($site_url) ?>/uploads/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="uploads/tailwind.min.css">
    
    <?php if ($is_pdf): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <?php endif; ?>

    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8595320911699983" crossorigin="anonymous"></script>

    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* Sticky Header Transitions */
        header.sticky-header {
            transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        header.sticky-header .header-container {
            height: 112px !important; /* h-28 equivalent (desktop 2x scale) */
            transition: height 0.3s ease !important;
        }
        header.sticky-header .logo-img {
            height: 88px !important; /* Larger logo on load */
            transition: height 0.3s ease !important;
        }
        @media (max-width: 768px) {
            header.sticky-header .header-container {
                height: 80px !important; /* h-20 equivalent on mobile */
            }
            header.sticky-header .logo-img {
                height: 56px !important; /* h-14 on mobile */
            }
        }
        header.sticky-header.scrolled {
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            background-color: rgba(255, 255, 255, 0.95);
        }
        header.sticky-header.scrolled .header-container {
            height: 64px !important; /* h-16 equivalent when scrolled */
        }
        header.sticky-header.scrolled .logo-img {
            height: 48px !important; /* h-12 equivalent when scrolled */
        }
        
        .thumbnail-btn.active-thumb {
            border-color: #dc2626 !important;
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(220, 38, 38, 0.15);
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* ── Product Overlay ── */
        #page-wrapper {
            position: relative;
            display: inline-block;
            max-height: 75vh;
            cursor: crosshair;
        }
        #mainImg {
            max-height: 75vh;
            max-width: 100%;
            width: auto;
            display: block;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
        }
        #product-overlay {
            position: absolute;
            inset: 0;
            border-radius: 1rem;
            pointer-events: none;
        }
        .product-hotspot {
            position: absolute;
            border: 1.5px dashed rgba(239, 68, 68, 0.45);
            background: rgba(239, 68, 68, 0.03);
            border-radius: 6px;
            cursor: pointer;
            pointer-events: all;
            transition: background .15s, border-color .15s, transform .15s;
            box-sizing: border-box;
        }
        .product-hotspot:hover {
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.8);
            transform: scale(1.02);
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.2);
        }
        .product-hotspot.active {
            background: rgba(239, 68, 68, 0.18);
            border-color: #ef4444;
        }

        /* ── Zoom Modal ── */
        #zoom-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9000;
            background: rgba(0,0,0,.55);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        #zoom-modal.open { display: flex; }
        #zoom-box {
            background: white;
            border-radius: 20px;
            padding: 0;
            max-width: 860px;
            width: 100%;
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: 0 24px 80px rgba(0,0,0,.3);
            display: flex;
            flex-direction: column;
            animation: zoomIn .22s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes zoomIn {
            from { opacity:0; transform: scale(.88); }
            to   { opacity:1; transform: scale(1); }
        }
        #zoom-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px 0;
            gap: 12px;
        }
        #zoom-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            padding: 18px 22px 22px;
        }
        @media (max-width: 600px) {
            #zoom-content { grid-template-columns: 1fr; }
        }
        #zoom-canvas-wrap {
            background: #f8fafc;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
        }
        #zoom-canvas {
            max-width: 100%;
            max-height: 340px;
            object-fit: contain;
            border-radius: 8px;
        }

        /* Price comparison list */
        .compare-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            transition: background .15s;
        }
        .compare-item:hover { background: #f1f5f9; }
        .compare-item .logo-wrap {
            width: 36px; height: 36px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            display: flex; align-items: center; justify-content: center;
            background: white; flex-shrink: 0; overflow: hidden;
        }
        .compare-item .logo-wrap img { width: 100%; height: 100%; object-fit: contain; }
        .compare-badge-cheap {
            font-size: 10px; font-weight: 700;
            background: #dcfce7; color: #166534;
            padding: 2px 8px; border-radius: 999px;
        }
        .compare-badge-expensive {
            font-size: 10px; font-weight: 700;
            background: #fee2e2; color: #991b1b;
            padding: 2px 8px; border-radius: 999px;
        }

        /* Alert form */
        #alert-form input[type=email],
        #alert-form input[type=number] {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            width: 100%;
            outline: none;
            transition: border-color .15s;
        }
        #alert-form input:focus { border-color: #ef4444; }

        /* Loading skeleton */
        .skeleton { 
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 6px;
        }
        @keyframes shimmer {
            0%   { background-position: -200% 0; }
            100% { background-position:  200% 0; }
        }

        /* Pulse indicator when analysis running */
        .analyzing-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff7ed; color: #c2410c;
            border: 1px solid #fed7aa; border-radius: 999px;
            padding: 4px 12px; font-size: 12px; font-weight: 600;
        }
        .dot-pulse {
            width: 8px; height: 8px; border-radius: 50%;
            background: #f97316;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .3; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-red-500 selection:text-white">

    <!-- Header -->
    <header class="bg-white/80 backdrop-blur-md border-b border-slate-100 sticky top-0 z-50 sticky-header">
        <div class="max-w-7xl mx-auto px-4 md:px-6 h-20 flex items-center justify-between header-container">
            <a href="index.php" class="flex items-center gap-2">
                <?php if (file_exists('uploads/logo.png')): ?>
                    <img src="uploads/logo.png" alt="marketisleri.com" class="h-16 w-auto object-contain logo-img">
                <?php else: ?>
                    <span class="font-title text-base font-black text-slate-900 tracking-tight flex items-center gap-1.5">
                        <span class="text-red-600 material-symbols-outlined font-black">receipt_long</span>
                        marketisleri<span class="text-red-600">.com</span>
                    </span>
                <?php endif; ?>
            </a>
            <nav class="flex items-center gap-6 text-sm font-bold text-slate-600">
                <a href="index.php" class="hover:text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">home</span>Anasayfa</a>
                <a href="marketler.php" class="hover:text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">storefront</span>Marketler</a>
            </nav>
        </div>
    </header>

    <main class="pt-8 max-w-7xl w-full mx-auto px-4 md:px-6 flex-1 pb-16">
        <div class="mb-6 flex flex-wrap gap-3">
            <a href="index.php" class="inline-flex items-center gap-1.5 text-sm font-bold text-slate-600 hover:text-red-600 transition bg-white border border-slate-200/80 px-4 py-2 rounded-xl shadow-sm">
                <span class="material-symbols-outlined text-sm font-black">arrow_back</span>
                Anasayfaya Geri Dön
            </a>
            <a href="market.php?slug=<?= htmlspecialchars($brochure['market_slug']) ?>" class="inline-flex items-center gap-1.5 text-sm font-bold text-red-600 hover:text-white transition bg-red-50 hover:bg-red-600 border border-red-200/60 hover:border-red-600 px-4 py-2 rounded-xl shadow-sm">
                <span class="material-symbols-outlined text-sm font-black">storefront</span>
                <?= htmlspecialchars($brochure['market_name']) ?> Tüm Broşürleri
            </a>
        </div>

        <!-- Top Banner Ad -->
        <div class="w-full bg-white border border-slate-200/60 rounded-2xl p-4 text-center text-xs font-bold text-slate-400 tracking-wider mb-6 relative overflow-hidden select-none">
            <div class="absolute inset-0 bg-gradient-to-r from-red-500/5 to-rose-500/5 pointer-events-none"></div>
            <span class="material-symbols-outlined text-sm inline-block align-middle mr-1 text-slate-400">ads_click</span>
            GOOGLE ADSENSE REKLAM ALANI (728x90 veya Esnek)
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
            
            <!-- Left: Viewer -->
            <div class="lg:col-span-3 space-y-6">
                <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-md relative group flex flex-col justify-between min-h-[50vh]">
                    
                    <!-- Analysis status badge -->
                    <div id="analysis-badge" class="mb-3 hidden">
                        <span class="analyzing-badge">
                            <span class="dot-pulse"></span>
                            <span id="analysis-badge-text">Ürünler analiz ediliyor...</span>
                        </span>
                    </div>

                    <!-- Main display -->
                    <div class="relative flex items-center justify-center overflow-hidden flex-1 py-4">
                        
                        <button onclick="prevPage()" class="absolute left-2 z-10 w-12 h-12 rounded-full bg-white/95 border border-slate-100 shadow-lg flex items-center justify-center text-slate-700 hover:text-red-600 hover:scale-105 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-all duration-300">
                            <span class="material-symbols-outlined font-black">chevron_left</span>
                        </button>
                        <button onclick="nextPage()" class="absolute right-2 z-10 w-12 h-12 rounded-full bg-white/95 border border-slate-100 shadow-lg flex items-center justify-center text-slate-700 hover:text-red-600 hover:scale-105 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-all duration-300">
                            <span class="material-symbols-outlined font-black">chevron_right</span>
                        </button>

                        <?php if ($is_pdf): ?>
                            <canvas id="pdf-canvas" class="max-h-[75vh] max-w-full w-auto rounded-2xl shadow border border-slate-100 hidden bg-white"></canvas>
                            <div id="pdf-loading" class="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
                                <div class="w-10 h-10 border-4 border-slate-200 border-t-red-600 rounded-full animate-spin"></div>
                                <span class="text-sm">Broşür yükleniyor...</span>
                            </div>
                        <?php else: ?>
                            <?php if (empty($pages)): ?>
                                <div class="py-20 text-center text-slate-400">
                                    <span class="material-symbols-outlined text-5xl mb-2">find_in_page</span>
                                    Bu broşürde hiç sayfa bulunmamaktadır.
                                </div>
                            <?php else: ?>
                                <!-- Page wrapper with product overlay -->
                                <div id="page-wrapper" class="relative inline-block max-w-full">
                                    <img id="mainImg" 
                                         src="uploads/brochures/pages/<?= htmlspecialchars($pages[0]['image_path']) ?>" 
                                         alt="Sayfa 1"
                                         onload="onImageLoad()">
                                    <div id="product-overlay"></div>
                                </div>
                                <div id="no-products-hint" class="hidden">
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Navigation bar -->
                    <div class="flex items-center justify-between border-t border-slate-100 pt-6 mt-4">
                        <button onclick="prevPage()" class="inline-flex items-center gap-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold transition">
                            <span class="material-symbols-outlined text-sm font-black">chevron_left</span> Önceki
                        </button>
                        <span id="pageNo" class="font-title text-base font-black text-slate-800">
                            Sayfa 1 / <?= $is_pdf ? '...' : count($pages) ?>
                        </span>
                        <button onclick="nextPage()" class="inline-flex items-center gap-1 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold transition">
                            Sonraki <span class="material-symbols-outlined text-sm font-black">chevron_right</span>
                        </button>
                    </div>
                </div>

                <!-- Thumbnail ribbon -->
                <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm">
                    <h4 class="font-title text-sm font-bold text-slate-900 mb-4 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-red-600 text-base">apps</span>
                        Tüm Sayfalar
                    </h4>
                    <div id="thumbnail-ribbon" class="flex gap-3 overflow-x-auto pb-2 no-scrollbar">
                        <?php if (!$is_pdf): ?>
                            <?php foreach ($pages as $index => $p): ?>
                                <button onclick="goToPage(<?= $index ?>)" 
                                        class="thumbnail-btn shrink-0 border-2 rounded-xl overflow-hidden w-16 h-20 transition-all border-slate-200 hover:border-slate-400 <?= $index === 0 ? 'active-thumb' : '' ?>"
                                        data-page-index="<?= $index ?>">
                                    <img src="uploads/brochures/pages/<?= htmlspecialchars($p['image_path']) ?>" 
                                         class="w-full h-full object-cover" 
                                         alt="Thumb <?= $index + 1 ?>">
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bottom Banner Ad -->
                <div class="w-full bg-white border border-slate-200/60 rounded-2xl p-4 text-center text-xs font-bold text-slate-400 tracking-wider relative overflow-hidden select-none">
                    <div class="absolute inset-0 bg-gradient-to-r from-red-500/5 to-rose-500/5 pointer-events-none"></div>
                    <span class="material-symbols-outlined text-sm inline-block align-middle mr-1 text-slate-400">ads_click</span>
                    GOOGLE ADSENSE REKLAM ALANI (728x90 veya Esnek)
                </div>
            </div>

            <!-- Right: Sidebar -->
            <div class="space-y-6">
                <!-- Market info -->
                <a href="market.php?slug=<?= htmlspecialchars($brochure['market_slug']) ?>" class="block bg-white border border-slate-100 rounded-3xl p-6 shadow-sm hover:shadow-md transition space-y-5 group">
                    <div class="flex items-center gap-3">
                        <div class="w-14 h-14 rounded-2xl border border-slate-100 p-1 flex items-center justify-center shrink-0 shadow-sm bg-white">
                            <?php if ($brochure['market_logo']): ?>
                                <img src="uploads/markets/<?= htmlspecialchars($brochure['market_logo']) ?>" class="w-full h-full object-contain rounded-lg" alt="Market Logo">
                            <?php else: ?>
                                <span class="material-symbols-outlined text-slate-400 text-2xl">storefront</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-title text-lg font-black text-slate-900 leading-tight group-hover:text-red-600 transition-colors"><?= htmlspecialchars($brochure['market_name']) ?></h3>
                            <span class="text-xs text-red-600 font-bold flex items-center gap-0.5">
                                Tüm Broşürleri Gör
                                <span class="material-symbols-outlined text-xs">open_in_new</span>
                            </span>
                        </div>
                    </div>
                    <?php if ($brochure['market_desc']): ?>
                        <p class="text-slate-500 text-xs leading-relaxed border-t border-slate-100 pt-4">
                            <?= htmlspecialchars($brochure['market_desc']) ?>
                        </p>
                    <?php endif; ?>
                </a>

                <!-- Brochure details -->
                <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm space-y-4">
                    <h4 class="font-title text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">Katalog Bilgileri</h4>
                    <div class="space-y-3.5 text-sm">
                        <div class="flex justify-between items-center text-xs text-slate-500 border-b border-slate-100/50 pb-2.5">
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">calendar_month</span> Başlangıç:</span>
                            <span class="font-bold text-slate-700"><?= date('d.m.Y', strtotime($brochure['start_date'])) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs text-slate-500 border-b border-slate-100/50 pb-2.5">
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">calendar_month</span> Bitiş:</span>
                            <span class="font-bold text-slate-700"><?= date('d.m.Y', strtotime($brochure['end_date'])) ?></span>
                        </div>
                        <div class="p-3.5 rounded-2xl text-xs font-semibold leading-relaxed mt-2 border">
                            <?php
                            if ($brochure['end_date'] < $today) {
                                echo '<div class="bg-red-500/10 border-red-500/20 text-red-700 flex items-center gap-2"><span class="material-symbols-outlined text-base">error</span><span>Bu broşürün süresi dolmuştur.</span></div>';
                            } elseif ($brochure['start_date'] > $today) {
                                $diff = strtotime($brochure['start_date']) - strtotime($today);
                                $days = round($diff / 86400);
                                $dayStr = $days == 1 ? 'yarın' : $days . ' gün sonra';
                                echo '<div class="bg-amber-500/10 border-amber-500/20 text-amber-800 flex items-center gap-2"><span class="material-symbols-outlined text-base">schedule</span><span>Bu broşür ' . $dayStr . ' yayına girecektir.</span></div>';
                            } else {
                                $diff  = strtotime($brochure['end_date']) - strtotime($today);
                                $days  = round($diff / 86400);
                                $msg   = $days == 0 ? 'Son gün! Bugün sona eriyor.' : ($days == 1 ? 'Son 1 gün kaldı!' : "Aktif. Kalan: {$days} gün");
                                echo '<div class="bg-emerald-500/10 border-emerald-500/20 text-emerald-800 flex items-center gap-2"><span class="material-symbols-outlined text-base font-black">check_circle</span><span>' . $msg . '</span></div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Paylaş (Share) section -->
                <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm space-y-4">
                    <h4 class="font-title text-sm font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-red-600 text-base">share</span>
                        Bu Kataloğu Paylaş
                    </h4>
                    <div class="grid grid-cols-5 gap-2">
                        <?php
                        $share_url = urlencode($site_url . "/viewer.php?id=" . $brochure['id']);
                        $share_text = urlencode($brochure['market_name'] . " - " . $brochure['title'] . " Aktüel Ürün Kataloğu ve İndirimleri");
                        ?>
                        <!-- WhatsApp -->
                        <a href="https://api.whatsapp.com/send?text=<?= $share_text ?>%20<?= $share_url ?>" 
                           target="_blank" rel="noopener" 
                           class="w-10 h-10 rounded-xl bg-emerald-50 hover:bg-emerald-500 text-emerald-600 hover:text-white flex items-center justify-center transition shadow-sm border border-emerald-100" 
                           title="WhatsApp ile Paylaş">
                           <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                               <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.73-1.455L0 24zm6.59-4.846c1.66.986 3.296 1.488 4.966 1.489 5.485 0 9.948-4.469 9.952-9.959.002-2.661-1.034-5.161-2.909-7.038C16.792 1.77 14.3 1.72 12.012 1.72c-5.489 0-9.954 4.469-9.958 9.958-.002 1.95.51 3.843 1.482 5.508l-.99 3.61 3.705-.972c1.611.879 3.167 1.334 4.808 1.334zm11.233-7.559c-.309-.154-1.829-.903-2.107-1.004-.278-.101-.48-.153-.681.147-.202.3-.779.979-.955 1.18-.177.201-.354.226-.663.072-1.353-.679-2.316-1.189-3.21-2.723-.236-.406-.118-.625.035-.778.138-.138.309-.359.464-.539.15-.177.2-.3.3-.5.101-.2.05-.376-.026-.527-.076-.151-.681-1.637-.933-2.242-.244-.587-.492-.508-.681-.518-.176-.009-.379-.011-.581-.011-.202 0-.53.075-.808.376-.278.3-1.059 1.03-1.059 2.515s1.08 2.919 1.232 3.119c.152.2 2.126 3.245 5.15 4.553.719.311 1.28.497 1.718.636.722.23 1.381.197 1.902.12.579-.087 1.83-.748 2.083-1.47.253-.722.253-1.343.177-1.471-.076-.129-.278-.201-.587-.354z"/>
                           </svg>
                        </a>
                        <!-- Telegram -->
                        <a href="https://t.me/share/url?url=<?= $share_url ?>&text=<?= $share_text ?>" 
                           target="_blank" rel="noopener" 
                           class="w-10 h-10 rounded-xl bg-sky-50 hover:bg-sky-500 text-sky-600 hover:text-white flex items-center justify-center transition shadow-sm border border-sky-100" 
                           title="Telegram ile Paylaş">
                           <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                               <path d="M9.079 17.587l.317-4.47 8.13-7.34c.353-.313-.077-.487-.546-.177L6.877 12.015 2.54 10.66c-.94-.294-.959-.94.197-1.392L19.56 2.894c.777-.282 1.458.285 1.203 1.5l-2.859 13.48c-.216 1.02-.82 1.272-1.674.793l-4.353-3.21-2.099 2.02c-.232.232-.429.429-.879.429z"/>
                           </svg>
                        </a>
                        <!-- SMS -->
                        <a href="sms:?&body=<?= $share_text ?>%20<?= $share_url ?>" 
                           class="w-10 h-10 rounded-xl bg-purple-50 hover:bg-purple-500 text-purple-600 hover:text-white flex items-center justify-center transition shadow-sm border border-purple-100" 
                           title="SMS ile Paylaş">
                           <span class="material-symbols-outlined text-xl">sms</span>
                        </a>
                        <!-- Facebook -->
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_url ?>" 
                           target="_blank" rel="noopener" 
                           class="w-10 h-10 rounded-xl bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white flex items-center justify-center transition shadow-sm border border-blue-100" 
                           title="Facebook'ta Paylaş">
                           <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                               <path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/>
                           </svg>
                        </a>
                        <!-- X (Twitter) -->
                        <a href="https://twitter.com/intent/tweet?text=<?= $share_text ?>&url=<?= $share_url ?>" 
                           target="_blank" rel="noopener" 
                           class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-900 text-slate-800 hover:text-white flex items-center justify-center transition shadow-sm border border-slate-200" 
                           title="X'te (Twitter) Paylaş">
                           <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                               <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                           </svg>
                        </a>
                    </div>
                </div>

                <!-- Sidebar Ad -->
                <div class="bg-white border border-slate-200/60 rounded-3xl p-6 text-center text-xs font-bold text-slate-400 tracking-wider shadow-sm select-none relative overflow-hidden h-60 flex flex-col justify-center items-center">
                    <div class="absolute inset-0 bg-gradient-to-b from-red-500/5 to-rose-500/5 pointer-events-none"></div>
                    <span class="material-symbols-outlined text-2xl mb-2 text-slate-400">ads_click</span>
                    GOOGLE ADSENSE REKLAM ALANI<br>(300x250 veya Esnek)
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-100 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex flex-col items-center md:items-start gap-2">
                <a href="index.php">
                    <?php if (file_exists('uploads/logo.png')): ?>
                        <img src="uploads/logo.png" alt="marketisleri.com" class="h-20 w-auto object-contain">
                    <?php else: ?>
                        <span class="font-title text-lg font-black text-slate-900 tracking-tight flex items-center gap-1.5">
                            <span class="text-red-600 material-symbols-outlined font-black">receipt_long</span>
                            marketisleri<span class="text-red-600">.com</span>
                        </span>
                    <?php endif; ?>
                </a>
                <p class="text-slate-400 text-xs">En güncel aktüel ürün katalogları tek adreste.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-slate-500 font-medium my-4 md:my-0">
                <a href="marketler.php" class="hover:text-red-600 transition">Marketler</a>
                <a href="gizlilik-politikasi.php" class="hover:text-red-600 transition">Gizlilik Politikası</a>
                <a href="kullanim-kosullari.php" class="hover:text-red-600 transition">Kullanım Koşulları</a>
                <a href="cerez-politikasi.php" class="hover:text-red-600 transition">Çerez Politikası</a>
            </div>
            <?php if (!empty($social_settings['social_facebook']) || !empty($social_settings['social_instagram']) || !empty($social_settings['social_twitter']) || !empty($social_settings['social_youtube'])): ?>
                <div class="flex gap-4 my-4 md:my-0">
                    <?php if (!empty($social_settings['social_facebook'])): ?>
                        <a href="<?= htmlspecialchars($social_settings['social_facebook']) ?>" target="_blank" rel="noopener" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 flex items-center justify-center transition shadow-sm border border-slate-200/50" title="Facebook">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($social_settings['social_instagram'])): ?>
                        <a href="<?= htmlspecialchars($social_settings['social_instagram']) ?>" target="_blank" rel="noopener" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 flex items-center justify-center transition shadow-sm border border-slate-200/50" title="Instagram">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($social_settings['social_twitter'])): ?>
                        <a href="<?= htmlspecialchars($social_settings['social_twitter']) ?>" target="_blank" rel="noopener" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 flex items-center justify-center transition shadow-sm border border-slate-200/50" title="Twitter / X">
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($social_settings['social_youtube'])): ?>
                        <a href="<?= htmlspecialchars($social_settings['social_youtube']) ?>" target="_blank" rel="noopener" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 flex items-center justify-center transition shadow-sm border border-slate-200/50" title="YouTube">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M23.498 6.163a3.003 3.003 0 0 0-2.11-2.11C19.518 3.545 12 3.545 12 3.545s-7.518 0-9.388.507a3.003 3.003 0 0 0-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 0 0 2.11 2.11c1.87.507 9.388.507 9.388.507s7.518 0 9.388-.507a3.003 3.003 0 0 0 2.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="text-slate-400 text-xs text-center md:text-right space-y-1">
                <p>&copy; 2026 marketisleri.com All rights reserved.</p>
                <p><a href="https://kominikee.com" target="_blank" rel="noopener" class="text-red-600 hover:text-red-500 font-semibold">Kominike "Creative" Digital Project</a></p>
            </div>
        </div>
    </footer>

    <!-- ════════════════════════════════════════════════════════════════
         ZOOM MODAL
    ════════════════════════════════════════════════════════════════ -->
    <div id="zoom-modal" role="dialog" aria-modal="true" aria-label="Ürün Detayı">
        <div id="zoom-box">
            <div id="zoom-header">
                <h2 id="zoom-product-name" class="font-title text-lg font-black text-slate-900 leading-tight flex-1"></h2>
                <button onclick="closeZoom()" class="w-9 h-9 rounded-full border border-slate-200 flex items-center justify-center text-slate-500 hover:text-red-600 hover:border-red-300 transition flex-shrink-0">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <div id="zoom-content">
                <!-- Left: Zoomed image -->
                <div id="zoom-canvas-wrap">
                    <canvas id="zoom-canvas"></canvas>
                </div>

                <!-- Right: Details -->
                <div class="flex flex-col gap-4">
                    <!-- Price -->
                    <div>
                        <div id="zoom-price-wrap" class="flex items-end gap-3 flex-wrap">
                            <span id="zoom-price" class="text-4xl font-black text-red-600 font-title"></span>
                            <span id="zoom-orig-price" class="text-lg text-slate-400 line-through hidden"></span>
                            <span id="zoom-unit" class="text-sm text-slate-500 mb-1"></span>
                        </div>
                        <div id="zoom-discount-badge" class="hidden mt-1">
                            <span class="inline-block bg-red-100 text-red-700 font-bold text-xs px-2 py-0.5 rounded-full" id="zoom-discount-text"></span>
                        </div>
                    </div>

                    <!-- Price Comparison -->
                    <div>
                        <h3 class="font-bold text-sm text-slate-700 flex items-center gap-1.5 mb-2">
                            <span class="material-symbols-outlined text-base text-slate-500">compare_arrows</span>
                            Diğer Marketlerde
                        </h3>
                        <div id="compare-list" class="space-y-2">
                            <div class="skeleton h-10 rounded-xl"></div>
                            <div class="skeleton h-10 rounded-xl"></div>
                        </div>
                        <div id="compare-empty" class="hidden text-xs text-slate-400 py-2">Diğer marketlerde bu ürün bulunamadı.</div>
                    </div>

                    <!-- Price Alert Form -->
                    <div class="border-t border-slate-100 pt-4">
                        <h3 class="font-bold text-sm text-slate-700 flex items-center gap-1.5 mb-3">
                            <span class="material-symbols-outlined text-base text-amber-500">notifications</span>
                            Fiyat Alarmı Kur
                        </h3>
                        <form id="alert-form" onsubmit="submitAlert(event)" class="space-y-2">
                            <input type="hidden" id="alert-product-name" name="product_name">
                            <input type="email" id="alert-email" name="email" 
                                   placeholder="E-posta adresiniz" required
                                   class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-red-400 transition">
                            <div class="flex gap-2">
                                <input type="number" id="alert-target-price" name="target_price" 
                                       placeholder="Hedef fiyat (TL, opsiyonel)" step="0.01" min="0"
                                       class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-red-400 transition">
                            </div>
                            <button type="submit" id="alert-btn"
                                    class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold text-sm rounded-xl py-2.5 transition flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-base">notifications_active</span>
                                Alarm Kur
                            </button>
                            <div id="alert-msg" class="text-xs text-center hidden"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         JAVASCRIPT
    ════════════════════════════════════════════════════════════════ -->
    <script>
    const BROCHURE_ID       = <?= (int)$id ?>;
    const MARKET_ID         = <?= (int)$brochure['market_id_val'] ?>;
    const GEMINI_CONFIGURED = <?= $gemini_configured ? 'true' : 'false' ?>;
    const isPdf             = <?= $is_pdf ? 'true' : 'false' ?>;
    const pagesArray        = <?= json_encode(array_column($pages, 'image_path')) ?>;
    const totalPages        = pagesArray.length;
    let   currentPage       = 0;

    // Products cache: { pageNum: [...] }
    const productsCache     = { 1: <?= json_encode($first_page_products) ?> };

    // ── Page navigation ──────────────────────────────────────────────
    function prevPage() { if (currentPage > 0) goToPage(currentPage - 1); }
    function nextPage() { if (currentPage < totalPages - 1) goToPage(currentPage + 1); }

    function goToPage(index) {
        if (index < 0 || index >= totalPages) return;
        currentPage = index;
        const mainImg = document.getElementById('mainImg');
        if (mainImg) {
            mainImg.onload = onImageLoad;
            mainImg.src = 'uploads/brochures/pages/' + pagesArray[index];
            mainImg.alt = 'Sayfa ' + (index + 1);
        }
        document.getElementById('pageNo').innerText = 'Sayfa ' + (index + 1) + ' / ' + totalPages;
        highlightActiveThumbnail(index);
        clearHotspots();
    }

    function highlightActiveThumbnail(index) {
        document.querySelectorAll('.thumbnail-btn').forEach(btn => {
            const isActive = parseInt(btn.dataset.pageIndex) === index;
            btn.classList.toggle('active-thumb', isActive);
            if (isActive) {
                const ribbon = document.getElementById('thumbnail-ribbon');
                if (ribbon) {
                    const btnOffset = btn.offsetLeft;
                    const btnWidth = btn.offsetWidth;
                    const ribbonWidth = ribbon.offsetWidth;
                    ribbon.scrollTo({
                        left: btnOffset - (ribbonWidth / 2) + (btnWidth / 2),
                        behavior: 'smooth'
                    });
                }
            }
        });
    }

    // ── Image loaded: render hotspots ────────────────────────────────
    function onImageLoad() {
        const pageNum = currentPage + 1;
        if (productsCache[pageNum] !== undefined) {
            renderHotspots(productsCache[pageNum]);
        } else if (GEMINI_CONFIGURED) {
            analyzeCurrentPage();
        }
    }

    // ── Analyze page via Gemini ──────────────────────────────────────
    function analyzeCurrentPage() {
        const pageNum = currentPage + 1;
        const badge   = document.getElementById('analysis-badge');
        const badgeText = document.getElementById('analysis-badge-text');
        badge.classList.remove('hidden');
        badgeText.textContent = `Sayfa ${pageNum} analiz ediliyor...`;

        fetch(`api/analyze_page.php?brochure_id=${BROCHURE_ID}&page_number=${pageNum}`)
            .then(r => r.json())
            .then(data => {
                badge.classList.add('hidden');
                if (data.success) {
                    productsCache[pageNum] = data.products;
                    renderHotspots(data.products);
                }
            })
            .catch(() => badge.classList.add('hidden'));
    }

    // ── Render product hotspots on image ────────────────────────────
    function clearHotspots() {
        const overlay = document.getElementById('product-overlay');
        if (overlay) overlay.innerHTML = '';
    }

    function renderHotspots(products) {
        const overlay = document.getElementById('product-overlay');
        const img     = document.getElementById('mainImg');
        if (!overlay || !img) return;
        overlay.innerHTML = '';

        const hint = document.getElementById('no-products-hint');
        if (!products || products.length === 0) {
            if (hint) {
                hint.innerHTML = '<span class="material-symbols-outlined text-slate-400 text-lg">info</span> Bu sayfa henüz yapay zeka ile analiz edilmemiş.';
                hint.className = "mt-4 text-xs font-semibold text-slate-400 bg-slate-50 rounded-xl px-4 py-2.5 border border-slate-200/50 text-center flex items-center justify-center gap-1.5 max-w-sm mx-auto";
                hint.classList.remove('hidden');
            }
            return;
        }
        if (hint) {
            hint.innerHTML = '<span class="material-symbols-outlined text-red-500 text-lg animate-pulse">info</span> Ürünlerin üzerine tıklayarak fiyat karşılaştırmasını ve alarmını görebilirsiniz.';
            hint.className = "mt-4 text-sm font-bold text-red-600 bg-red-50/50 rounded-xl px-5 py-3 border border-red-100 text-center flex items-center justify-center gap-2 max-w-md mx-auto";
            hint.classList.remove('hidden');
        }

        products.forEach((p, i) => {
            if (p.x_pct == null) return;
            const hs = document.createElement('div');
            hs.className = 'product-hotspot';
            hs.style.left   = p.x_pct + '%';
            hs.style.top    = p.y_pct + '%';
            hs.style.width  = p.w_pct + '%';
            hs.style.height = p.h_pct + '%';
            hs.title = p.product_name + (p.price ? ` — ${formatPrice(p.price)} TL` : '');
            hs.dataset.idx  = i;
            hs.addEventListener('click', () => openZoom(p, img));
            overlay.appendChild(hs);
        });
    }

    // ── Zoom modal ───────────────────────────────────────────────────
    function openZoom(product, img) {
        // Deactivate all hotspots, activate clicked
        document.querySelectorAll('.product-hotspot').forEach(h => h.classList.remove('active'));

        // Render name
        document.getElementById('zoom-product-name').textContent = product.product_name;
        document.getElementById('alert-product-name').value = product.product_name;

        // Price
        const priceEl = document.getElementById('zoom-price');
        const origEl  = document.getElementById('zoom-orig-price');
        const unitEl  = document.getElementById('zoom-unit');
        const discBadge = document.getElementById('zoom-discount-badge');
        const discText  = document.getElementById('zoom-discount-text');

        if (product.price) {
            priceEl.textContent = formatPrice(product.price) + ' TL';
        } else {
            priceEl.textContent = '—';
        }

        if (product.original_price && product.original_price > product.price) {
            origEl.textContent = formatPrice(product.original_price) + ' TL';
            origEl.classList.remove('hidden');
            const pct = Math.round((1 - product.price / product.original_price) * 100);
            discBadge.classList.remove('hidden');
            discText.textContent = `%${pct} İndirim`;
        } else {
            origEl.classList.add('hidden');
            discBadge.classList.add('hidden');
        }

        unitEl.textContent = product.unit ? '(' + product.unit + ')' : '';

        // Draw zoomed region on canvas
        drawZoom(img, product);

        // Open modal
        document.getElementById('zoom-modal').classList.add('open');
        document.body.style.overflow = 'hidden';

        // Reset alert form
        document.getElementById('alert-msg').classList.add('hidden');
        document.getElementById('alert-btn').disabled = false;
        document.getElementById('alert-btn').textContent = '🔔 Alarm Kur';

        // Load price comparison
        loadComparison(product.product_name);
    }

    function closeZoom() {
        document.getElementById('zoom-modal').classList.remove('open');
        document.body.style.overflow = '';
        document.querySelectorAll('.product-hotspot').forEach(h => h.classList.remove('active'));
    }

    document.getElementById('zoom-modal').addEventListener('click', function(e) {
        if (e.target === this) closeZoom();
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeZoom(); });

    // ── Canvas zoom render ───────────────────────────────────────────
    function drawZoom(img, product) {
        const canvas = document.getElementById('zoom-canvas');
        const ctx    = canvas.getContext('2d');
        const w = img.naturalWidth  || img.width;
        const h = img.naturalHeight || img.height;

        // Source rect (% → px) with padding
        const PAD = 0.015; // 1.5% padding
        const sx = Math.max(0, (product.x_pct - PAD * 100) / 100 * w);
        const sy = Math.max(0, (product.y_pct - PAD * 100) / 100 * h);
        const sw = Math.min(w - sx, ((product.w_pct + PAD * 200) / 100) * w);
        const sh = Math.min(h - sy, ((product.h_pct + PAD * 200) / 100) * h);

        const maxW = 320, maxH = 320;
        const scale = Math.min(maxW / sw, maxH / sh);
        canvas.width  = Math.round(sw * scale);
        canvas.height = Math.round(sh * scale);

        // White bg
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        try {
            ctx.drawImage(img, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
        } catch(e) {
            // Cross-origin fallback
            const freshImg = new Image();
            freshImg.crossOrigin = 'anonymous';
            freshImg.onload = () => ctx.drawImage(freshImg, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
            freshImg.src = img.src;
        }
    }

    // ── Price comparison ─────────────────────────────────────────────
    function loadComparison(productName) {
        const list  = document.getElementById('compare-list');
        const empty = document.getElementById('compare-empty');
        list.innerHTML = '<div class="skeleton h-10 rounded-xl"></div><div class="skeleton h-10 rounded-xl mt-2"></div>';
        empty.classList.add('hidden');

        const params = new URLSearchParams({
            product_name: productName,
            exclude_brochure_id: BROCHURE_ID
        });

        fetch('api/price_compare.php?' + params)
            .then(r => r.json())
            .then(data => {
                list.innerHTML = '';
                if (!data.success || !data.results.length) {
                    empty.classList.remove('hidden');
                    return;
                }
                const minPrice = Math.min(...data.results.map(r => r.price));
                data.results.slice(0, 6).forEach(r => {
                    const isCheap = r.price === minPrice;
                    const badge   = isCheap
                        ? '<span class="compare-badge-cheap">En Ucuz</span>'
                        : (r.price > minPrice ? `<span class="compare-badge-expensive">+${formatPrice(r.price - minPrice)} TL</span>` : '');
                    const logo    = r.market_logo_url
                        ? `<img src="${r.market_logo_url}" alt="${escHtml(r.market_name)}">`
                        : `<span class="material-symbols-outlined text-slate-400 text-sm">storefront</span>`;
                    const days    = r.days_left === 0 ? 'Son gün' : `${r.days_left} gün`;

                    list.insertAdjacentHTML('beforeend', `
                      <a href="${r.brochure_url}" target="_blank" class="compare-item">
                        <div class="logo-wrap">${logo}</div>
                        <div class="flex-1 min-w-0">
                          <div class="font-bold text-sm text-slate-800">${escHtml(r.market_name)}</div>
                          <div class="text-xs text-slate-400">${days} kaldı</div>
                        </div>
                        <div class="flex flex-col items-end gap-0.5">
                          <span class="font-black text-base ${isCheap ? 'text-emerald-600' : 'text-slate-700'}">${formatPrice(r.price)} TL</span>
                          ${badge}
                        </div>
                      </a>`);
                });
            })
            .catch(() => { list.innerHTML = ''; empty.classList.remove('hidden'); });
    }

    // ── Price alert submit ───────────────────────────────────────────
    function submitAlert(e) {
        e.preventDefault();
        const btn     = document.getElementById('alert-btn');
        const msgEl   = document.getElementById('alert-msg');
        const formData = new FormData(document.getElementById('alert-form'));
        formData.append('action', 'create');
        formData.append('market_id', MARKET_ID);

        btn.disabled    = true;
        btn.textContent = 'Kaydediliyor...';
        msgEl.classList.add('hidden');

        fetch('api/price_alert.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                msgEl.classList.remove('hidden');
                if (data.success) {
                    msgEl.textContent = '✅ ' + data.message;
                    msgEl.className   = 'text-xs text-center text-emerald-600';
                    btn.textContent   = '✅ Alarm Kuruldu';
                } else {
                    msgEl.textContent = '⚠️ ' + data.error;
                    msgEl.className   = 'text-xs text-center text-red-600';
                    btn.disabled      = false;
                    btn.textContent   = '🔔 Alarm Kur';
                }
            })
            .catch(() => {
                msgEl.textContent = '⚠️ Bir hata oluştu, tekrar deneyin.';
                msgEl.className   = 'text-xs text-center text-red-600';
                msgEl.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = '🔔 Alarm Kur';
            });
    }

    // ── Utilities ────────────────────────────────────────────────────
    function formatPrice(n) {
        return parseFloat(n).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Init: load hotspots for first page ──────────────────────────
    window.prevPage = prevPage;
    window.nextPage = nextPage;
    window.goToPage = goToPage;

    // Trigger hotspot render if products already cached (first page)
    window.addEventListener('load', () => {
        const img = document.getElementById('mainImg');
        if (img && img.complete) onImageLoad();
    });

    // PDF support (kept for backward compat)
    <?php if ($is_pdf): ?>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    const pdfUrl = 'uploads/brochures/pdfs/<?= htmlspecialchars($brochure['pdf_path']) ?>';
    let pdfDoc = null, pageRendering = false, pageNumPending = null;
    const canvas2 = document.getElementById('pdf-canvas');
    const ctx2 = canvas2.getContext('2d');
    const loader = document.getElementById('pdf-loading');
    let pdfTotalPages = 0;

    pdfjsLib.getDocument(pdfUrl).promise.then(function(doc) {
        pdfDoc = doc;
        pdfTotalPages = doc.numPages;
        loader.classList.add('hidden');
        canvas2.classList.remove('hidden');
        populatePdfThumbnails();
        renderPdfPage(1);
    }).catch(() => {
        loader.innerHTML = '<span class="text-red-500 font-bold">PDF dosyası yüklenemedi.</span>';
    });

    function renderPdfPage(num) {
        pageRendering = true;
        pdfDoc.getPage(num).then(page => {
            const vp = page.getViewport({scale:1.5});
            canvas2.height = vp.height; canvas2.width = vp.width;
            page.render({canvasContext: ctx2, viewport: vp}).promise.then(() => {
                pageRendering = false;
                if (pageNumPending !== null) { renderPdfPage(pageNumPending); pageNumPending = null; }
            });
        });
        document.getElementById('pageNo').innerText = 'Sayfa ' + num + ' / ' + pdfTotalPages;
        currentPage = num - 1;
        highlightActiveThumbnail(currentPage);
    }
    function queueRenderPage(num) {
        if (pageRendering) pageNumPending = num; else renderPdfPage(num);
    }
    function populatePdfThumbnails() {
        const ribbon = document.getElementById('thumbnail-ribbon');
        ribbon.innerHTML = '';
        for (let i = 1; i <= pdfTotalPages; i++) {
            const btn = document.createElement('button');
            btn.className = 'thumbnail-btn shrink-0 border-2 rounded-xl w-16 h-20 flex flex-col items-center justify-center bg-slate-100 border-slate-200 text-slate-700 font-bold text-sm transition-all';
            btn.dataset.pageIndex = i - 1;
            btn.innerHTML = `<span class="text-[9px] text-slate-400 block font-normal uppercase">SAYFA</span>${i}`;
            btn.onclick = () => queueRenderPage(i);
            ribbon.appendChild(btn);
        }
        highlightActiveThumbnail(0);
    }
    window.prevPage = () => { if (currentPage > 0) queueRenderPage(currentPage); };
    window.nextPage = () => { if (currentPage < pdfTotalPages - 1) queueRenderPage(currentPage + 2); };
    window.goToPage = (i) => { if (i >= 0 && i < pdfTotalPages) queueRenderPage(i + 1); };
    <?php endif; ?>

    // Header scroll behavior
    window.addEventListener('scroll', () => {
        const header = document.querySelector('header.sticky-header');
        if (header) {
            if (window.scrollY > 20) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }
    });
    </script>
</body>
</html>