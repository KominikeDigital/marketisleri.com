<?php
require 'config.php';

// Fetch all settings
$settings_stmt = $pdo->query("SELECT * FROM settings");
$site_settings = [];
while ($row = $settings_stmt->fetch()) {
    $site_settings[$row['key_name']] = $row['value_text'];
}
$social_settings = $site_settings; // backward compatibility

// Pagination & Search parameters
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$posts_per_page = 9;
$offset = ($current_page - 1) * $posts_per_page;

// Count and Fetch queries
$params = [];
$where_clause = "";
if ($search_query !== "") {
    $where_clause = "WHERE title LIKE ? OR summary LIKE ? OR content LIKE ?";
    $params = ["%$search_query%", "%$search_query%", "%$search_query%"];
}

// Get total count
$count_query = "SELECT COUNT(*) FROM blog_posts $where_clause";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_posts = $count_stmt->fetchColumn();
$total_pages = ceil($total_posts / $posts_per_page);

// Fetch posts
$select_query = "SELECT * FROM blog_posts $where_clause ORDER BY created_at DESC LIMIT " . (int)$posts_per_page . " OFFSET " . (int)$offset;
$select_stmt = $pdo->prepare($select_query);

$bind_idx = 1;
foreach ($params as $param) {
    $select_stmt->bindValue($bind_idx++, $param);
}
$select_stmt->execute();
$blog_posts = $select_stmt->fetchAll();

// Formatting date helper
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
    <title>Blog | Akıllı Alışveriş ve İndirim Rehberi - marketisleri.com</title>
    <meta name="description" content="BİM, A101, ŞOK ve Migros market alışverişlerinizde tasarruf etmenizi sağlayacak güncel tüyolar, aktüel ürün incelemeleri ve bütçe rehberleri.">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($site_url) ?>/uploads/logo.png">
    
    <!-- Typography & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" media="print" onload="this.media='all'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    </noscript>
    
    <!-- Tailwind CSS -->
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
                    <img src="uploads/logo.png" alt="marketisleri.com" class="h-16 w-auto object-contain" width="128" height="64">
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
                <a href="blog.php" class="text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">article</span>Blog</a>
                <a href="iletisim.php" class="hover:text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">mail</span>İletişim</a>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="pt-8 max-w-7xl w-full mx-auto px-4 md:px-6 flex-1 pb-16 space-y-10">
        

        <!-- Hero Section -->
        <section class="text-center py-12 bg-gradient-to-tr from-slate-900 via-red-950 to-slate-900 rounded-3xl border border-slate-800 relative overflow-hidden px-4 shadow-xl">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-red-500/10 via-transparent to-transparent pointer-events-none"></div>
            
            <div class="relative z-10 max-w-2xl mx-auto space-y-4">
                <h1 class="font-title text-3xl md:text-5xl font-black text-white tracking-tight leading-tight">
                    marketisleri<span class="text-red-500">.blog</span>
                </h1>
                <p class="text-slate-300 text-sm md:text-base font-medium max-w-lg mx-auto">
                    Mutfak bütçenizi rahatlatacak tüyolar, aktüel ürün incelemeleri ve en akıllı indirim rehberleri burada.
                </p>
                
                <!-- Search Form -->
                <form action="blog.php" method="GET" class="pt-4 max-w-lg mx-auto">
                    <div class="relative flex items-center bg-white rounded-2xl border border-slate-200 shadow-lg p-1.5 focus-within:ring-2 focus-within:ring-red-500 focus-within:border-transparent transition-all">
                        <span class="material-symbols-outlined text-slate-400 ml-3">search</span>
                        <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" 
                               placeholder="Makale veya konu arayın..." 
                               class="w-full pl-2 pr-4 py-2 outline-none text-slate-800 text-sm bg-transparent">
                        <button type="submit" class="bg-red-600 hover:bg-red-500 text-white font-bold text-xs md:text-sm px-5 py-2.5 rounded-xl transition flex items-center gap-1.5 shrink-0 shadow-md">
                            Ara
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Blog Grid Section -->
        <section class="space-y-8">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h2 class="font-title text-xl md:text-2xl font-extrabold text-slate-900 flex items-center gap-2">
                    <span class="text-red-600 material-symbols-outlined text-2xl font-black">article</span>
                    <?php if ($search_query !== ""): ?>
                        "<?= htmlspecialchars($search_query) ?>" Arama Sonuçları (<?= $total_posts ?>)
                    <?php else: ?>
                        Son Eklenen Yazılar
                    <?php endif; ?>
                </h2>
                
                <?php if ($search_query !== ""): ?>
                    <a href="blog.php" class="text-xs font-bold text-red-600 hover:text-red-500 transition flex items-center gap-1">
                        Aramayı Temizle <span class="material-symbols-outlined text-sm">close</span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($blog_posts)): ?>
                <div class="text-center py-16 bg-white border border-slate-100 rounded-3xl shadow-sm space-y-4">
                    <span class="material-symbols-outlined text-5xl text-slate-300">search_off</span>
                    <h3 class="font-title text-lg font-bold text-slate-700">Aramanıza uygun yazı bulunamadı</h3>
                    <p class="text-slate-400 text-sm max-w-sm mx-auto">
                        Farklı anahtar kelimeler deneyebilir veya tüm yazıları listelemek için aramayı temizleyebilirsiniz.
                    </p>
                    <a href="blog.php" class="inline-flex items-center gap-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-4 py-2 rounded-xl transition shadow-sm">
                        Tüm Yazıları Göster
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($blog_posts as $post): ?>
                        <article class="bg-white border border-slate-100 rounded-3xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 flex flex-col group">
                            <!-- Thumbnail -->
                            <div class="h-48 overflow-hidden bg-slate-100 relative">
                                <img src="<?= htmlspecialchars($site_url . '/' . ($post['cover_image'] ?: 'uploads/blog_cover_default.png')) ?>" 
                                     alt="<?= htmlspecialchars($post['title']) ?>" 
                                     class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                <div class="absolute top-4 left-4 bg-red-600 text-white text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-md shadow-sm">
                                    Tasarruf
                                </div>
                            </div>
                            
                            <!-- Body -->
                            <div class="p-6 flex-1 flex flex-col space-y-3">
                                <div class="flex items-center gap-1.5 text-slate-400 text-xs font-medium">
                                    <span class="material-symbols-outlined text-sm">calendar_month</span>
                                    <span><?= formatBlogDate($post['created_at']) ?></span>
                                </div>
                                
                                <h3 class="font-title text-lg font-bold text-slate-900 group-hover:text-red-600 transition leading-snug">
                                    <a href="blog-detay.php?slug=<?= htmlspecialchars($post['slug']) ?>">
                                        <?= htmlspecialchars($post['title']) ?>
                                    </a>
                                </h3>
                                
                                <p class="text-slate-500 text-sm line-clamp-3 leading-relaxed flex-1">
                                    <?= htmlspecialchars($post['summary']) ?>
                                </p>
                                
                                <div class="pt-3 border-t border-slate-50 flex items-center justify-between">
                                    <a href="blog-detay.php?slug=<?= htmlspecialchars($post['slug']) ?>" 
                                       class="inline-flex items-center gap-1 text-sm font-bold text-slate-900 group-hover:text-red-600 transition-colors">
                                        Okumaya Devam Et 
                                        <span class="material-symbols-outlined text-base font-black group-hover:translate-x-1 transition-transform">arrow_forward</span>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="flex items-center justify-center gap-2 pt-6">
                        <!-- Prev button -->
                        <?php if ($current_page > 1): ?>
                            <a href="blog.php?page=<?= $current_page - 1 ?><?= $search_query !== '' ? '&q=' . urlencode($search_query) : '' ?>" 
                               class="w-10 h-10 rounded-xl bg-white border border-slate-200/80 flex items-center justify-center text-slate-600 hover:text-red-600 hover:border-red-500/50 transition shadow-sm">
                                <span class="material-symbols-outlined text-lg font-black">chevron_left</span>
                            </a>
                        <?php endif; ?>

                        <!-- Page numbers -->
                        <?php 
                        $start = max(1, $current_page - 2);
                        $end = min($total_pages, $current_page + 2);
                        for ($p_num = $start; $p_num <= $end; $p_num++): 
                        ?>
                            <a href="blog.php?page=<?= $p_num ?><?= $search_query !== '' ? '&q=' . urlencode($search_query) : '' ?>" 
                               class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm transition shadow-sm <?= $p_num === $current_page ? 'bg-red-600 text-white' : 'bg-white text-slate-600 border border-slate-200/80 hover:text-red-600 hover:border-red-500/50' ?>">
                                <?= $p_num ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Next button -->
                        <?php if ($current_page < $total_pages): ?>
                            <a href="blog.php?page=<?= $current_page + 1 ?><?= $search_query !== '' ? '&q=' . urlencode($search_query) : '' ?>" 
                               class="w-10 h-10 rounded-xl bg-white border border-slate-200/80 flex items-center justify-center text-slate-600 hover:text-red-600 hover:border-red-500/50 transition shadow-sm">
                                <span class="material-symbols-outlined text-lg font-black">chevron_right</span>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
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

            <!-- Links -->
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-slate-500 font-medium my-4 md:my-0">
                <a href="marketler.php" class="hover:text-red-600 transition">Marketler</a>
                <a href="blog.php" class="text-red-600 font-bold transition">Blog</a>
                <a href="gizlilik-politikasi.php" class="hover:text-red-600 transition">Gizlilik Politikası</a>
                <a href="kullanim-kosullari.php" class="hover:text-red-600 transition">Kullanım Koşulları</a>
                <a href="cerez-politikasi.php" class="hover:text-red-600 transition">Çerez Politikası</a>
                <a href="iletisim.php" class="hover:text-red-600 transition">İletişim</a>
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
</body>
</html>
