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
$stmt = $pdo->prepare("SELECT b.*, m.name as market_name, m.logo as market_logo, m.slug as market_slug, m.description as market_desc 
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
    // Fetch image pages
    $pages_stmt = $pdo->prepare("SELECT * FROM brochure_pages WHERE brochure_id = ? ORDER BY page_number ASC");
    $pages_stmt->execute([$id]);
    $pages = $pages_stmt->fetchAll();
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
    
    <!-- SEO Meta Tags -->
    <title><?= htmlspecialchars($brochure['market_name']) ?> - <?= htmlspecialchars($brochure['title']) ?> | Tüm Market Broşürleri Tek Yerde</title>
    <meta name="description" content="<?= htmlspecialchars($brochure['market_name']) ?> en güncel kataloğu. Geçerlilik Tarihi: <?= date('d.m.Y', strtotime($brochure['start_date'])) ?> - <?= date('d.m.Y', strtotime($brochure['end_date'])) ?>. Aktüel indirimleri kaçırmayın!">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($site_url) ?>/uploads/logo.png">
    
    <!-- Typography & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <!-- Compiled Tailwind CSS -->
    <link rel="stylesheet" href="uploads/tailwind.min.css">
    
    <!-- PDF.js CDN (Loaded only if PDF brochure) -->
    <?php if ($is_pdf): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <?php endif; ?>

    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* Thumbnail highlight style */
        .thumbnail-btn.active-thumb {
            border-color: #dc2626 !important;
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(220, 38, 38, 0.15);
        }
        
        /* Hide scrollbars for sliders */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    
    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8595320911699983"
         crossorigin="anonymous"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-red-500 selection:text-white">

    <!-- Header Navigation -->
    <header class="bg-white/80 backdrop-blur-md border-b border-slate-100 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 md:px-6 h-20 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2">
                <?php if (file_exists('uploads/logo.png')): ?>
                    <img src="uploads/logo.png" alt="marketisleri.com" class="h-16 w-auto object-contain">
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

    <!-- Main Content Grid -->
    <main class="pt-8 max-w-7xl w-full mx-auto px-4 md:px-6 flex-1 pb-16">
        <!-- Back Navigation Link -->
        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center gap-1.5 text-sm font-bold text-slate-600 hover:text-red-600 transition bg-white border border-slate-200/80 px-4 py-2 rounded-xl shadow-sm">
                <span class="material-symbols-outlined text-sm font-black">arrow_back</span>
                Anasayfaya Geri Dön
            </a>
        </div>
        
        <!-- Top Adsense Placeholder Banner -->
        <div class="w-full bg-white border border-slate-200/60 rounded-2xl p-4 text-center text-xs font-bold text-slate-400 tracking-wider mb-6 relative overflow-hidden select-none">
            <div class="absolute inset-0 bg-gradient-to-r from-red-500/5 to-rose-500/5 pointer-events-none"></div>
            <span class="material-symbols-outlined text-sm inline-block align-middle mr-1 text-slate-400">ads_click</span>
            GOOGLE ADSENSE REKLAM ALANI (728x90 veya Esnek)
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
            
            <!-- Left Column: Brochure Page Viewer (lg:col-span-3) -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Viewer Panel -->
                <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-md relative group flex flex-col justify-between min-h-[50vh]">
                    
                    <!-- Main Display Container -->
                    <div class="relative flex items-center justify-center overflow-hidden flex-1 py-4">
                        
                        <!-- Overlay Navigation Arrows (Desktop) -->
                        <button onclick="prevPage()" 
                                class="absolute left-2 z-10 w-12 h-12 rounded-full bg-white/95 border border-slate-100 shadow-lg flex items-center justify-center text-slate-700 hover:text-red-600 hover:scale-105 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-all duration-300">
                            <span class="material-symbols-outlined font-black">chevron_left</span>
                        </button>
                        
                        <button onclick="nextPage()" 
                                class="absolute right-2 z-10 w-12 h-12 rounded-full bg-white/95 border border-slate-100 shadow-lg flex items-center justify-center text-slate-700 hover:text-red-600 hover:scale-105 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-all duration-300">
                            <span class="material-symbols-outlined font-black">chevron_right</span>
                        </button>

                        <!-- Content Render Targets -->
                        <?php if ($is_pdf): ?>
                            <!-- PDF Canvas Render Target -->
                            <canvas id="pdf-canvas" class="max-h-[75vh] max-w-full w-auto rounded-2xl shadow border border-slate-100 hidden bg-white"></canvas>
                            <!-- PDF Loading Spinner -->
                            <div id="pdf-loading" class="flex flex-col items-center justify-center py-20 text-slate-400 gap-3">
                                <div class="w-10 h-10 border-4 border-slate-200 border-t-red-600 rounded-full animate-spin"></div>
                                <span class="text-sm">Broşür yükleniyor...</span>
                            </div>
                        <?php else: ?>
                            <!-- Image Render Target -->
                            <?php if (empty($pages)): ?>
                                <div class="py-20 text-center text-slate-400">
                                    <span class="material-symbols-outlined text-5xl mb-2">find_in_page</span>
                                    Bu broşürde hiç sayfa bulunmamaktadır.
                                </div>
                            <?php else: ?>
                                <img id="mainImg" src="uploads/brochures/pages/<?= htmlspecialchars($pages[0]['image_path']) ?>" 
                                     class="max-h-[75vh] max-w-full w-auto rounded-2xl shadow border border-slate-100 object-contain" 
                                     alt="Page 1">
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>
                    
                    <!-- Navigation / Page Numbers Bar -->
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

                <!-- Page Thumbnail Ribbon -->
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

                <!-- Bottom Adsense Placeholder Banner -->
                <div class="w-full bg-white border border-slate-200/60 rounded-2xl p-4 text-center text-xs font-bold text-slate-400 tracking-wider relative overflow-hidden select-none">
                    <div class="absolute inset-0 bg-gradient-to-r from-red-500/5 to-rose-500/5 pointer-events-none"></div>
                    <span class="material-symbols-outlined text-sm inline-block align-middle mr-1 text-slate-400">ads_click</span>
                    GOOGLE ADSENSE REKLAM ALANI (728x90 veya Esnek)
                </div>
            </div>

            <!-- Right Column: Sidebar Details & Ads (lg:col-span-1) -->
            <div class="space-y-6">
                <!-- Market Info Box -->
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

                <!-- Brochure Details Box -->
                <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm space-y-4">
                    <h4 class="font-title text-sm font-bold text-slate-900 border-b border-slate-100 pb-3">Katalog Bilgileri</h4>
                    
                    <div class="space-y-3.5 text-sm">
                        <!-- Validity dates -->
                        <div class="flex justify-between items-center text-xs text-slate-500 border-b border-slate-100/50 pb-2.5">
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">calendar_month</span> Başlangıç:</span>
                            <span class="font-bold text-slate-700"><?= date('d.m.Y', strtotime($brochure['start_date'])) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs text-slate-500 border-b border-slate-100/50 pb-2.5">
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">calendar_month</span> Bitiş:</span>
                            <span class="font-bold text-slate-700"><?= date('d.m.Y', strtotime($brochure['end_date'])) ?></span>
                        </div>

                        <!-- Status Alert Card -->
                        <div class="p-3.5 rounded-2xl text-xs font-semibold leading-relaxed mt-2 border">
                            <?php
                            if ($brochure['end_date'] < $today) {
                                echo '<div class="bg-red-500/10 border-red-500/20 text-red-700 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base">error</span>
                                        <span>Bu broşürün süresi dolmuştur. Yeni kataloglara göz atabilirsiniz.</span>
                                      </div>';
                            } elseif ($brochure['start_date'] > $today) {
                                $diff = strtotime($brochure['start_date']) - strtotime($today);
                                $days = round($diff / (60 * 60 * 24));
                                $dayStr = ($days == 1) ? 'yarın' : $days . ' gün sonra';
                                echo '<div class="bg-amber-500/10 border-amber-500/20 text-amber-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base">schedule</span>
                                        <span>Bu broşür henüz aktif değildir. ' . $dayStr . ' yayına girecektir.</span>
                                      </div>';
                            } else {
                                $diff = strtotime($brochure['end_date']) - strtotime($today);
                                $days = round($diff / (60 * 60 * 24));
                                if ($days == 0) {
                                    $msg = "Son gün! Bugün kampanya sona eriyor.";
                                } elseif ($days == 1) {
                                    $msg = "Son 1 gün! Yarın kampanya sona eriyor.";
                                } else {
                                    $msg = "Bu indirim broşürü aktiftir. Kalan gün: " . $days;
                                }
                                echo '<div class="bg-emerald-500/10 border-emerald-500/20 text-emerald-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-base font-black">check_circle</span>
                                        <span>' . $msg . '</span>
                                      </div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Adsense Placeholder Card -->
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

            <!-- Legal Links -->
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-slate-500 font-medium my-4 md:my-0">
                <a href="marketler.php" class="hover:text-red-600 transition">Marketler</a>
                <a href="gizlilik-politikasi.php" class="hover:text-red-600 transition">Gizlilik Politikası</a>
                <a href="kullanim-kosullari.php" class="hover:text-red-600 transition">Kullanım Koşulları</a>
                <a href="cerez-politikasi.php" class="hover:text-red-600 transition">Çerez Politikası</a>
            </div>

            <!-- Social Media Links -->
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

    <!-- Hybrid Viewer JS Logic -->
    <script>
        const isPdf = <?= $is_pdf ? 'true' : 'false' ?>;
        let currentPage = 0;
        let totalPages = 0;
        let pagesArray = [];

        if (isPdf) {
            // PDF.js Integration
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

            const pdfUrl = 'uploads/brochures/pdfs/<?= htmlspecialchars($brochure['pdf_path']) ?>';
            let pdfDoc = null;
            let pageRendering = false;
            let pageNumPending = null;
            const canvas = document.getElementById('pdf-canvas');
            const ctx = canvas.getContext('2d');
            const loader = document.getElementById('pdf-loading');

            pdfjsLib.getDocument(pdfUrl).promise.then(function(pdfDoc_) {
                pdfDoc = pdfDoc_;
                totalPages = pdfDoc.numPages;
                loader.classList.add('hidden');
                canvas.classList.remove('hidden');
                
                // Populate thumbnail ribbon dynamically
                populatePdfThumbnails();
                
                // Render first page
                renderPdfPage(1);
            }).catch(function(err) {
                console.error("PDF Yüklenemedi: ", err);
                loader.innerHTML = '<span class="text-red-500 font-bold">PDF dosyası yüklenemedi. Lütfen tekrar deneyin.</span>';
            });

            function renderPdfPage(num) {
                pageRendering = true;
                
                pdfDoc.getPage(num).then(function(page) {
                    // Set scale according to container width for sharpness and responsiveness
                    const viewport = page.getViewport({ scale: 1.5 });
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    const renderContext = {
                        canvasContext: ctx,
                        viewport: viewport
                    };
                    const renderTask = page.render(renderContext);

                    renderTask.promise.then(function() {
                        pageRendering = false;
                        if (pageNumPending !== null) {
                            renderPdfPage(pageNumPending);
                            pageNumPending = null;
                        }
                    });
                });

                // Update page number elements
                document.getElementById('pageNo').innerText = 'Sayfa ' + num + ' / ' + totalPages;
                currentPage = num - 1;
                
                // Highlight active thumbnail
                highlightActiveThumbnail(currentPage);
            }

            function queueRenderPage(num) {
                if (pageRendering) {
                    pageNumPending = num;
                } else {
                    renderPdfPage(num);
                }
            }

            function populatePdfThumbnails() {
                const ribbon = document.getElementById('thumbnail-ribbon');
                ribbon.innerHTML = '';
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement('button');
                    btn.className = 'thumbnail-btn shrink-0 border-2 rounded-xl w-16 h-20 flex flex-col items-center justify-center bg-slate-100 hover:bg-slate-200 border-slate-200 text-slate-700 font-bold text-sm transition-all';
                    btn.setAttribute('data-page-index', i - 1);
                    btn.innerHTML = `<span class="text-[9px] text-slate-400 block font-normal uppercase">SAYFA</span>${i}`;
                    btn.onclick = () => goToPage(i - 1);
                    ribbon.appendChild(btn);
                }
                highlightActiveThumbnail(0);
            }

            function prevPage() {
                if (currentPage <= 0) return;
                queueRenderPage(currentPage);
            }

            function nextPage() {
                if (currentPage >= totalPages - 1) return;
                queueRenderPage(currentPage + 2);
            }

            function goToPage(index) {
                if (index < 0 || index >= totalPages) return;
                queueRenderPage(index + 1);
            }

            window.prevPage = prevPage;
            window.nextPage = nextPage;
            window.goToPage = goToPage;

        } else {
            // Image Pages Logic
            pagesArray = <?= json_encode(array_column($pages, 'image_path')) ?>;
            totalPages = pagesArray.length;
            currentPage = 0;

            function prevPage() {
                if (currentPage <= 0) return;
                goToPage(currentPage - 1);
            }

            function nextPage() {
                if (currentPage >= totalPages - 1) return;
                goToPage(currentPage + 1);
            }

            function goToPage(index) {
                if (index < 0 || index >= totalPages) return;
                currentPage = index;
                
                const mainImg = document.getElementById('mainImg');
                if (mainImg) {
                    mainImg.src = 'uploads/brochures/pages/' + pagesArray[currentPage];
                    mainImg.alt = 'Page ' + (currentPage + 1);
                }
                
                document.getElementById('pageNo').innerText = 'Sayfa ' + (currentPage + 1) + ' / ' + totalPages;
                highlightActiveThumbnail(currentPage);
            }

            window.prevPage = prevPage;
            window.nextPage = nextPage;
            window.goToPage = goToPage;
        }

        function highlightActiveThumbnail(index) {
            document.querySelectorAll('.thumbnail-btn').forEach(btn => {
                if (parseInt(btn.getAttribute('data-page-index')) === index) {
                    btn.classList.add('active-thumb');
                    // Scroll active thumbnail into view smoothly
                    btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                } else {
                    btn.classList.remove('active-thumb');
                }
            });
        }
    </script>
</body>
</html>