<?php
require 'config.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    header("Location: index.php");
    exit;
}

// Fetch all settings
$settings_stmt = $pdo->query("SELECT * FROM settings");
$site_settings = [];
while ($row = $settings_stmt->fetch()) {
    $site_settings[$row['key_name']] = $row['value_text'];
}
$social_settings = $site_settings; // backward compatibility

// Fetch the market
$market_stmt = $pdo->prepare("SELECT m.*, c.name as category_name FROM markets m LEFT JOIN categories c ON m.category_id = c.id WHERE m.slug = ?");
$market_stmt->execute([$slug]);
$market = $market_stmt->fetch();

if (!$market) {
    header("Location: index.php");
    exit;
}

$today = date('Y-m-d');
$selected_tab = $_GET['tab'] ?? 'active';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build conditions
$conditions = [
    "b.market_id = ?",
    "b.show_on_homepage = 1",
    "((b.cover_image IS NOT NULL AND b.cover_image != '') OR (b.pdf_path IS NOT NULL AND b.pdf_path != '') OR (SELECT COUNT(*) FROM brochure_pages WHERE brochure_id = b.id) > 0 OR EXISTS (SELECT 1 FROM brochure_products bp_visible WHERE bp_visible.brochure_id = b.id AND COALESCE(bp_visible.product_url, '') <> ''))",
    "(COALESCE(b.source_name, '') NOT IN ('amazon', 'hepsiburada')
        OR COALESCE(b.source_url, '') <> ''
        OR EXISTS (
            SELECT 1 FROM brochure_products bp_link
            WHERE bp_link.brochure_id = b.id
              AND COALESCE(bp_link.product_url, '') <> ''
        )
    )"
];
$params = [$market['id']];

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

// Search condition
if (!empty($search_query)) {
    $conditions[] = "b.title LIKE ?";
    $params[] = '%' . $search_query . '%';
}

$sql = "SELECT b.*, m.name as market_name, m.logo as market_logo, m.slug as market_slug,
               ap.product_name AS affiliate_product_name,
               ap.price AS affiliate_product_price,
               ap.product_url AS affiliate_product_url,
               ap.product_image AS affiliate_product_image,
               ap.product_images AS affiliate_product_images,
               ap.rating AS affiliate_product_rating,
               ap.review_count AS affiliate_review_count,
               ap.description AS affiliate_product_description,
               CASE WHEN EXISTS (SELECT 1 FROM brochure_products bp WHERE bp.brochure_id = b.id) THEN 1 ELSE 0 END as has_ai_analysis
        FROM brochures b 
        JOIN markets m ON b.market_id = m.id
        LEFT JOIN brochure_products ap ON ap.id = (
            SELECT bp_first.id
            FROM brochure_products bp_first
            WHERE bp_first.brochure_id = b.id
            ORDER BY bp_first.id ASC
            LIMIT 1
        )
        WHERE " . implode(" AND ", $conditions) . "
        ORDER BY b.start_date DESC, b.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$brochures = $stmt->fetchAll();

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
    $seo_title = htmlspecialchars($market['name']) . " Aktüel Ürün Katalogları ve İndirim Broşürleri | marketisleri.com";
    $seo_desc = htmlspecialchars($market['name']) . " en güncel aktüel ürün katalogları ve haftalık indirim broşürleri. Fırsatları kaçırmamak için broşürleri inceleyin!";
    ?>
    <title><?= $seo_title ?></title>
    <meta name="description" content="<?= $seo_desc ?>">
    
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
    
    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8595320911699983"
         crossorigin="anonymous"></script>

    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }

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
    </style>
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
                <a href="index.php" class="hover:text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">home</span>Anasayfa</a>
                <a href="marketler.php" class="hover:text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">storefront</span>Marketler</a>
                <a href="blog.php" class="hover:text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">article</span>Blog</a>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="pt-8 max-w-7xl w-full mx-auto px-4 md:px-6 flex-1 pb-16 space-y-10">
        
        <!-- Back Navigation Link -->
        <div>
            <a href="index.php" class="inline-flex items-center gap-1.5 text-sm font-bold text-slate-600 hover:text-red-600 transition bg-white border border-slate-200/80 px-4 py-2 rounded-xl shadow-sm">
                <span class="material-symbols-outlined text-sm font-black">arrow_back</span>
                Anasayfaya Geri Dön
            </a>
        </div>

        <!-- Market Header Hero Section -->
        <section class="bg-gradient-to-tr from-slate-950 via-slate-900 to-slate-950 rounded-3xl border border-slate-800 relative overflow-hidden p-8 md:p-12 shadow-xl">
            <!-- Glowing ambient backdrops -->
            <div class="absolute top-[-30%] left-[-10%] w-[50%] h-[90%] rounded-full bg-red-500/5 blur-[100px] pointer-events-none"></div>
            
            <div class="flex flex-col md:flex-row items-center gap-8 relative z-10">
                <!-- Market Logo -->
                <div class="w-28 h-28 md:w-36 md:h-36 rounded-3xl border bg-white flex items-center justify-center p-3 shadow-lg shrink-0">
                    <?php if ($market['logo']): ?>
                        <img src="uploads/markets/<?= htmlspecialchars($market['logo']) ?>" 
                             class="w-full h-full object-contain rounded-2xl" 
                             alt="<?= htmlspecialchars($market['name']) ?>"
                             width="144" height="144">
                    <?php else: ?>
                        <div class="w-full h-full bg-slate-100 rounded-2xl flex items-center justify-center text-slate-400 font-bold text-lg">
                            <?= substr($market['name'], 0, 3) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Market Details -->
                <div class="text-center md:text-left space-y-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-500/10 border border-red-500/20 text-xs font-bold text-red-400 uppercase tracking-widest font-title">
                        <?= htmlspecialchars($market['category_name'] ?? 'Kategori Yok') ?>
                    </span>
                    <h1 class="font-title text-3xl md:text-5xl font-black text-white tracking-tight leading-tight">
                        <?= htmlspecialchars($market['name']) ?> <span class="text-slate-400 font-normal">Broşürleri</span>
                    </h1>
                    <?php if ($market['description']): ?>
                        <p class="text-slate-400 max-w-2xl text-sm md:text-base leading-relaxed">
                            <?= htmlspecialchars($market['description']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Search Bar Inside Market -->
        <section class="max-w-2xl mx-auto">
            <form method="GET" action="market.php" class="relative group">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($selected_tab) ?>">
                
                <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" 
                       class="w-full p-4 pl-12 pr-24 rounded-2xl border border-slate-200 shadow focus:ring-4 focus:ring-red-500/10 focus:border-red-500 outline-none text-slate-800 bg-white transition-all text-sm placeholder:text-slate-400" 
                       placeholder="<?= htmlspecialchars($market['name']) ?> broşürlerinde ara...">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 group-focus-within:text-red-500 transition-colors">search</span>
                
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 bg-red-600 hover:bg-red-500 text-white font-bold px-5 py-2.5 rounded-xl transition text-xs">
                    Ara
                </button>
            </form>
        </section>

        <!-- Tabs & Brochures Listing -->
        <section id="listing-section" class="space-y-6">
            <!-- Tabs -->
            <div class="flex border-b border-slate-200 overflow-x-auto no-scrollbar">
                <a href="market.php?slug=<?= htmlspecialchars($slug) ?>&tab=active<?= $search_query ? "&q=" . urlencode($search_query) : "" ?>#listing-section" 
                   class="px-6 py-4 text-sm font-bold border-b-2 transition-all shrink-0 flex items-center gap-2 <?= $selected_tab === 'active' ? 'border-red-600 text-red-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <span class="material-symbols-outlined text-lg">local_fire_department</span>
                    Aktif Broşürler
                </a>
                <a href="market.php?slug=<?= htmlspecialchars($slug) ?>&tab=ending_soon<?= $search_query ? "&q=" . urlencode($search_query) : "" ?>#listing-section" 
                   class="px-6 py-4 text-sm font-bold border-b-2 transition-all shrink-0 flex items-center gap-2 <?= $selected_tab === 'ending_soon' ? 'border-red-600 text-red-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <span class="material-symbols-outlined text-lg">hourglass_empty</span>
                    Son 1 Gün
                </a>
                <a href="market.php?slug=<?= htmlspecialchars($slug) ?>&tab=upcoming<?= $search_query ? "&q=" . urlencode($search_query) : "" ?>#listing-section" 
                   class="px-6 py-4 text-sm font-bold border-b-2 transition-all shrink-0 flex items-center gap-2 <?= $selected_tab === 'upcoming' ? 'border-red-600 text-red-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <span class="material-symbols-outlined text-lg">schedule</span>
                    Yakında Başlayacaklar
                </a>
                <a href="market.php?slug=<?= htmlspecialchars($slug) ?>&tab=expired<?= $search_query ? "&q=" . urlencode($search_query) : "" ?>#listing-section" 
                   class="px-6 py-4 text-sm font-bold border-b-2 transition-all shrink-0 flex items-center gap-2 <?= $selected_tab === 'expired' ? 'border-red-600 text-red-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                    <span class="material-symbols-outlined text-lg">history</span>
                    Süresi Dolanlar
                </a>
            </div>

            <!-- Active Filters Info -->
            <?php if (!empty($search_query)): ?>
                <div class="flex flex-wrap gap-2 items-center text-sm text-slate-500">
                    <span>Arama Sonucu:</span>
                    <span class="bg-slate-200 text-slate-800 px-3 py-1 rounded-full font-bold text-xs flex items-center gap-1">
                        "<?= htmlspecialchars($search_query) ?>"
                        <a href="market.php?slug=<?= htmlspecialchars($slug) ?>&tab=<?= $selected_tab ?>" class="hover:text-red-500 material-symbols-outlined text-xs font-bold leading-none">close</a>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Grid listing brochures -->
            <?php if (empty($brochures)): ?>
                <div class="py-24 text-center text-slate-400 bg-white border border-slate-100 rounded-3xl shadow-sm">
                    <span class="material-symbols-outlined text-5xl mb-3 block text-slate-300">find_in_page</span>
                    Bu markete ait aktif broşür bulunamadı.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
                    <?php 
                    $bIndex = 0;
                    foreach ($brochures as $b): 
                        // Performance optimization: lazy load below the fold images
                        $lazyLoading = ($bIndex < 4) ? 'fetchpriority="high"' : 'loading="lazy"';
                    ?>
                        <?php include __DIR__ . '/partials/brochure_card.php'; ?>
                    <?php 
                        $bIndex++;
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
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

            <!-- Legal Links -->
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-slate-500 font-medium my-4 md:my-0">
                <a href="marketler.php" class="hover:text-red-600 transition">Marketler</a>
                <a href="blog.php" class="hover:text-red-600 transition">Blog</a>
                <a href="gizlilik-politikasi.php" class="hover:text-red-600 transition">Gizlilik Politikası</a>
                <a href="kullanim-kosullari.php" class="hover:text-red-600 transition">Kullanım Koşulları</a>
                <a href="cerez-politikasi.php" class="hover:text-red-600 transition">Çerez Politikası</a>
            </div>

            <div class="text-slate-400 text-xs text-center md:text-right space-y-1">
                <p>&copy; 2026 marketisleri.com All rights reserved.</p>
                <p><a href="https://kominikee.com" target="_blank" rel="noopener" class="text-red-600 hover:text-red-500 font-semibold">Kominike "Creative" Digital Project</a></p>
            </div>
        </div>
    </footer>
    <script>
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
