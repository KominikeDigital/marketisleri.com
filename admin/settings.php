<?php
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

// Handle Save Settings
if (isset($_POST['save_settings'])) {
    try {
        $stmt = $pdo->prepare("UPDATE settings SET value_text = ? WHERE key_name = ?");
        
        // Save Social Media
        $stmt->execute([trim($_POST['social_facebook'] ?? ''), 'social_facebook']);
        $stmt->execute([trim($_POST['social_instagram'] ?? ''), 'social_instagram']);
        $stmt->execute([trim($_POST['social_twitter'] ?? ''), 'social_twitter']);
        $stmt->execute([trim($_POST['social_youtube'] ?? ''), 'social_youtube']);
        
        // Save SMTP Config
        $stmt->execute([trim($_POST['smtp_host'] ?? ''), 'smtp_host']);
        $stmt->execute([trim($_POST['smtp_port'] ?? ''), 'smtp_port']);
        $stmt->execute([trim($_POST['smtp_user'] ?? ''), 'smtp_user']);
        $stmt->execute([trim($_POST['smtp_pass'] ?? ''), 'smtp_pass']);
        $stmt->execute([trim($_POST['smtp_secure'] ?? ''), 'smtp_secure']);
        $stmt->execute([trim($_POST['smtp_from_email'] ?? ''), 'smtp_from_email']);
        $stmt->execute([trim($_POST['smtp_from_name'] ?? ''), 'smtp_from_name']);
        
        // Save SEO Settings
        $stmt->execute([trim($_POST['seo_title_home'] ?? ''), 'seo_title_home']);
        $stmt->execute([trim($_POST['seo_description_home'] ?? ''), 'seo_description_home']);
        $stmt->execute([trim($_POST['seo_keywords_home'] ?? ''), 'seo_keywords_home']);
        
        $success = "Tüm ayarlar başarıyla güncellendi.";
    } catch (PDOException $e) {
        $error = "Ayarlar kaydedilirken hata oluştu: " . $e->getMessage();
    }
}

// Fetch Settings
$settings_raw = $pdo->query("SELECT * FROM settings")->fetchAll();
$settings = [];
foreach ($settings_raw as $s) {
    $settings[$s['key_name']] = $s['value_text'];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Ayarları - marketisleri.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
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
            <a href="subscribers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">mail</span>
                Aboneler
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
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
            <h1 class="font-title text-2xl font-bold text-white">Sistem Ayarları</h1>
            <div class="flex items-center gap-4">
                <a href="../" target="_blank" class="flex items-center gap-2 text-sm bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-xl transition">
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                    Siteyi Görüntüle
                </a>
            </div>
        </header>

        <!-- Container -->
        <div class="p-8 space-y-8 max-w-3xl w-full mx-auto">
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

            <form method="POST" class="space-y-8">
                <!-- Settings Form Card 1: Social -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                    <div class="flex items-center gap-3 pb-4 border-b border-slate-800">
                        <span class="material-symbols-outlined text-red-500">share</span>
                        <h3 class="font-title text-lg font-bold text-white">Sosyal Medya Hesapları</h3>
                    </div>

                    <div>
                        <label for="fb" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Facebook Sayfa Linki</label>
                        <input type="url" id="fb" name="social_facebook" value="<?= htmlspecialchars($settings['social_facebook'] ?? '') ?>"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                               placeholder="https://facebook.com/sayfaniz">
                    </div>

                    <div>
                        <label for="ig" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Instagram Profil Linki</label>
                        <input type="url" id="ig" name="social_instagram" value="<?= htmlspecialchars($settings['social_instagram'] ?? '') ?>"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                               placeholder="https://instagram.com/kullaniciadiniz">
                    </div>

                    <div>
                        <label for="tw" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">X / Twitter Profil Linki</label>
                        <input type="url" id="tw" name="social_twitter" value="<?= htmlspecialchars($settings['social_twitter'] ?? '') ?>"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                               placeholder="https://x.com/kullaniciadiniz">
                    </div>

                    <div>
                        <label for="yt" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">YouTube Kanal Linki</label>
                        <input type="url" id="yt" name="social_youtube" value="<?= htmlspecialchars($settings['social_youtube'] ?? '') ?>"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                               placeholder="https://youtube.com/c/kanaliniz">
                    </div>
                </div>

                <!-- Settings Form Card 2: SMTP -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                    <div class="flex items-center gap-3 pb-4 border-b border-slate-800">
                        <span class="material-symbols-outlined text-red-500">mail</span>
                        <h3 class="font-title text-lg font-bold text-white">E-Posta Gönderim (SMTP) Ayarları</h3>
                    </div>
                    
                    <p class="text-xs text-slate-400 bg-slate-950 p-4 rounded-xl border border-slate-800 leading-relaxed">
                        💡 <strong>İpucu:</strong> Eğer SMTP Host alanını boş bırakırsanız sistem, sunucunun varsayılan PHP <code>mail()</code> altyapısını kullanarak gönderim yapacaktır. cPanel gibi canlı hosting panellerinde bu otomatik olarak çalışacaktır.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label for="smtp_host" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                                   placeholder="mail.marketisleri.com veya smtp.gmail.com">
                        </div>
                        <div>
                            <label for="smtp_port" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? '') ?>"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                                   placeholder="465 (SSL) / 587 (TLS)">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label for="smtp_user" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">SMTP Kullanıcı Adı (Mail)</label>
                            <input type="text" id="smtp_user" name="smtp_user" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                                   placeholder="info@marketisleri.com">
                        </div>
                        <div>
                            <label for="smtp_secure" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Güvenlik Protokolü</label>
                            <select id="smtp_secure" name="smtp_secure"
                                    class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition">
                                <option value="" <?= ($settings['smtp_secure'] ?? '') === '' ? 'selected' : '' ?>>Yok (Güvensiz)</option>
                                <option value="ssl" <?= ($settings['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Tavsiye Edilen)</option>
                                <option value="tls" <?= ($settings['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="smtp_pass" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">SMTP Şifre</label>
                        <input type="password" id="smtp_pass" name="smtp_pass" value="<?= htmlspecialchars($settings['smtp_pass'] ?? '') ?>"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                               placeholder="••••••••">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="smtp_from_email" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Gönderen E-Posta Adresi</label>
                            <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                                   placeholder="info@marketisleri.com">
                        </div>
                        <div>
                            <label for="smtp_from_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Gönderen Adı</label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?= htmlspecialchars($settings['smtp_from_name'] ?? '') ?>"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                                   placeholder="marketisleri.com">
                        </div>
                    </div>
                </div>

                <!-- Settings Form Card 3: SEO Settings -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                    <div class="flex items-center gap-3 pb-4 border-b border-slate-800">
                        <span class="material-symbols-outlined text-red-500">travel_explore</span>
                        <h3 class="font-title text-lg font-bold text-white">Arama Motoru Optimizasyonu (SEO) Ayarları</h3>
                    </div>

                    <div>
                        <label for="seo_title" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Anasayfa Tarayıcı Başlığı (SEO Title)</label>
                        <input type="text" id="seo_title" name="seo_title_home" value="<?= htmlspecialchars($settings['seo_title_home'] ?? '') ?>"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                               placeholder="Tüm Market Broşürleri Tek Yerde | marketisleri.com">
                    </div>

                    <div>
                        <label for="seo_desc" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Anasayfa Meta Açıklaması (Meta Description)</label>
                        <textarea id="seo_desc" name="seo_description_home" rows="3"
                                  class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition resize-none"
                                  placeholder="BİM, A101, ŞOK, Migros ve diğer süpermarketlerin en güncel broşürleri..."><?= htmlspecialchars($settings['seo_description_home'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label for="seo_keywords" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Anahtar Kelimeler (Meta Keywords - Virgülle Ayırın)</label>
                        <input type="text" id="seo_keywords" name="seo_keywords_home" value="<?= htmlspecialchars($settings['seo_keywords_home'] ?? '') ?>"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                               placeholder="market broşürleri, aktüel ürünler, bim aktüel...">
                    </div>
                </div>

                <!-- Save Button Container -->
                <div class="flex justify-end">
                    <button type="submit" name="save_settings"
                            class="bg-red-600 hover:bg-red-500 text-white font-bold px-8 py-3 rounded-xl transition shadow-lg shadow-red-600/10 flex items-center gap-2 text-base">
                        <span class="material-symbols-outlined">save</span>
                        Tüm Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
