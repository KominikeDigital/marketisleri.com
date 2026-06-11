<?php
require 'config.php';

// Fetch all settings
$settings_stmt = $pdo->query("SELECT * FROM settings");
$site_settings = [];
while ($row = $settings_stmt->fetch()) {
    $site_settings[$row['key_name']] = $row['value_text'];
}
$social_settings = $site_settings; // backward compatibility

$today = date('Y-m-d');

// Fetch all categories for filtering
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch all markets with active brochure counts
$markets = $pdo->query("
    SELECT m.*, c.name as category_name,
           (SELECT COUNT(*) FROM brochures b WHERE b.market_id = m.id AND b.start_date <= '$today' AND b.end_date >= '$today') as active_count
    FROM markets m
    LEFT JOIN categories c ON m.category_id = c.id
    ORDER BY m.name ASC
")->fetchAll();

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
    <title>Tüm Marketler ve İndirim Markaları | marketisleri.com</title>
    <meta name="description" content="BİM, A101, ŞOK, Migros, Carrefoursa, Metro ve diğer tüm süpermarketler ile yapı, teknoloji, kozmetik markalarının güncel broşür listesi.">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="uploads/logo.png">
    
    <!-- Typography & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <!-- Pre-compiled Tailwind CSS -->
    <link rel="stylesheet" href="uploads/tailwind.min.css">
    
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
        
        /* Custom layout classes since pruned tailwind.min.css does not include these rules */
        #markets-grid {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }
        @media (min-width: 768px) {
            #markets-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
            }
        }
        .market-logo-img {
            height: 60% !important;
            width: auto !important;
            max-width: 85% !important;
            object-fit: contain !important;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-red-500 selection:text-white">

    <!-- Header Navigation -->
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
                <a href="marketler.php" class="text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">storefront</span>Marketler</a>
                <a href="iletisim.php" class="hover:text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">mail</span>İletişim</a>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="pt-8 max-w-7xl w-full mx-auto px-4 md:px-6 flex-1 pb-16 space-y-10">

        <!-- Page Header -->
        <section class="text-center py-12 bg-gradient-to-tr from-slate-950 via-slate-900 to-slate-950 rounded-3xl border border-slate-800 relative overflow-hidden px-4 shadow-xl">
            <!-- Glowing ambient backdrops -->
            <div class="absolute top-[-30%] left-[-10%] w-[50%] h-[90%] rounded-full bg-red-500/5 blur-[100px] pointer-events-none"></div>
            
            <div class="relative z-10 space-y-3">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-red-500/10 border border-red-500/20 text-xs font-bold text-red-400 uppercase tracking-widest font-title">
                    Tüm Markalar
                </span>
                <h1 class="font-title text-3xl md:text-5xl font-black text-white tracking-tight leading-tight">
                    Anlaşmalı Marketler & Markalar
                </h1>
                <p class="text-slate-400 max-w-xl mx-auto text-sm md:text-base">
                    Broşür ve indirim kataloglarını yayınladığımız tüm markalara buradan hızlıca ulaşabilirsiniz.
                </p>
            </div>
        </section>

        <!-- Live Search and Category Filter -->
        <section class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm space-y-6">
            <div class="max-w-xl mx-auto relative group">
                <input type="text" id="market-search" 
                       class="w-full p-4 pl-12 pr-6 rounded-2xl border border-slate-200 shadow-sm focus:ring-4 focus:ring-red-500/10 focus:border-red-500 outline-none text-slate-800 bg-white transition-all text-sm placeholder:text-slate-400" 
                       placeholder="Market veya marka adı arayın..."
                       oninput="filterMarkets()">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 group-focus-within:text-red-500 transition-colors">search</span>
            </div>

            <!-- Category Fast Filters -->
            <div class="flex flex-wrap justify-center gap-2" id="category-filters">
                <button onclick="filterCategory(null)" class="category-btn px-4 py-2 rounded-full text-xs font-bold border transition bg-red-600 border-red-600 text-white" data-cat-id="all">
                    Tümü
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button onclick="filterCategory(<?= $cat['id'] ?>)" class="category-btn px-4 py-2 rounded-full text-xs font-bold border transition bg-white border-slate-200 text-slate-600 hover:border-slate-300" data-cat-id="<?= $cat['id'] ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Markets Grid -->
        <section>
            <div class="grid grid-cols-3 md:grid-cols-5 gap-4 md:gap-6" id="markets-grid">
                <?php foreach ($markets as $m): ?>
                    <div class="market-card bg-white border border-slate-100 rounded-2xl md:rounded-3xl p-3 md:p-5 shadow-sm hover:shadow-md hover:-translate-y-1 transition-all duration-300 flex flex-col items-center justify-between text-center cursor-pointer group relative"
                         data-name="<?= strtolower(htmlspecialchars($m['name'])) ?>"
                         data-category="<?= $m['category_id'] ?>"
                         onclick="window.location='market.php?slug=<?= htmlspecialchars($m['slug']) ?>'">
                        
                        <!-- Logo Container -->
                        <div class="w-full aspect-square rounded-xl md:rounded-2xl bg-white border border-slate-100 p-2 md:p-3 flex items-center justify-center shadow-inner transition-transform group-hover:scale-105 duration-300 relative">
                            <?php if ($m['logo']): ?>
                                <img src="uploads/markets/<?= htmlspecialchars($m['logo']) ?>" 
                                     class="market-logo-img rounded-lg md:rounded-xl" 
                                     alt="<?= htmlspecialchars($m['name']) ?>"
                                     onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\'><rect width=\'80\' height=\'80\' fill=\'%23f8fafc\'/><text x=\'50%%27 y=\'50%%27 dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'sans-serif\' font-size=\'14\' font-weight=\'bold\' fill=\'%23cbd5e1\'><?= substr(htmlspecialchars($m['name']), 0, 3) ?></text></svg>'">
                            <?php else: ?>
                                <div class="w-full h-full bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 font-bold text-lg">
                                    <?= substr(htmlspecialchars($m['name']), 0, 3) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Brand Title & Active Count -->
                        <div class="mt-4 space-y-1">
                            <h3 class="font-title font-bold text-slate-800 group-hover:text-red-600 transition-colors text-sm line-clamp-1">
                                <?= htmlspecialchars($m['name']) ?>
                            </h3>
                            <span class="text-xs font-semibold text-slate-400 block">
                                <?= htmlspecialchars($m['category_name'] ?? 'Kategori Yok') ?>
                            </span>
                            
                            <!-- Active Badge -->
                            <div class="pt-2">
                                <?php if ($m['active_count'] > 0): ?>
                                    <span class="inline-block px-2.5 py-1 text-[10px] font-black rounded-full bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 uppercase tracking-wide">
                                        <?= $m['active_count'] ?> AKTİF BROŞÜR
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block px-2.5 py-1 text-[10px] font-bold rounded-full bg-slate-100 text-slate-400 border border-slate-200/50 uppercase tracking-wide">
                                        AKTİF BROŞÜR YOK
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty State -->
            <div id="empty-state" class="py-24 text-center text-slate-400 bg-white border border-slate-100 rounded-3xl shadow-sm hidden">
                <span class="material-symbols-outlined text-5xl mb-3 block text-slate-300">storefront</span>
                Arama kriterlerine uygun market bulunamadı.
            </div>
        </section>

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
                <a href="gizlilik-politikasi.php" class="hover:text-red-600 transition">Gizlilik Politikası</a>
                <a href="kullanim-kosullari.php" class="hover:text-red-600 transition">Kullanım Koşulları</a>
                <a href="cerez-politikasi.php" class="hover:text-red-600 transition">Çerez Politikası</a>
                <a href="iletisim.php" class="hover:text-red-600 transition">İletişim</a>
            </div>

            <div class="text-slate-400 text-xs text-center md:text-right space-y-1">
                <p>&copy; 2026 marketisleri.com All rights reserved.</p>
                <p><a href="https://kominikee.com" target="_blank" rel="noopener" class="text-red-600 hover:text-red-500 font-semibold">Kominike "Creative" Digital Project</a></p>
            </div>
        </div>
    </footer>

    <!-- Client-Side Search and Filter Logic -->
    <script>
        let currentCategory = null;

        function filterMarkets() {
            const query = document.getElementById('market-search').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.market-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const category = card.getAttribute('data-category');
                
                const matchesSearch = name.includes(query);
                const matchesCategory = (currentCategory === null || String(category) === String(currentCategory));

                if (matchesSearch && matchesCategory) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            const emptyState = document.getElementById('empty-state');
            const grid = document.getElementById('markets-grid');
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
                grid.classList.add('hidden');
            } else {
                emptyState.classList.add('hidden');
                grid.classList.remove('hidden');
            }
        }

        function filterCategory(catId) {
            currentCategory = catId;
            
            // Update button styles
            const buttons = document.querySelectorAll('.category-btn');
            buttons.forEach(btn => {
                const btnCatId = btn.getAttribute('data-cat-id');
                const targetId = catId === null ? 'all' : String(catId);
                
                if (btnCatId === targetId) {
                    btn.className = "category-btn px-4 py-2 rounded-full text-xs font-bold border transition bg-red-600 border-red-600 text-white";
                } else {
                    btn.className = "category-btn px-4 py-2 rounded-full text-xs font-bold border transition bg-white border-slate-200 text-slate-600 hover:border-slate-300";
                }
            });

            filterMarkets();
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
    </script>
</body>
</html>
