<?php
require 'config.php';

// Fetch all settings
$settings_stmt = $pdo->query("SELECT * FROM settings");
$site_settings = [];
while ($row = $settings_stmt->fetch()) {
    $site_settings[$row['key_name']] = $row['value_text'];
}
$social_settings = $site_settings; // backward compatibility

// Get post slug
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === "") {
    header("Location: blog.php");
    exit;
}

// Fetch post
$stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = ?");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    header("Location: blog.php");
    exit;
}

// Fetch recent posts for sidebar (exclude current)
$recent_stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id != ? ORDER BY created_at DESC LIMIT 5");
$recent_stmt->execute([$post['id']]);
$recent_posts = $recent_stmt->fetchAll();

// Fetch popular markets for sidebar
$popular_markets = $pdo->query("SELECT * FROM markets WHERE is_popular = 1 ORDER BY sort_order ASC, name ASC LIMIT 6")->fetchAll();

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

$current_url = htmlspecialchars($site_url . '/blog-detay.php?slug=' . $post['slug']);
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
    <title><?= htmlspecialchars($post['title']) ?> | marketisleri.com</title>
    <meta name="description" content="<?= htmlspecialchars(mb_substr(strip_tags($post['summary']), 0, 160)) ?>">
    
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
        
        /* Premium custom typography styles for blog content */
        .blog-rich-content h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 1.35rem;
            color: #0f172a;
            margin-top: 1.75rem;
            margin-bottom: 0.75rem;
            line-height: 1.25;
        }
        .blog-rich-content p {
            color: #475569;
            font-size: 1rem;
            line-height: 1.75;
            margin-bottom: 1.25rem;
        }
        .blog-rich-content ul {
            list-style-type: disc;
            padding-left: 1.5rem;
            margin-bottom: 1.25rem;
            color: #475569;
            font-size: 1rem;
            line-height: 1.75;
        }
        .blog-rich-content li {
            margin-bottom: 0.5rem;
        }
        .blog-rich-content strong {
            font-weight: 700;
            color: #0f172a;
        }
        .blog-rich-content a {
            color: #dc2626;
            font-weight: 600;
            text-decoration: underline;
            transition: color 0.2s;
        }
        .blog-rich-content a:hover {
            color: #b91c1c;
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
    <main class="pt-8 max-w-7xl w-full mx-auto px-4 md:px-6 flex-1 pb-16">
        
        <!-- Header Banner Ad -->
        <?php if (($site_settings['adsense_active'] ?? '1') === '1'): ?>
            <div class="ad-banner-container w-full bg-white border border-slate-200/60 rounded-2xl p-4 text-center text-xs font-bold text-slate-400 tracking-wider mb-6 relative overflow-hidden select-none">
                <div class="absolute inset-0 bg-gradient-to-r from-red-500/5 to-rose-500/5 pointer-events-none"></div>
                <span class="material-symbols-outlined text-sm inline-block align-middle mr-1 text-slate-400">ads_click</span>
                GOOGLE ADSENSE REKLAM ALANI
            </div>
        <?php endif; ?>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
            
            <!-- Left: Main Article Column -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Back Navigation -->
                <div>
                    <a href="blog.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-red-600 transition bg-white border border-slate-200/80 px-4 py-2.5 rounded-xl shadow-sm">
                        <span class="material-symbols-outlined text-sm font-black">arrow_back</span>
                        Blog Yazılarına Dön
                    </a>
                </div>

                <!-- Article Card -->
                <article class="bg-white border border-slate-100 rounded-3xl p-6 md:p-10 shadow-md space-y-6">
                    <!-- Title & Meta Header -->
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3 text-slate-400 text-xs font-medium">
                            <span class="bg-red-50 text-red-600 font-bold uppercase px-2.5 py-1 rounded-md text-[10px]">Tasarruf</span>
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">calendar_month</span>
                                <?= formatBlogDate($post['created_at']) ?>
                            </span>
                        </div>
                        
                        <h1 class="font-title text-2xl md:text-4xl font-black text-slate-900 tracking-tight leading-tight">
                            <?= htmlspecialchars($post['title']) ?>
                        </h1>
                        
                        <p class="text-slate-500 text-base font-semibold leading-relaxed border-l-4 border-red-500 pl-4 bg-slate-50 py-3 pr-4 rounded-r-2xl">
                            <?= htmlspecialchars($post['summary']) ?>
                        </p>
                    </div>

                    <!-- Featured Image -->
                    <div class="rounded-2xl overflow-hidden bg-slate-100 border border-slate-100 relative h-64 md:h-[400px]">
                        <img src="<?= htmlspecialchars($site_url . '/' . ($post['cover_image'] ?: 'uploads/blog_cover_default.png')) ?>" 
                             alt="<?= htmlspecialchars($post['title']) ?>" 
                             class="w-full h-full object-cover">
                    </div>

                    <!-- Article Body Content -->
                    <div class="blog-rich-content pt-4 border-t border-slate-50">
                        <?= $post['content'] ?>
                    </div>

                    <!-- Social Sharing Section -->
                    <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <span class="text-sm font-bold text-slate-700">Bu yazıyı paylaşın:</span>
                        <div class="flex flex-wrap gap-2">
                            <!-- WhatsApp -->
                            <a href="https://api.whatsapp.com/send?text=<?= urlencode($post['title'] . ' ' . $current_url) ?>" 
                               target="_blank" rel="noopener" 
                               class="inline-flex items-center gap-1.5 text-xs font-bold bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-xl transition shadow-sm">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.457L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.625 1.449 5.49-.001 9.951-4.467 9.953-9.96.002-2.661-1.034-5.159-2.92-7.047C16.42 1.71 13.928.665 11.272.665c-5.492 0-9.952 4.467-9.955 9.96-.001 1.702.463 3.361 1.34 4.811L1.654 21.07l5.793-1.516z-1.127-1.105"/></svg>
                                WhatsApp
                            </a>
                            <!-- Facebook -->
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($current_url) ?>" 
                               target="_blank" rel="noopener" 
                               class="inline-flex items-center gap-1.5 text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl transition shadow-sm">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                                Facebook
                            </a>
                            <!-- Twitter X -->
                            <a href="https://twitter.com/intent/tweet?text=<?= urlencode($post['title']) ?>&url=<?= urlencode($current_url) ?>" 
                               target="_blank" rel="noopener" 
                               class="inline-flex items-center gap-1.5 text-xs font-bold bg-slate-900 hover:bg-slate-850 text-white px-4 py-2 rounded-xl transition shadow-sm">
                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                X (Twitter)
                            </a>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Right: Sidebar -->
            <div class="lg:col-span-1 space-y-8">
                
                <!-- Recent Posts Widget -->
                <?php if (!empty($recent_posts)): ?>
                    <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm space-y-4">
                        <h3 class="font-title text-sm font-extrabold text-slate-900 uppercase tracking-wider pb-3 border-b border-slate-50 flex items-center gap-2">
                            <span class="text-red-600 material-symbols-outlined text-lg font-black">schedule</span>
                            Son Yazılar
                        </h3>
                        <div class="space-y-4">
                            <?php foreach ($recent_posts as $rp): ?>
                                <div class="flex items-start gap-3 group">
                                    <div class="w-14 h-14 rounded-xl overflow-hidden shrink-0 bg-slate-100 border border-slate-100">
                                        <img src="<?= htmlspecialchars($site_url . '/' . ($rp['cover_image'] ?: 'uploads/blog_cover_default.png')) ?>" 
                                             alt="<?= htmlspecialchars($rp['title']) ?>" 
                                             class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                    </div>
                                    <div class="space-y-1">
                                        <h4 class="text-xs font-bold text-slate-800 line-clamp-2 leading-snug group-hover:text-red-600 transition">
                                            <a href="blog-detay.php?slug=<?= htmlspecialchars($rp['slug']) ?>">
                                                <?= htmlspecialchars($rp['title']) ?>
                                            </a>
                                        </h4>
                                        <span class="text-[10px] text-slate-400 font-semibold block"><?= formatBlogDate($rp['created_at']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Popular Stores Widget -->
                <?php if (!empty($popular_markets)): ?>
                    <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm space-y-4">
                        <h3 class="font-title text-sm font-extrabold text-slate-900 uppercase tracking-wider pb-3 border-b border-slate-50 flex items-center gap-2">
                            <span class="text-red-600 material-symbols-outlined text-lg font-black">storefront</span>
                            Popüler Marketler
                        </h3>
                        <div class="grid grid-cols-2 gap-3">
                            <?php foreach ($popular_markets as $pm): ?>
                                <a href="market.php?slug=<?= htmlspecialchars($pm['slug']) ?>" 
                                   class="border border-slate-100 hover:border-red-500/20 hover:bg-red-50/10 rounded-2xl p-3 flex flex-col items-center justify-center text-center transition group shadow-sm">
                                    <img src="uploads/<?= htmlspecialchars($pm['logo_path']) ?>" 
                                         alt="<?= htmlspecialchars($pm['name']) ?>" 
                                         class="h-10 w-auto object-contain mb-1.5 group-hover:scale-105 transition">
                                    <span class="text-[10px] font-bold text-slate-700 group-hover:text-red-600 transition"><?= htmlspecialchars($pm['name']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Sidebar Ad Banner -->
                <?php if (($site_settings['adsense_active'] ?? '1') === '1'): ?>
                    <div class="bg-white border border-slate-200/60 rounded-3xl p-6 text-center text-xs font-bold text-slate-400 tracking-wider shadow-sm select-none relative overflow-hidden h-64 flex flex-col justify-center items-center">
                        <div class="absolute inset-0 bg-gradient-to-b from-red-500/5 to-rose-500/5 pointer-events-none"></div>
                        <span class="material-symbols-outlined text-2xl mb-2 text-slate-400">ads_click</span>
                        GOOGLE ADSENSE REKLAM ALANI
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom Banner Ad -->
        <?php if (($site_settings['adsense_active'] ?? '1') === '1'): ?>
            <div class="ad-banner-container w-full bg-white border border-slate-200/60 rounded-2xl p-4 text-center text-xs font-bold text-slate-400 tracking-wider relative overflow-hidden select-none">
                <div class="absolute inset-0 bg-gradient-to-r from-red-500/5 to-rose-500/5 pointer-events-none"></div>
                <span class="material-symbols-outlined text-sm inline-block align-middle mr-1 text-slate-400">ads_click</span>
                GOOGLE ADSENSE REKLAM ALANI
            </div>
        <?php endif; ?>
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
