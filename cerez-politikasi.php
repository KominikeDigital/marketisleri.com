<?php
require 'config.php';

// Fetch all settings
$settings_stmt = $pdo->query("SELECT * FROM settings");
$site_settings = [];
while ($row = $settings_stmt->fetch()) {
    $site_settings[$row['key_name']] = $row['value_text'];
}
$social_settings = $site_settings; // backward compatibility
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
    <title>Çerez Politikası | marketisleri.com</title>
    <meta name="description" content="marketisleri.com Çerez Politikası sayfası. Çerezlerin kullanımı, reklam çerezleri ve tercih ayarlarınız hakkında detaylı bilgilendirme.">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($site_url) ?>/uploads/logo.png">
    
    <!-- Typography & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
    
    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8595320911699983"
         crossorigin="anonymous"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-red-500 selection:text-white">

    <!-- Main Content Area -->
    <main class="pt-8 max-w-4xl w-full mx-auto px-4 md:px-6 flex-1 pb-16">
        
        <!-- Back Navigation Link -->
        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center gap-1.5 text-sm font-bold text-slate-600 hover:text-red-600 transition bg-white border border-slate-200/80 px-4 py-2 rounded-xl shadow-sm">
                <span class="material-symbols-outlined text-sm font-black">arrow_back</span>
                Anasayfaya Geri Dön
            </a>
        </div>

        <!-- Document Card -->
        <div class="bg-white border border-slate-100 rounded-3xl p-8 md:p-12 shadow-md space-y-8">
            <div class="border-b border-slate-100 pb-6">
                <h1 class="font-title text-3xl md:text-4xl font-black text-slate-900 tracking-tight mb-2">
                    Çerez Politikası
                </h1>
                <p class="text-slate-400 text-sm">Son Güncelleme: 6 Haziran 2026</p>
            </div>

            <div class="space-y-6 text-slate-600 leading-relaxed">
                <p>
                    <strong>marketisleri.com</strong> olarak ziyaretçilerimizin kullanıcı deneyimini iyileştirmek, sitemizin performansını analiz etmek ve ilgi alanlarınıza uygun kişiselleştirilmiş reklamlar sunmak amacıyla çerezler (cookies) kullanmaktayız. Bu Çerez Politikası, çerezlerin ne olduğunu, hangi amaçlarla kullanıldığını ve bunları nasıl yönetebileceğinizi açıklamaktadır.
                </p>

                <h3 class="font-title text-xl font-bold text-slate-900 pt-4">1. Çerez Nedir?</h3>
                <p>
                    Çerezler, bir web sitesini ziyaret ettiğinizde tarayıcınız aracılığıyla cihazınıza (bilgisayar, akıllı telefon veya tablet) kaydedilen küçük metin dosyalarıdır. Çerezler, web sitesinin sizi hatırlamasını, tercihlerinizin kaydedilmesini ve siteyle olan etkileşimlerinizin daha hızlı ve sorunsuz olmasını sağlar.
                </p>

                <h3 class="font-title text-xl font-bold text-slate-900 pt-4">2. Kullandığımız Çerez Türleri</h3>
                <p>
                    Sitemizde kullanılan çerezler işlevlerine göre aşağıdaki kategorilere ayrılmaktadır:
                </p>
                <ul class="list-disc pl-6 space-y-2">
                    <li><strong>Zorunlu Çerezler:</strong> Web sitemizin düzgün çalışması, sayfalar arasında güvenli bir şekilde gezinmeniz ve temel özellikleri (örneğin abonelik veya filtreleme tercihleri) kullanabilmeniz için gerekli teknik çerezlerdir. Bunlar devre dışı bırakılamaz.</li>
                    <li><strong>Performans ve Analiz Çerezleri:</strong> Sitemizi kaç kişinin ziyaret ettiğini, hangi sayfaların daha çok tıklandığını ve ziyaretçilerin sitede nasıl hareket ettiğini anonim olarak analiz etmemize yarayan çerezlerdir. Bu çerezler aracılığıyla kişisel kimlik bilgileriniz toplanmaz.</li>
                    <li><strong>Reklam ve Pazarlama Çerezleri:</strong> Sitemizde yer alan Google AdSense gibi üçüncü taraf reklam ağları tarafından kullanılır. Bu çerezler, tarayıcınızın geçmişine dayanarak ilgi alanlarınıza uygun, kişiselleştirilmiş reklamlar göstermek amacıyla kullanılır.</li>
                </ul>

                <h3 class="font-title text-xl font-bold text-slate-900 pt-4">3. Google AdSense ve DoubleClick Çerezi</h3>
                <p>
                    Google, sitemizde reklam yayınlamak için üçüncü taraf satıcı olarak çerezlerden yararlanır. Google'ın DoubleClick çerezini kullanması, sitemize ve internetteki diğer sitelere yaptığınız ziyaretlere dayanarak size kişiselleştirilmiş reklamlar sunmasına olanak tanır.
                </p>
                <p>
                    Dilerseniz <a href="https://adssettings.google.com" target="_blank" rel="noopener" class="text-red-600 hover:text-red-500 font-semibold underline">Google Reklam Ayarları</a> sayfasını ziyaret ederek kişiselleştirilmiş reklamcılık çerezlerini devre dışı bırakabilirsiniz.
                </p>

                <h3 class="font-title text-xl font-bold text-slate-900 pt-4">4. Çerez Tercihlerini Nasıl Yönetebilirsiniz?</h3>
                <p>
                    Web tarayıcınızın ayarlarını değiştirerek çerez kullanımını kısıtlama, engelleme veya silme haklarına sahipsiniz. Popüler tarayıcılarda çerez ayarlarını yönetmek için aşağıdaki yardım sayfalarını inceleyebilirsiniz:
                </p>
                <ul class="list-disc pl-6 space-y-2">
                    <li><a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener" class="hover:text-red-600 underline">Google Chrome</a></li>
                    <li><a href="https://support.mozilla.org/tr/kb/cerezleri-silme-bilgisayarinizdaki-bilgileri-isteyen" target="_blank" rel="noopener" class="hover:text-red-600 underline">Mozilla Firefox</a></li>
                    <li><a href="https://support.apple.com/tr-tr/guide/safari/sfri11471/mac" target="_blank" rel="noopener" class="hover:text-red-600 underline">Safari (Mac)</a></li>
                    <li><a href="https://support.microsoft.com/tr-tr/windows/microsoft-edge-g%C3%B6z atma-verileri-ve-gizlilik-bb8174ba-9d73-dcf2-9b4a-c582b4e640dd" target="_blank" rel="noopener" class="hover:text-red-600 underline">Microsoft Edge</a></li>
                </ul>
                <p class="text-xs text-slate-400">
                    Önemli Not: Çerezleri tamamen devre dışı bırakmanız durumunda, sitemizdeki bazı etkileşimli özelliklerin veya filtrelerin düzgün çalışmayabileceğini lütfen unutmayınız.
                </p>
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
                <a href="gizlilik-politikasi.php" class="hover:text-red-600 transition">Gizlilik Politikası</a>
                <a href="kullanim-kosullari.php" class="hover:text-red-600 transition">Kullanım Koşulları</a>
                <a href="cerez-politikasi.php" class="text-red-600 font-bold transition">Çerez Politikası</a>
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
</body>
</html>
