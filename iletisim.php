<?php
require 'config.php';

// Fetch all settings
$settings_stmt = $pdo->query("SELECT * FROM settings");
$site_settings = [];
while ($row = $settings_stmt->fetch()) {
    $site_settings[$row['key_name']] = $row['value_text'];
}
$social_settings = $site_settings; // backward compatibility

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        $error_msg = 'Lütfen gerekli tüm alanları (Ad Soyad, E-posta, Mesaj) doldurunuz.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Lütfen geçerli bir e-posta adresi giriniz.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            $success_msg = 'Mesajınız başarıyla iletilmiştir. En kısa sürede sizinle iletişime geçeceğiz.';
        } catch (PDOException $e) {
            $error_msg = 'Bir veritabanı hatası oluştu, lütfen daha sonra tekrar deneyiniz.';
        }
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
    <title>İletişim | marketisleri.com</title>
    <meta name="description" content="Sorularınız, reklam teklifleriniz veya geri bildirimleriniz için bizimle iletişime geçin. info@marketisleri.com mail adresimiz ve iletişim formumuz.">
    
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
                <a href="iletisim.php" class="text-red-600 transition flex items-center gap-1"><span class="material-symbols-outlined text-lg">mail</span>İletişim</a>
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
                    Bize Ulaşın
                </span>
                <h1 class="font-title text-3xl md:text-5xl font-black text-white tracking-tight leading-tight">
                    İletişim & Geri Bildirim
                </h1>
                <p class="text-slate-400 text-sm md:text-base max-w-xl mx-auto font-medium leading-relaxed">
                    Soru, öneri, şikayet veya iş birliği talepleriniz için aşağıdaki iletişim kanallarını kullanabilir ya da formu doldurarak bize doğrudan yazabilirsiniz.
                </p>
            </div>
        </section>

        <!-- Form and Details Layout -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Info Panel -->
            <div class="space-y-6">
                <!-- Direct Contact Card -->
                <div class="bg-white border border-slate-100 rounded-3xl p-8 shadow-sm space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-red-500/5 to-rose-500/5 rounded-full blur-xl pointer-events-none"></div>
                    
                    <h3 class="font-title text-xl font-bold text-slate-900 flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-600">contact_support</span>
                        İletişim Bilgileri
                    </h3>
                    
                    <p class="text-slate-500 text-sm leading-relaxed">
                        Tüm soru ve iş birliği teklifleriniz için e-posta adresimiz üzerinden bizimle direkt iletişime geçebilirsiniz.
                    </p>

                    <!-- Email details -->
                    <div class="flex items-center gap-4 p-4 bg-slate-50 border border-slate-100 rounded-2xl group hover:border-red-200 transition duration-200">
                        <span class="w-10 h-10 rounded-xl bg-red-100 text-red-600 flex items-center justify-center material-symbols-outlined flex-shrink-0 group-hover:scale-105 transition-transform">
                            mail
                        </span>
                        <div>
                            <span class="text-xs text-slate-400 font-semibold block uppercase tracking-wider">E-posta</span>
                            <a href="mailto:info@marketisleri.com" class="text-slate-800 font-bold hover:text-red-600 transition text-sm">
                                info@marketisleri.com
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Social Media Card -->
                <?php if (!empty($social_settings['social_facebook']) || !empty($social_settings['social_instagram']) || !empty($social_settings['social_twitter']) || !empty($social_settings['social_youtube'])): ?>
                <div class="bg-white border border-slate-100 rounded-3xl p-8 shadow-sm space-y-4">
                    <h3 class="font-title text-lg font-bold text-slate-900">Sosyal Medya</h3>
                    <p class="text-slate-500 text-xs leading-relaxed">
                        Kampanyaları ve güncel duyuruları sosyal ağlarımızdan da takip edebilirsiniz:
                    </p>
                    <div class="flex gap-3 pt-2">
                        <?php if (!empty($social_settings['social_facebook'])): ?>
                            <a href="<?= htmlspecialchars($social_settings['social_facebook']) ?>" target="_blank" rel="noopener" class="w-10 h-10 rounded-xl bg-slate-50 hover:bg-blue-600 text-slate-500 hover:text-white flex items-center justify-center transition border border-slate-100" title="Facebook">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($social_settings['social_instagram'])): ?>
                            <a href="<?= htmlspecialchars($social_settings['social_instagram']) ?>" target="_blank" rel="noopener" class="w-10 h-10 rounded-xl bg-slate-50 hover:bg-gradient-to-tr hover:from-amber-500 hover:via-red-500 hover:to-purple-600 text-slate-500 hover:text-white flex items-center justify-center transition border border-slate-100" title="Instagram">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($social_settings['social_twitter'])): ?>
                            <a href="<?= htmlspecialchars($social_settings['social_twitter']) ?>" target="_blank" rel="noopener" class="w-10 h-10 rounded-xl bg-slate-50 hover:bg-slate-900 text-slate-500 hover:text-white flex items-center justify-center transition border border-slate-100" title="Twitter / X">
                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($social_settings['social_youtube'])): ?>
                            <a href="<?= htmlspecialchars($social_settings['social_youtube']) ?>" target="_blank" rel="noopener" class="w-10 h-10 rounded-xl bg-slate-50 hover:bg-red-600 text-slate-500 hover:text-white flex items-center justify-center transition border border-slate-100" title="YouTube">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M23.498 6.163a3.003 3.003 0 0 0-2.11-2.11C19.518 3.545 12 3.545 12 3.545s-7.518 0-9.388.507a3.003 3.003 0 0 0-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 0 0 2.11 2.11c1.87.507 9.388.507 9.388.507s7.518 0 9.388-.507a3.003 3.003 0 0 0 2.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Contact Form Panel -->
            <div class="lg:col-span-2">
                <div class="bg-white border border-slate-100 rounded-3xl p-8 md:p-10 shadow-sm space-y-6">
                    <h3 class="font-title text-xl font-bold text-slate-900">İletişim Formu</h3>
                    
                    <?php if (!empty($success_msg)): ?>
                        <div class="bg-emerald-50 border border-emerald-500/30 text-emerald-800 text-sm p-4 rounded-2xl flex items-center gap-3">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                            <span><?= htmlspecialchars($success_msg) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_msg)): ?>
                        <div class="bg-red-50 border border-red-500/30 text-red-800 text-sm p-4 rounded-2xl flex items-center gap-3">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse shrink-0"></span>
                            <span><?= htmlspecialchars($error_msg) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="iletisim.php" class="space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="name" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Ad Soyad <span class="text-red-500">*</span></label>
                                <input type="text" id="name" name="name" required
                                       class="w-full bg-slate-50 border border-slate-200 focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:bg-white text-slate-800 rounded-xl px-4 py-3 outline-none text-sm transition"
                                       placeholder="Adınızı ve soyadınızı girin">
                            </div>
                            <div>
                                <label for="email" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">E-posta Adresi <span class="text-red-500">*</span></label>
                                <input type="email" id="email" name="email" required
                                       class="w-full bg-slate-50 border border-slate-200 focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:bg-white text-slate-800 rounded-xl px-4 py-3 outline-none text-sm transition"
                                       placeholder="E-posta adresinizi girin">
                            </div>
                        </div>

                        <div>
                            <label for="subject" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Konu</label>
                            <input type="text" id="subject" name="subject"
                                   class="w-full bg-slate-50 border border-slate-200 focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:bg-white text-slate-800 rounded-xl px-4 py-3 outline-none text-sm transition"
                                   placeholder="Mesajınızın konusunu girin">
                        </div>

                        <div>
                            <label for="message" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Mesajınız <span class="text-red-500">*</span></label>
                            <textarea id="message" name="message" rows="5" required
                                      class="w-full bg-slate-50 border border-slate-200 focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:bg-white text-slate-800 rounded-xl px-4 py-3 outline-none text-sm transition resize-none"
                                      placeholder="Mesajınızı detaylıca yazın..."></textarea>
                        </div>

                        <button type="submit"
                                class="w-full sm:w-auto bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-bold px-8 py-3.5 rounded-xl transition shadow-lg hover:shadow-red-600/15 text-sm flex items-center justify-center gap-2 cursor-pointer">
                            Gönder
                            <span class="material-symbols-outlined text-sm font-black">send</span>
                        </button>
                    </form>
                </div>
            </div>
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
                <a href="gizlilik-politikasi.php" class="hover:text-red-600 transition">Gizlilik Politikası</a>
                <a href="kullanim-kosullari.php" class="hover:text-red-600 transition">Kullanım Koşulları</a>
                <a href="cerez-politikasi.php" class="hover:text-red-600 transition">Çerez Politikası</a>
                <a href="iletisim.php" class="text-red-600 font-bold transition">İletişim</a>
            </div>

            <!-- Social Media Links -->
            <div class="flex flex-col items-center md:items-end gap-4">
                <?php if (!empty($social_settings['social_facebook']) || !empty($social_settings['social_instagram']) || !empty($social_settings['social_twitter']) || !empty($social_settings['social_youtube'])): ?>
                    <div class="flex gap-4">
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
    </script>
</body>
</html>
