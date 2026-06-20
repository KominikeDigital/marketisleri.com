<?php
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

// Handle Delete Subscriber
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    if ($delete_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM subscribers WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "Abone başarıyla silindi.";
        } catch (PDOException $e) {
            $error = "Silme hatası: " . $e->getMessage();
        }
    }
}

// Handle Send Email Campaign
if (isset($_POST['send_campaign'])) {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    
    if (empty($subject) || empty($body)) {
        $error = "Lütfen e-posta konusu ve içeriğini doldurun.";
    } else {
        // Fetch all subscribers
        try {
            $subs_stmt = $pdo->query("SELECT email FROM subscribers");
            $subscribers = $subs_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($subscribers)) {
                $error = "Gönderilecek kayıtlı abone bulunmuyor.";
            } else {
                $success_count = 0;
                $fail_count = 0;
                
                // Wrap content in a beautiful email template wrapper
                $email_content = "
                <html>
                <head>
                    <style>
                        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 20px; }
                        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
                        .header { background: #dc2626; padding: 30px; text-align: center; }
                        .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: bold; }
                        .content { padding: 40px 30px; line-height: 1.6; font-size: 15px; }
                        .footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #64748b; border-t: 1px solid #e2e8f0; }
                        .footer a { color: #dc2626; text-decoration: none; font-weight: bold; }
                        .btn { display: inline-block; background-color: #dc2626; color: #ffffff !important; font-weight: bold; text-decoration: none; padding: 12px 24px; border-radius: 8px; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>marketisleri.com</h1>
                        </div>
                        <div class='content'>
                            " . nl2br($body) . "
                        </div>
                        <div class='footer'>
                            Bu e-posta, marketisleri.com bültenine kayıt olduğunuz için gönderilmiştir.<br>
                            Bültenden ayrılmak için lütfen yönetici ile iletişime geçin.<br>
                            &copy; 2026 <a href='https://marketisleri.com'>marketisleri.com</a>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                foreach ($subscribers as $email) {
                    if (send_email_notification($email, $subject, $email_content, $pdo)) {
                        $success_count++;
                    } else {
                        $fail_count++;
                    }
                }
                
                $success = "Kampanya gönderimi tamamlandı. (Başarılı: $success_count, Başarısız: $fail_count)";
            }
        } catch (Exception $e) {
            $error = "Gönderim sırasında sistem hatası oluştu: " . $e->getMessage();
        }
    }
}

// Fetch Subscribers list
$subscribers_stmt = $pdo->query("SELECT * FROM subscribers ORDER BY created_at DESC");
$subscribers_list = $subscribers_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abone Yönetimi & Kampanya - marketisleri.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../uploads/tailwind.min.css">
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
            <a href="magic_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">auto_fix</span>
                Sihirli Broşür Ekle
            </a>
            <a href="amazon_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">shopping_basket</span> Amazon Broşür Ekle
            </a>
            <a href="cron_setup.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">schedule</span>
                Otomasyon &amp; Cron
            </a>
            <a href="apply_scrapers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">build</span>
                Scraper Ayarları
            </a>
            <a href="analyze_brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">explore</span>
                Broşür AI Analizi
            </a>
            <a href="blogs.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">article</span>
                Blog Yazıları
            </a>
            <a href="subscribers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">mail</span>
                Aboneler
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
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
            <h1 class="font-title text-2xl font-bold text-white font-bold">Abone & Bülten Yönetimi</h1>
            <div class="flex items-center gap-4">
                <a href="../" target="_blank" class="flex items-center gap-2 text-sm bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-xl transition">
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                    Siteyi Görüntüle
                </a>
            </div>
        </header>

        <!-- Container -->
        <div class="p-8 space-y-8 max-w-7xl w-full mx-auto">
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <!-- Left: Send Campaign Form (lg:col-span-2) -->
                <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                    <div class="flex items-center gap-3 pb-4 border-b border-slate-800">
                        <span class="material-symbols-outlined text-red-500 font-bold">send</span>
                        <h3 class="font-title text-lg font-bold text-white">Toplu E-Bülten Gönderimi</h3>
                    </div>

                    <form method="POST" class="space-y-5">
                        <div>
                            <label for="subject" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">E-Posta Konusu *</label>
                            <input type="text" id="subject" name="subject" required
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                                   placeholder="Örn: BİM 12 Haziran Kataloğu Yayınlandı!">
                        </div>

                        <div>
                            <label for="body" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">E-Posta İçeriği (HTML Kullanılabilir) *</label>
                            <textarea id="body" name="body" rows="8" required
                                      class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-slate-200 rounded-xl px-4 py-2.5 outline-none transition font-sans text-sm"
                                      placeholder="Abonelerinize göndermek istediğiniz kampanya metnini buraya yazın..."></textarea>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-slate-800">
                            <button type="submit" name="send_campaign"
                                    onclick="return confirm('Bu bülteni tüm kayıtlı abonelere göndermek istediğinizden emin misiniz?')"
                                    class="bg-red-600 hover:bg-red-500 text-white font-bold px-6 py-2.5 rounded-xl transition shadow-lg shadow-red-600/10 flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm font-black">campaign</span>
                                Kampanyayı Gönder
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right: Subscribers List -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-red-500">group</span>
                            <h3 class="font-title text-base font-bold text-white">Kayıtlı Aboneler</h3>
                        </div>
                        <span class="px-2.5 py-0.5 rounded bg-slate-850 border border-slate-800 text-slate-300 text-xs font-bold font-mono">
                            <?= count($subscribers_list) ?>
                        </span>
                    </div>

                    <?php if (empty($subscribers_list)): ?>
                        <div class="py-12 text-center text-slate-500 text-sm">
                            <span class="material-symbols-outlined text-4xl mb-2 block">mail</span>
                            Kayıtlı abone bulunmuyor.
                        </div>
                    <?php else: ?>
                        <div class="max-h-[500px] overflow-y-auto pr-1 space-y-3">
                            <?php foreach ($subscribers_list as $sub): ?>
                                <div class="flex items-center justify-between p-3 bg-slate-950/60 border border-slate-850 rounded-2xl">
                                    <div class="truncate mr-3">
                                        <p class="font-semibold text-xs text-white truncate" title="<?= htmlspecialchars($sub['email']) ?>">
                                            <?= htmlspecialchars($sub['email']) ?>
                                        </p>
                                        <p class="text-[10px] text-slate-500 mt-0.5">
                                            <?= date('d.m.Y H:i', strtotime($sub['created_at'])) ?>
                                        </p>
                                    </div>
                                    <a href="subscribers.php?delete=<?= $sub['id'] ?>"
                                       onclick="return confirm('Aboneyi bültenden silmek istediğinizden emin misiniz?')"
                                       class="w-8 h-8 rounded-lg bg-slate-900 hover:bg-red-950/40 text-slate-500 hover:text-red-400 border border-slate-800 hover:border-red-900/30 flex items-center justify-center transition shrink-0"
                                       title="Sil">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
