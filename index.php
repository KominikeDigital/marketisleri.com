<?php
require 'config.php';

$today = date('Y-m-d');

// Fetch all settings
$settings_stmt = $pdo->query("SELECT * FROM settings");
$site_settings = [];
while ($row = $settings_stmt->fetch()) {
    $site_settings[$row['key_name']] = $row['value_text'];
}
$social_settings = $site_settings; // backward compatibility


// Filter parameters
$selected_tab = $_GET['tab'] ?? 'active';
$selected_cat = isset($_GET['category']) && $_GET['category'] !== '' ? intval($_GET['category']) : null;
$selected_market = isset($_GET['market']) && $_GET['market'] !== '' ? intval($_GET['market']) : null;
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build conditions
$conditions = [];
$params = [];

// Tab condition
if ($selected_tab === 'upcoming') {
    $conditions[] = "b.start_date > ?";
    $params[] = $today;
} elseif ($selected_tab === 'expired') {
    $conditions[] = "b.end_date < ?";
    $params[] = $today;
} elseif ($selected_tab === 'ending_soon') {
    $conditions[] = "b.start_date <= ? AND b.end_date >= ? AND b.end_date <= ?";
    $params[] = $today;
    $params[] = $today;
    $params[] = date('Y-m-d', strtotime('+1 day'));
} else { // active
    $conditions[] = "b.start_date <= ? AND b.end_date >= ?";
    $params[] = $today;
    $params[] = $today;
}

// Filter to only show visible brochures on homepage (must have cover_image OR pdf OR pages)
$conditions[] = "b.show_on_homepage = 1 AND (
    (b.cover_image IS NOT NULL AND b.cover_image != '')
    OR (b.pdf_path IS NOT NULL AND b.pdf_path != '')
    OR (SELECT COUNT(*) FROM brochure_pages WHERE brochure_id = b.id) > 0
)";

// Limit homepage to 1 newest brochure per market (based on the current tab's condition)
// $subquery_cond = "AND b2.show_on_homepage = 1 AND ((b2.pdf_path IS NOT NULL AND b2.pdf_path != '') OR (SELECT COUNT(*) FROM brochure_pages WHERE brochure_id = b2.id) > 0)";
// if ($selected_tab === 'upcoming') {
//     $subquery_cond .= " AND b2.start_date > '$today'";
// } elseif ($selected_tab === 'expired') {
//     $subquery_cond .= " AND b2.end_date < '$today'";
// } elseif ($selected_tab === 'ending_soon') {
//     $subquery_cond .= " AND b2.start_date <= '$today' AND b2.end_date >= '$today' AND b2.end_date <= '" . date('Y-m-d', strtotime('+1 day')) . "'";
// } else { // active
//     $subquery_cond .= " AND b2.start_date <= '$today' AND b2.end_date >= '$today'";
// }
// $conditions[] = "b.id = (SELECT b2.id FROM brochures b2 WHERE b2.market_id = b.market_id $subquery_cond ORDER BY b2.start_date DESC, b2.created_at DESC LIMIT 1)";

// Category condition
if ($selected_cat !== null) {
    $conditions[] = "m.category_id = ?";
    $params[] = $selected_cat;
}

// Market condition
if ($selected_market !== null) {
    $conditions[] = "b.market_id = ?";
    $params[] = $selected_market;
}

// Search condition
if (!empty($search_query)) {
    $conditions[] = "(b.title LIKE ? OR m.name LIKE ?)";
    $params[] = '%' . $search_query . '%';
    $params[] = '%' . $search_query . '%';
}

$sql = "SELECT b.*, m.name as market_name, m.logo as market_logo,
               CASE WHEN EXISTS (SELECT 1 FROM brochure_products bp WHERE bp.brochure_id = b.id) THEN 1 ELSE 0 END as has_ai_analysis
        FROM brochures b 
        JOIN markets m ON b.market_id = m.id";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY b.start_date DESC, b.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$brochures = $stmt->fetchAll();

// Fetch Categories & Markets for filters
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
$markets = $pdo->query("SELECT * FROM markets ORDER BY name ASC")->fetchAll();
$popular_markets = $pdo->query("SELECT * FROM markets WHERE is_popular = 1 ORDER BY name ASC")->fetchAll();

// Fetch 3 latest blog posts for homepage showcase
$recent_blogs = $pdo->query("SELECT title, slug, summary, cover_image, created_at FROM blog_posts ORDER BY created_at DESC LIMIT 3")->fetchAll();

// Formatting date helper
if (!function_exists('formatBlogDate')) {
    function formatBlogDate($date_str) {
        $months = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
            7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
        ];
        $timestamp = strtotime($date_str);
        $day = date('j', $timestamp);
        $month = $months[(int)date('n', $timestamp)];
        $year = date('Y', $timestamp);
        return "$day $month $year";
    }
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
    <?php
    $seo_title = $site_settings['seo_title_home'] ?? 'marketisleri.com - Tüm Market Broşürleri Tek Yerde';
    $seo_desc = $site_settings['seo_description_home'] ?? 'BİM, A101, ŞOK, Migros ve diğer süpermarketlerin en güncel broşürleri, aktüel ürün katalogları ve haftalık indirimleri tek bir yerde!';
    $seo_key = $site_settings['seo_keywords_home'] ?? 'market broşürleri, aktüel ürünler, bim aktüel, a101 aktüel, şok katalog, haftalık indirimler, indirim broşürleri';

    if ($selected_market) {
        $curr_market_name = '';
        foreach ($markets as $m) {
            if ($m['id'] === $selected_market) {
                $curr_market_name = $m['name'];
                break;
            }
        }
        if ($curr_market_name) {
            $seo_title = "$curr_market_name Aktüel Ürün Katalogları ve İndirim Broşürleri | marketisleri.com";
            $seo_desc = "$curr_market_name en güncel aktüel ürün katalogları ve broşürleri. Tüm haftalık indirimleri ve fırsatları detaylı inceleyin!";
        }
    } elseif ($selected_cat) {
        $curr_cat_name = '';
        foreach ($categories as $c) {
            if ($c['id'] === $selected_cat) {
                $curr_cat_name = $c['name'];
                break;
            }
        }
        if ($curr_cat_name) {
            $seo_title = "Güncel $curr_cat_name Kampanyaları ve İndirim Katalogları | marketisleri.com";
            $seo_desc = "En yeni $curr_cat_name indirim broşürleri, aktüel ürünler listesi ve fırsatları. Tüm market kampanyalarını karşılaştırın.";
        }
    } elseif (!empty($search_query)) {
        $seo_title = '"' . htmlspecialchars($search_query) . '" İndirimleri ve Broşürleri | marketisleri.com';
        $seo_desc = '"' . htmlspecialchars($search_query) . '" ile ilgili tüm güncel market broşürleri, aktüel ürünler ve indirim fırsatları.';
    }
    ?>
    <title><?= htmlspecialchars($seo_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seo_desc) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($seo_key) ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($site_url) ?>/uploads/logo.png">
    
    <!-- Typography & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" media="print" onload="this.media='all'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    </noscript>
    
    <!-- Inlined Tailwind CSS to prevent render-blocking request -->
    <style>
        <?php 
        $css_file = __DIR__ . '/uploads/tailwind.min.css';
        if (file_exists($css_file)) {
            echo file_get_contents($css_file);
        }
        ?>
    </style>
    
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* Hide scrollbars for sliders */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Sticky Header Transitions */
        header.sticky-header {
            position: sticky !important;
            top: 0 !important;
            z-index: 50 !important;
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

        /* Hero drift animations */
        @keyframes float-orb-1 {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(8%, 12%) scale(1.12); }
            100% { transform: translate(0, 0) scale(1); }
        }
        @keyframes float-orb-2 {
            0% { transform: translate(0, 0) scale(1.1); }
            50% { transform: translate(-8%, -12%) scale(0.92); }
            100% { transform: translate(0, 0) scale(1.1); }
        }
        .animate-orb-1 {
            animation: float-orb-1 16s ease-in-out infinite;
        }
        .animate-orb-2 {
            animation: float-orb-2 20s ease-in-out infinite;
        }
    </style>
    
    <!-- Google AdSense -->
    <?php if (($site_settings['adsense_active'] ?? '1') === '1'): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8595320911699983"
             crossorigin="anonymous"></script>
    <?php endif; ?>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-red-500 selection:text-white">

    <!-- Header Navigation -->
    <header class="bg-white/80 backdrop-blur-md border-b border-slate-100 sticky top-0 z-50 sticky-header">
        <div class="max-w-7xl mx-auto px-4 md:px-6 h-20 flex items-center justify-between header-container">
            <a href="index.php" class="flex items-center gap-2">
                <?php if (file_exists('uploads/logo.png')): ?>
                    <img src="uploads/logo.png" alt="marketisleri.com" class="h-16 w-auto object-contain logo-img" width="128" height="64">
                <?php else: ?>
                    <span class="font-title text-base font-black text-slate-900 tracking-tight flex items-center gap-1.5">
                        <span class="text-red-600 material-symbols-outlined font-black">receipt_long</span>
                        marketisleri<span class="text-red-600">.com</span>
                    </span>
                <?php endif; ?>
            </a>
            
            <nav class="flex items-center gap-6 text-sm font-bold text-slate-600">
                <a href="index.php" class="text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">home</span>Anasayfa</a>
                <a href="marketler.php" class="hover:text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">storefront</span>Marketler</a>
                <a href="blog.php" class="hover:text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">article</span>Blog</a>
                <a href="iletisim.php" class="hover:text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">mail</span>İletişim</a>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="pt-8 max-w-7xl w-full mx-auto px-4 md:px-6 flex-1 pb-16 space-y-10">
        
        <!-- Hero Search Section -->
        <section id="hero-section" class="text-center py-16 bg-gradient-to-tr from-slate-950 via-red-950 to-slate-950 rounded-3xl border border-slate-800 relative overflow-hidden px-4 shadow-xl shadow-red-950/10">
            <!-- Video Background (dynamically loaded on desktop after page load to save initial bandwidth) -->
            <?php if (file_exists('uploads/hero.mp4')): ?>
                <div id="hero-video-container" class="absolute inset-0 w-full h-full object-cover opacity-20 pointer-events-none mix-blend-lighten hidden md:block"></div>
                <script>
                    window.addEventListener('load', () => {
                        if (window.innerWidth >= 768) {
                            const videoContainer = document.getElementById('hero-video-container');
                            if (videoContainer) {
                                videoContainer.innerHTML = '<video autoplay muted loop playsinline class="w-full h-full object-cover"><source src="uploads/hero.mp4" type="video/mp4"></video>';
                            }
                        }
                    });
                </script>
            <?php endif; ?>

            <!-- Glowing backdrops (Drifting Ambient) -->
            <div class="absolute top-[-40%] left-[-10%] w-[50%] h-[90%] rounded-full bg-red-500/10 blur-[100px] pointer-events-none animate-orb-1"></div>
            <div class="absolute bottom-[-40%] right-[-10%] w-[50%] h-[90%] rounded-full bg-rose-500/10 blur-[100px] pointer-events-none animate-orb-2"></div>
            
            <!-- Mouse Follow Interactive Glow -->
            <div id="hero-mouse-glow" class="absolute w-[350px] h-[350px] rounded-full bg-red-500/[0.05] blur-[90px] pointer-events-none transition-transform duration-300 ease-out hidden md:block" style="left: -999px; top: -999px; transform: translate3d(0,0,0);"></div>
            
            <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full bg-red-500/10 border border-red-500/20 text-xs font-bold text-red-400 uppercase tracking-widest mb-6 font-title">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-ping"></span>
                Kampanyalar & İndirimler
            </span>

            <h1 class="font-title text-4xl md:text-6xl font-black text-white mb-4 tracking-tight max-w-3xl mx-auto leading-tight">
                Tüm Market Broşürleri <span class="text-transparent bg-clip-text bg-gradient-to-r from-red-500 to-rose-400">Tek Yerde</span>
            </h1>
            
            <p class="text-slate-400 md:text-lg mb-10 max-w-xl mx-auto font-medium">
                BİM, A101, ŞOK ve daha fazlasının güncel aktüel katalogları ve indirimleri.
            </p>
            
            <form method="GET" action="index.php" class="max-w-2xl mx-auto relative group mb-6">
                <!-- Keep existing filters on search -->
                <?php if ($selected_cat): ?>
                    <input type="hidden" name="category" value="<?= $selected_cat ?>">
                <?php endif; ?>
                <?php if ($selected_market): ?>
                    <input type="hidden" name="market" value="<?= $selected_market ?>">
                <?php endif; ?>
                <input type="hidden" name="tab" value="<?= htmlspecialchars($selected_tab) ?>">
                
                <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" 
                       class="w-full p-5 pl-14 pr-24 rounded-2xl border border-slate-800 shadow-2xl focus:ring-4 focus:ring-red-500/20 focus:border-red-500 outline-none text-slate-100 bg-slate-900/60 backdrop-blur-md transition-all text-base placeholder:text-slate-500" 
                       placeholder="Hangi marketin broşürünü arıyorsunuz? (BİM, A101, ŞOK...)">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-500 group-focus-within:text-red-500 transition-colors">search</span>
                
                <button type="submit" class="absolute right-2.5 top-1/2 -translate-y-1/2 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-bold px-6 py-3 rounded-xl transition shadow-lg shadow-red-600/20 text-sm">
                    Ara
                </button>
            </form>
            
            <!-- Quick Searches / Popüler Aramalar -->
            <div class="flex flex-wrap items-center justify-center gap-2.5 max-w-xl mx-auto text-xs">
                <span class="text-slate-500 font-semibold">Popüler:</span>
                <a href="index.php?q=BİM" class="px-3.5 py-1.5 rounded-full bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:border-red-500 hover:bg-slate-800 transition">BİM</a>
                <a href="index.php?q=A101" class="px-3.5 py-1.5 rounded-full bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:border-red-500 hover:bg-slate-800 transition">A101</a>
                <a href="index.php?q=ŞOK" class="px-3.5 py-1.5 rounded-full bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:border-red-500 hover:bg-slate-800 transition">ŞOK</a>
                <a href="index.php?q=Migros" class="px-3.5 py-1.5 rounded-full bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:border-red-500 hover:bg-slate-800 transition">Migros</a>
                <a href="index.php?q=Teknosa" class="px-3.5 py-1.5 rounded-full bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:border-red-500 hover:bg-slate-800 transition">Teknosa</a>
            </div>
        </section>

        <!-- Categories Filter Slider -->
        <section class="space-y-4">
            <h2 class="font-title text-lg font-bold text-slate-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-red-600">category</span>
                Kategoriler
            </h2>
            <div class="flex gap-3 overflow-x-auto no-scrollbar pb-2 mask-linear">
                <!-- All categories link -->
                <a href="index.php?tab=<?= $selected_tab ?><?= $selected_market ? "&market=" . $selected_market : "" ?><?= $search_query ? "&q=" . urlencode($search_query) : "" ?>" 
                   class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full text-sm font-bold border transition shrink-0 <?= $selected_cat === null ? 'bg-red-600 border-red-600 text-white shadow-md shadow-red-600/10' : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300' ?>">
                    <span class="material-symbols-outlined text-lg">grid_view</span>
                    Tümü
                </a>
                
                <?php foreach ($categories as $cat): ?>
                    <a href="index.php?category=<?= $cat['id'] ?>&tab=<?= $selected_tab ?><?= $selected_market ? "&market=" . $selected_market : "" ?><?= $search_query ? "&q=" . urlencode($search_query) : "" ?>" 
                       class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full text-sm font-bold border transition shrink-0 <?= $selected_cat === $cat['id'] ? 'bg-red-600 border-red-600 text-white shadow-md shadow-red-600/10' : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300' ?>">
                        <span class="material-symbols-outlined text-lg"><?= htmlspecialchars($cat['icon']) ?></span>
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>



        <!-- Markets Circle Slider -->
        <section class="space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="font-title text-lg font-bold text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-600">storefront</span>
                    Popüler Marketler
                </h2>
                <?php if ($selected_market !== null): ?>
                    <a href="index.php?tab=<?= $selected_tab ?><?= $selected_cat ? "&category=" . $selected_cat : "" ?><?= $search_query ? "&q=" . urlencode($search_query) : "" ?>" 
                       class="text-xs text-red-600 hover:text-red-500 font-bold">Market Filtresini Temizle</a>
                <?php endif; ?>
            </div>
            
            <div class="flex gap-4 overflow-x-auto no-scrollbar pb-3">
                <?php foreach ($popular_markets as $m): ?>
                    <a href="market.php?slug=<?= htmlspecialchars($m['slug']) ?>" 
                       class="flex flex-col items-center gap-2 shrink-0 group">
                        <div class="w-16 h-16 rounded-full border border-slate-200 bg-white flex items-center justify-center p-1 shadow-sm transition-all group-hover:scale-110 group-hover:border-red-600/50">
                            <?php if ($m['logo']): ?>
                                 <img src="uploads/markets/<?= htmlspecialchars($m['logo']) ?>" 
                                      class="w-full h-full object-contain rounded-full" 
                                      alt=""
                                      width="64" height="64">
                            <?php else: ?>
                                <div class="w-full h-full bg-slate-100 rounded-full flex items-center justify-center text-slate-400 font-bold text-xs">
                                    <?= substr($m['name'], 0, 3) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs font-bold text-slate-600 group-hover:text-red-600 transition-colors">
                            <?= htmlspecialchars($m['name']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Tabs & Brochures Listing -->
        <section id="listing-section" class="space-y-6">
            <!-- Tabs -->
            <div class="flex border-b border-slate-200 overflow-x-auto no-scrollbar">
                <a href="index.php?tab=active<?= $selected_cat ? "&category=" . $selected_cat : "" ?><?= $selected_market ? "&market=" . $selected_market : "" ?><?= $search_query ? "&q=" . urlencode($search_query) : "" ?>#listing-section" 
                   class="px-6 py-4 text-sm font-bold border-b-2 transition-all shrink-0 flex items-center gap-2 <?= $selected_tab === 'active' ? 'border-red-600 text-red-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <span class="material-symbols-outlined text-lg">local_fire_department</span>
                    Aktif Broşürler
                </a>
                <a href="index.php?tab=ending_soon<?= $selected_cat ? "&category=" . $selected_cat : "" ?><?= $selected_market ? "&market=" . $selected_market : "" ?><?= $search_query ? "&q=" . urlencode($search_query) : "" ?>#listing-section" 
                   class="px-6 py-4 text-sm font-bold border-b-2 transition-all shrink-0 flex items-center gap-2 <?= $selected_tab === 'ending_soon' ? 'border-red-600 text-red-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <span class="material-symbols-outlined text-lg">hourglass_empty</span>
                    Son 1 Gün
                </a>
                <a href="index.php?tab=upcoming<?= $selected_cat ? "&category=" . $selected_cat : "" ?><?= $selected_market ? "&market=" . $selected_market : "" ?><?= $search_query ? "&q=" . urlencode($search_query) : "" ?>#listing-section" 
                   class="px-6 py-4 text-sm font-bold border-b-2 transition-all shrink-0 flex items-center gap-2 <?= $selected_tab === 'upcoming' ? 'border-red-600 text-red-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <span class="material-symbols-outlined text-lg">schedule</span>
                    Yakında Başlayacaklar
                </a>
                <a href="index.php?tab=expired<?= $selected_cat ? "&category=" . $selected_cat : "" ?><?= $selected_market ? "&market=" . $selected_market : "" ?><?= $search_query ? "&q=" . urlencode($search_query) : "" ?>#listing-section" 
                   class="px-6 py-4 text-sm font-bold border-b-2 transition-all shrink-0 flex items-center gap-2 <?= $selected_tab === 'expired' ? 'border-red-600 text-red-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <span class="material-symbols-outlined text-lg">history</span>
                    Süresi Dolanlar
                </a>
            </div>

            <!-- Active Filters Info -->
            <?php if ($selected_cat !== null || $selected_market !== null || !empty($search_query)): ?>
                <div class="flex flex-wrap gap-2 items-center text-sm text-slate-500">
                    <span>Aktif Filtreler:</span>
                    <?php if ($selected_cat !== null): ?>
                        <span class="bg-slate-200 text-slate-800 px-3 py-1 rounded-full font-bold text-xs flex items-center gap-1">
                            Kategori: <?= htmlspecialchars($pdo->query("SELECT name FROM categories WHERE id = $selected_cat")->fetchColumn()) ?>
                            <a href="index.php?tab=<?= $selected_tab ?><?= $selected_market ? "&market=" . $selected_market : "" ?><?= $search_query ? "&q=" . urlencode($search_query) : "" ?>" class="hover:text-red-500 material-symbols-outlined text-xs font-bold leading-none">close</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($selected_market !== null): ?>
                        <span class="bg-slate-200 text-slate-800 px-3 py-1 rounded-full font-bold text-xs flex items-center gap-1">
                            Market: <?= htmlspecialchars($pdo->query("SELECT name FROM markets WHERE id = $selected_market")->fetchColumn()) ?>
                            <a href="index.php?tab=<?= $selected_tab ?><?= $selected_cat ? "&category=" . $selected_cat : "" ?><?= $search_query ? "&q=" . urlencode($search_query) : "" ?>" class="hover:text-red-500 material-symbols-outlined text-xs font-bold leading-none">close</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($search_query)): ?>
                        <span class="bg-slate-200 text-slate-800 px-3 py-1 rounded-full font-bold text-xs flex items-center gap-1">
                            Arama: "<?= htmlspecialchars($search_query) ?>"
                            <a href="index.php?tab=<?= $selected_tab ?><?= $selected_cat ? "&category=" . $selected_cat : "" ?><?= $selected_market ? "&market=" . $selected_market : "" ?>" class="hover:text-red-500 material-symbols-outlined text-xs font-bold leading-none">close</a>
                        </span>
                    <?php endif; ?>
                    <a href="index.php?tab=<?= $selected_tab ?>" class="text-xs text-red-600 hover:text-red-500 font-bold ml-1">Filtreleri Sıfırla</a>
                </div>
            <?php endif; ?>

            <!-- Grid listing brochures -->
            <?php if (empty($brochures)): ?>
                <div class="py-24 text-center text-slate-400 bg-white border border-slate-100 rounded-3xl shadow-sm">
                    <span class="material-symbols-outlined text-5xl mb-3 block text-slate-300">find_in_page</span>
                    Bu filtreleme kriterlerine uygun aktif broşür bulunamadı.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
                    <?php 
                    $bIndex = 0;
                    foreach ($brochures as $b): 
                        $lazyLoading = ($bIndex < 4) ? 'fetchpriority="high" loading="eager"' : 'loading="lazy"';
                    ?>
                        <div class="bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 group cursor-pointer flex flex-col relative"
                             onclick="window.location='viewer.php?id=<?= $b['id'] ?>'">
                            
                            <!-- Cover Image Container -->
                            <div class="relative aspect-[3/4] bg-slate-900/5 overflow-hidden">
                                <img src="uploads/brochures/<?= htmlspecialchars($b['cover_image']) ?>" 
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                                     alt=""
                                     <?= $lazyLoading ?>
                                     onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'100\'><rect width=\'80\' height=\'100\' fill=\'%23f1f5f9\'/><text x=\'50%%27 y=\'50%%27 dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'sans-serif\' font-size=\'10\' fill=\'%2394a3b8\'>RESİM YOK</text></svg>'">
                                
                                <!-- Dynamic countdown badge -->
                                <div class="absolute top-4 left-4 z-10">
                                    <?= brochure_status_badge_html($selected_tab, $b['start_date'], $b['end_date'], $today) ?>
                                </div>
                                
                                <!-- AI analysis badge (top right, only if analyzed) -->
                                <?php if (brochure_has_ai_analysis($b)): ?>
                                    <div class="absolute z-10" style="top: 1rem; right: 1rem;">
                                        <span class="text-white flex items-center justify-center material-symbols-outlined"
                                              style="width: 2.5rem; height: 2.5rem; background: #6d28d9; border-radius: 1rem; border: 2px solid #fff; box-shadow: 0 16px 35px rgba(76, 29, 149, .32); font-size: 22px;"
                                              title="Yapay zeka ürün analizi yapıldı">smart_toy</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Market logo overlay in bottom left corner -->
                                <div class="absolute bottom-3 left-3 bg-white border border-slate-100 rounded-xl p-1.5 shadow-md flex items-center justify-center w-11 h-11">
                                    <?php if ($b['market_logo']): ?>
                                        <img src="uploads/markets/<?= htmlspecialchars($b['market_logo']) ?>" class="w-full h-full object-contain rounded" alt="" width="44" height="44">
                                    <?php else: ?>
                                        <div class="text-[10px] font-bold text-slate-400">LOG</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Details -->
                            <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                                <div>
                                    <span class="text-xs font-bold text-red-600 tracking-wider uppercase mb-1.5 block"><?= htmlspecialchars($b['market_name']) ?></span>
                                    <h3 class="font-title text-base font-bold text-slate-800 line-clamp-2 hover:text-red-600 transition-colors" title="<?= htmlspecialchars($b['title']) ?>">
                                        <?= htmlspecialchars($b['title']) ?>
                                    </h3>
                                </div>
                                
                                <div class="flex justify-between items-center border-t border-slate-100 pt-4 text-xs">
                                    <span class="text-slate-600 font-semibold flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">date_range</span>
                                        <?= date('d.m.Y', strtotime($b['start_date'])) ?> - <?= date('d.m.Y', strtotime($b['end_date'])) ?>
                                    </span>
                                    <span class="text-red-600 font-bold flex items-center gap-0.5 group-hover:translate-x-0.5 transition-transform">
                                        İncele 
                                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php 
                        $bIndex++;
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Son Blog Yazıları / Recent Blog Posts Section -->
        <?php if (!empty($recent_blogs)): ?>
            <section class="space-y-8 mt-16">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <h2 class="font-title text-xl md:text-2xl font-extrabold text-slate-900 flex items-center gap-2">
                        <span class="text-red-600 material-symbols-outlined text-2xl font-black">article</span>
                        Son Blog Yazıları
                    </h2>
                    <a href="blog.php" class="text-xs font-bold text-red-600 hover:text-red-500 transition flex items-center gap-1">
                        Tüm Yazıları Gör <span class="material-symbols-outlined text-sm">chevron_right</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <?php foreach ($recent_blogs as $post): ?>
                        <article class="bg-white border border-slate-100 rounded-3xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 flex flex-col group">
                            <div class="h-44 overflow-hidden bg-slate-100 relative">
                                <img src="<?= htmlspecialchars($site_url . '/' . ($post['cover_image'] ?: 'uploads/blog_cover_default.png')) ?>" 
                                     alt="<?= htmlspecialchars($post['title']) ?>" 
                                     class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                <div class="absolute top-4 left-4 bg-red-600 text-white text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-md shadow-sm">
                                    Tasarruf
                                </div>
                            </div>
                            <div class="p-5 flex-1 flex flex-col space-y-3">
                                <div class="flex items-center gap-1.5 text-slate-400 text-xs font-medium">
                                    <span class="material-symbols-outlined text-sm">calendar_month</span>
                                    <span><?= formatBlogDate($post['created_at']) ?></span>
                                </div>
                                <h3 class="font-title text-base font-bold text-slate-900 group-hover:text-red-600 transition leading-snug">
                                    <a href="blog-detay.php?slug=<?= htmlspecialchars($post['slug']) ?>">
                                        <?= htmlspecialchars($post['title']) ?>
                                    </a>
                                </h3>
                                <p class="text-slate-500 text-xs line-clamp-3 leading-relaxed flex-1">
                                     <?= htmlspecialchars($post['summary']) ?>
                                </p>
                                <div class="pt-3 border-t border-slate-50 flex items-center justify-between">
                                    <a href="blog-detay.php?slug=<?= htmlspecialchars($post['slug']) ?>" 
                                       class="inline-flex items-center gap-1 text-xs font-bold text-slate-900 group-hover:text-red-600 transition-colors">
                                        Devamını Oku 
                                        <span class="material-symbols-outlined text-sm font-black group-hover:translate-x-1 transition-transform">arrow_forward</span>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- E-Bülten / Newsletter Subscription Section -->
        <section class="py-12 bg-gradient-to-tr from-slate-900 via-slate-950 to-slate-900 rounded-3xl border border-slate-800 text-center relative overflow-hidden px-6 shadow-xl mt-12">
            <div class="absolute top-[-50%] left-[-20%] w-[60%] h-[120%] rounded-full bg-red-500/5 blur-[100px] pointer-events-none"></div>
            
            <span class="w-12 h-12 rounded-2xl bg-red-500/10 text-red-500 flex items-center justify-center material-symbols-outlined text-2xl mx-auto mb-4 border border-red-500/20">
                mail
            </span>
            
            <h2 class="font-title text-2xl md:text-3xl font-black text-white mb-2 tracking-tight">İndirimlerden İlk Siz Haberdar Olun</h2>
            <p class="text-slate-400 text-sm md:text-base mb-8 max-w-lg mx-auto font-medium">
                Yeni market broşürleri yüklendiğinde anında e-posta bildirimleri almak için bültenimize ücretsiz kayıt olun.
            </p>
            
            <form id="newsletter-form" onsubmit="submitSubscription(event)" class="max-w-md mx-auto flex flex-col sm:flex-row gap-3 relative z-10">
                <input type="email" id="subscriber-email" required
                       class="flex-1 bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-2xl px-5 py-3.5 outline-none text-sm placeholder:text-slate-500"
                       placeholder="E-posta adresinizi yazın...">
                <button type="submit" 
                        class="bg-red-600 hover:bg-red-500 text-white font-bold px-6 py-3.5 rounded-2xl transition shadow-lg shadow-red-600/15 text-sm shrink-0 flex items-center justify-center gap-1.5">
                    Abone Ol
                    <span class="material-symbols-outlined text-sm">send</span>
                </button>
            </form>
            <div id="subscription-message" class="text-xs font-semibold mt-4 hidden"></div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-100 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex flex-col items-center md:items-start gap-2">
                <a href="index.php">
                    <?php if (file_exists('uploads/logo.png')): ?>
                        <img src="uploads/logo.png" alt="marketisleri.com" class="h-20 w-auto object-contain" width="160" height="80">
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
                <a href="blog.php" class="hover:text-red-600 transition">Blog</a>
                <a href="gizlilik-politikasi.php" class="hover:text-red-600 transition">Gizlilik Politikası</a>
                <a href="kullanim-kosullari.php" class="hover:text-red-600 transition">Kullanım Koşulları</a>
                <a href="cerez-politikasi.php" class="hover:text-red-600 transition">Çerez Politikası</a>
                <a href="iletisim.php" class="hover:text-red-600 transition font-bold">İletişim</a>
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
    <script>
        async function submitSubscription(e) {
            e.preventDefault();
            const emailInput = document.getElementById('subscriber-email');
            const msgDiv = document.getElementById('subscription-message');
            const email = emailInput.value;

            msgDiv.className = "text-xs font-semibold mt-4 text-slate-400";
            msgDiv.innerText = "Kaydediliyor...";
            msgDiv.classList.remove('hidden');

            try {
                const formData = new FormData();
                formData.append('email', email);

                const response = await fetch('subscribe.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    msgDiv.className = "text-xs font-semibold mt-4 text-emerald-400";
                    msgDiv.innerText = data.message;
                    emailInput.value = '';
                } else {
                    msgDiv.className = "text-xs font-semibold mt-4 text-red-400";
                    msgDiv.innerText = data.message;
                }
            } catch (error) {
                msgDiv.className = "text-xs font-semibold mt-4 text-red-400";
                msgDiv.innerText = "Bir hata oluştu. Lütfen bağlantınızı kontrol edip tekrar deneyin.";
            }
        }

        // Interactive mouse glow follow
        const heroSection = document.getElementById('hero-section');
        const mouseGlow = document.getElementById('hero-mouse-glow');

        if (heroSection && mouseGlow) {
            heroSection.addEventListener('mousemove', (e) => {
                const rect = heroSection.getBoundingClientRect();
                const x = e.clientX - rect.left - 175; // Subtract radius (350/2)
                const y = e.clientY - rect.top - 175;  // Subtract radius (350/2)
                
                mouseGlow.style.transform = `translate3d(${x}px, ${y}px, 0)`;
                if (mouseGlow.style.left === '-999px') {
                    mouseGlow.style.left = '0px';
                    mouseGlow.style.top = '0px';
                }
            });
            heroSection.addEventListener('mouseleave', () => {
                mouseGlow.style.left = '-999px';
                mouseGlow.style.top = '-999px';
            });
        }
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

        // Keep scroll position on tab clicks
        const tabLinks = document.querySelectorAll('#listing-section a[href*="tab="]');
        tabLinks.forEach(link => {
            link.addEventListener('click', () => {
                sessionStorage.setItem('tabScrollY', window.scrollY);
            });
        });

        // Restore scroll position on load
        window.addEventListener('DOMContentLoaded', () => {
            const savedScrollY = sessionStorage.getItem('tabScrollY');
            if (savedScrollY !== null) {
                window.scrollTo(0, parseFloat(savedScrollY));
                sessionStorage.removeItem('tabScrollY');
            }
        });
    </script>
</body>
</html>
