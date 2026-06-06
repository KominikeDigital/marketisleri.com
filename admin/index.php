<?php
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$today = date('Y-m-d');

// Fetch Stats
$total_cats = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$total_markets = $pdo->query("SELECT COUNT(*) FROM markets")->fetchColumn();
$total_brochures = $pdo->query("SELECT COUNT(*) FROM brochures")->fetchColumn();

// Active
$stmt = $pdo->prepare("SELECT COUNT(*) FROM brochures WHERE start_date <= ? AND end_date >= ?");
$stmt->execute([$today, $today]);
$active_brochures = $stmt->fetchColumn();

// Upcoming
$stmt = $pdo->prepare("SELECT COUNT(*) FROM brochures WHERE start_date > ?");
$stmt->execute([$today]);
$upcoming_brochures = $stmt->fetchColumn();

// Expired
$stmt = $pdo->prepare("SELECT COUNT(*) FROM brochures WHERE end_date < ?");
$stmt->execute([$today]);
$expired_brochures = $stmt->fetchColumn();

// Recent Brochures
$recent_stmt = $pdo->query("SELECT b.*, m.name as market_name FROM brochures b JOIN markets m ON b.market_id = m.id ORDER BY b.created_at DESC LIMIT 5");
$recent_brochures = $recent_stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli - marketisleri.com</title>
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
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
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
            <h1 class="font-title text-2xl font-bold text-white">Yönetim Paneline Hoş Geldiniz</h1>
            <div class="flex items-center gap-4">
                <a href="../" target="_blank" class="flex items-center gap-2 text-sm bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-xl transition">
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                    Siteyi Görüntüle
                </a>
            </div>
        </header>

        <!-- Container -->
        <div class="p-8 space-y-8 max-w-7xl w-full mx-auto">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Stat Card 1 -->
                <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl flex items-center justify-between shadow-xl">
                    <div>
                        <p class="text-sm text-slate-400 font-semibold mb-1">Toplam Market</p>
                        <h3 class="text-3xl font-black text-white"><?= $total_markets ?></h3>
                    </div>
                    <span class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center material-symbols-outlined text-2xl">
                        storefront
                    </span>
                </div>
                
                <!-- Stat Card 2 -->
                <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl flex items-center justify-between shadow-xl">
                    <div>
                        <p class="text-sm text-slate-400 font-semibold mb-1">Aktif Broşürler</p>
                        <h3 class="text-3xl font-black text-emerald-400"><?= $active_brochures ?></h3>
                    </div>
                    <span class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center material-symbols-outlined text-2xl">
                        check_circle
                    </span>
                </div>

                <!-- Stat Card 3 -->
                <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl flex items-center justify-between shadow-xl">
                    <div>
                        <p class="text-sm text-slate-400 font-semibold mb-1">Bekleyen Broşürler</p>
                        <h3 class="text-3xl font-black text-amber-400"><?= $upcoming_brochures ?></h3>
                    </div>
                    <span class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center material-symbols-outlined text-2xl">
                        schedule
                    </span>
                </div>

                <!-- Stat Card 4 -->
                <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl flex items-center justify-between shadow-xl">
                    <div>
                        <p class="text-sm text-slate-400 font-semibold mb-1">Süresi Dolanlar</p>
                        <h3 class="text-3xl font-black text-red-400"><?= $expired_brochures ?></h3>
                    </div>
                    <span class="w-12 h-12 rounded-2xl bg-red-500/10 text-red-400 flex items-center justify-center material-symbols-outlined text-2xl">
                        history
                    </span>
                </div>
            </div>

            <!-- Main Sections Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Recent Activities (List) -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl lg:col-span-2">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-title text-xl font-bold text-white">Son Yüklenen Broşürler</h3>
                        <a href="brochures.php" class="text-sm text-red-500 hover:text-red-400 font-bold">Tümünü Gör →</a>
                    </div>
                    
                    <?php if (empty($recent_brochures)): ?>
                        <div class="py-12 text-center text-slate-500">
                            <span class="material-symbols-outlined text-4xl mb-2 block">menu_book</span>
                            Henüz broşür yüklenmemiş.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-800 text-slate-400 text-xs font-semibold uppercase tracking-wider">
                                        <th class="pb-3">Kapak</th>
                                        <th class="pb-3">Başlık</th>
                                        <th class="pb-3">Market</th>
                                        <th class="pb-3">Tarih Aralığı</th>
                                        <th class="pb-3">Durum</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800 text-sm">
                                    <?php foreach ($recent_brochures as $b): ?>
                                        <tr>
                                            <td class="py-4">
                                                <img src="../uploads/brochures/<?= htmlspecialchars($b['cover_image']) ?>" 
                                                     class="w-10 h-14 object-cover rounded-lg border border-slate-800" 
                                                     alt="Cover" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'100\'><rect width=\'80\' height=\'100\' fill=\'%231e293b\'/><text x=\'50%%27 y=\'50%%27 dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'sans-serif\' font-size=\'10\' fill=\'%2364748b\'>RESİM YOK</text></svg>'">
                                            </td>
                                            <td class="py-4 font-bold text-white"><?= htmlspecialchars($b['title']) ?></td>
                                            <td class="py-4 text-slate-300"><?= htmlspecialchars($b['market_name']) ?></td>
                                            <td class="py-4 text-slate-400">
                                                <?= htmlspecialchars($b['start_date']) ?> / <?= htmlspecialchars($b['end_date']) ?>
                                            </td>
                                            <td class="py-4">
                                                <?php
                                                if ($b['end_date'] < $today) {
                                                    echo '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-red-500/10 text-red-400 border border-red-500/20">Süresi Doldu</span>';
                                                } elseif ($b['start_date'] > $today) {
                                                    echo '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">Beklemede</span>';
                                                } else {
                                                    echo '<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Aktif</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions / Seed Info -->
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
                    <div>
                        <h3 class="font-title text-xl font-bold text-white mb-4">Hızlı İşlemler</h3>
                        <p class="text-sm text-slate-400 mb-6">
                            Admin panelinden marketler ekleyebilir ve bunlara ait broşürleri görseller (JPG/PNG) veya doğrudan tek bir PDF dosyası olarak yükleyebilirsiniz.
                        </p>
                        <div class="space-y-3">
                            <a href="markets.php" class="flex items-center justify-between p-4 rounded-2xl bg-slate-950 border border-slate-800 hover:border-slate-700 transition">
                                <span class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-indigo-400">storefront</span>
                                    <span class="font-semibold text-sm">Market Ekle/Yönet</span>
                                </span>
                                <span class="material-symbols-outlined text-sm text-slate-500">chevron_right</span>
                            </a>
                            <a href="brochures.php" class="flex items-center justify-between p-4 rounded-2xl bg-slate-950 border border-slate-800 hover:border-slate-700 transition">
                                <span class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-emerald-400">add_to_photos</span>
                                    <span class="font-semibold text-sm">Broşür Ekle/Yönet</span>
                                </span>
                                <span class="material-symbols-outlined text-sm text-slate-500">chevron_right</span>
                            </a>
                        </div>
                    </div>
                    
                    <div class="pt-6 border-t border-slate-800 text-xs text-slate-500 flex items-center justify-between">
                        <span>Veritabanı Modu:</span>
                        <span class="px-2 py-0.5 rounded bg-slate-800 font-mono text-slate-300">
                            <?= $is_local ? 'SQLite (Local)' : 'MySQL (Live)' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>