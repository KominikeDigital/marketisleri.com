<?php
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Handle Add/Edit Market
if (isset($_POST['save'])) {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
    $sort_order = isset($_POST['sort_order']) && $_POST['sort_order'] !== '' ? intval($_POST['sort_order']) : 0;
    
    // Scraper settings
    $scraper_url = trim($_POST['scraper_url'] ?? '');
    $scraper_container = trim($_POST['scraper_container'] ?? '');
    $scraper_title = trim($_POST['scraper_title'] ?? '');
    $scraper_cover = trim($_POST['scraper_cover'] ?? '');
    $scraper_detail_link = trim($_POST['scraper_detail_link'] ?? '');
    $scraper_page_image = trim($_POST['scraper_page_image'] ?? '');
    $scraper_active = isset($_POST['scraper_active']) ? 1 : 0;
    
    // Auto-generate slug if empty
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    }

    if (empty($name) || $category_id === 0) {
        $error = "Lütfen gerekli alanları (Ad, Kategori) doldurun.";
    } else {
        // Logo Upload Handling
        $logo_name = $_POST['existing_logo'] ?? '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['logo']['tmp_name'];
            $file_name = $_FILES['logo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
            if (in_array($file_ext, $allowed_exts)) {
                // Delete old logo if replacing
                if (!empty($logo_name) && file_exists('../uploads/markets/' . $logo_name)) {
                    @unlink('../uploads/markets/' . $logo_name);
                }
                
                // Set clean unique logo name
                $logo_name = $slug . '-' . time() . '.' . $file_ext;
                $dest_path = '../uploads/markets/' . $logo_name;
                
                if (!move_uploaded_file($file_tmp, $dest_path)) {
                    $error = "Logo yüklenirken bir hata oluştu.";
                } else {
                    compress_and_resize_image($dest_path, 200, 75);
                }
            } else {
                $error = "Geçersiz logo formatı. Sadece PNG, JPG, JPEG, SVG ve WEBP kabul edilir.";
            }
        }

        if (!$error) {
            if ($id === null) {
                // Check if slug is unique
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM markets WHERE slug = ?");
                $check_stmt->execute([$slug]);
                if ($check_stmt->fetchColumn() > 0) {
                    $slug = $slug . '-' . rand(100, 999);
                }
                
                // Add new market
                try {
                    $stmt = $pdo->prepare("INSERT INTO markets (name, slug, logo, description, category_id, scraper_url, scraper_container, scraper_title, scraper_cover, scraper_detail_link, scraper_page_image, scraper_active, is_popular, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $slug, $logo_name, $description, $category_id, $scraper_url, $scraper_container, $scraper_title, $scraper_cover, $scraper_detail_link, $scraper_page_image, $scraper_active, $is_popular, $sort_order]);
                    $success = "Market başarıyla eklendi.";
                } catch (PDOException $e) {
                    $error = "Kaydetme hatası: " . $e->getMessage();
                }
            } else {
                // Check if slug is unique for other markets
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM markets WHERE slug = ? AND id != ?");
                $check_stmt->execute([$slug, $id]);
                if ($check_stmt->fetchColumn() > 0) {
                    $slug = $slug . '-' . rand(100, 999);
                }
                
                // Edit existing market
                try {
                    $stmt = $pdo->prepare("UPDATE markets SET name = ?, slug = ?, logo = ?, description = ?, category_id = ?, scraper_url = ?, scraper_container = ?, scraper_title = ?, scraper_cover = ?, scraper_detail_link = ?, scraper_page_image = ?, scraper_active = ?, is_popular = ?, sort_order = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $logo_name, $description, $category_id, $scraper_url, $scraper_container, $scraper_title, $scraper_cover, $scraper_detail_link, $scraper_page_image, $scraper_active, $is_popular, $sort_order, $id]);
                    $success = "Market başarıyla güncellendi.";
                } catch (PDOException $e) {
                    $error = "Güncelleme hatası: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Categories for selection
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch Markets list with category names
$markets_stmt = $pdo->query("SELECT m.*, c.name as category_name FROM markets m LEFT JOIN categories c ON m.category_id = c.id ORDER BY m.sort_order ASC, m.name ASC");
$markets = $markets_stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Yönetimi - marketisleri.com</title>
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
            <a href="markets.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
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
            <a href="hepsiburada_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">local_mall</span> Hepsiburada Broşür Ekle
            </a>
            <a href="cron_setup.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">schedule</span>
                Otomasyon &amp; Cron
            </a>
            <a href="apply_scrapers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">build</span>
                Scraper Ayarları
            </a>
            <a href="blogs.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">article</span>
                Blog Yazıları
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
            <h1 class="font-title text-2xl font-bold text-white font-bold">Market Yönetimi</h1>
            <div class="flex items-center gap-4">
                <a href="merge_duplicate_markets.php" class="flex items-center gap-2 bg-amber-600 hover:bg-amber-500 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-lg shadow-amber-600/10">
                    <span class="material-symbols-outlined text-lg">call_merge</span>
                    Çiftleri Birleştir
                </a>
                <a href="akakce_import.php" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-lg shadow-indigo-600/10">
                    <span class="material-symbols-outlined text-lg">cloud_download</span>
                    Akakçe İçe Aktar
                </a>
                <button onclick="openModal()" class="flex items-center gap-2 bg-red-600 hover:bg-red-500 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-lg shadow-red-600/10">
                    <span class="material-symbols-outlined text-lg">add_circle</span>
                    Yeni Market Ekle
                </button>
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

            <!-- Markets Grid/Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                <div class="p-6 border-b border-slate-800">
                    <h3 class="font-title text-xl font-bold text-white">Marketler Listesi</h3>
                </div>

                <?php if (empty($markets)): ?>
                    <div class="py-20 text-center text-slate-500">
                        <span class="material-symbols-outlined text-5xl mb-3 block text-slate-600">storefront</span>
                        Henüz market eklenmemiş. Sağ üst köşeden ilk marketinizi ekleyin!
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-800 text-slate-400 text-xs font-semibold uppercase tracking-wider bg-slate-950/40">
                                    <th class="p-4 pl-6">Logo</th>
                                    <th class="p-4">Market Adı</th>
                                    <th class="p-4">Slug</th>
                                    <th class="p-4">Kategori</th>
                                    <th class="p-4">Popüler</th>
                                    <th class="p-4">Sıra</th>
                                    <th class="p-4">Açıklama</th>
                                    <th class="p-4 pr-6 text-right">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 text-sm">
                                <?php foreach ($markets as $m): ?>
                                    <?php $logo_exists = !empty($m['logo']) && is_file(dirname(__DIR__) . '/uploads/markets/' . $m['logo']); ?>
                                    <tr class="hover:bg-slate-800/20 transition-all">
                                        <td class="p-4 pl-6">
                                            <?php if ($logo_exists): ?>
                                                <img src="../uploads/markets/<?= htmlspecialchars($m['logo']) ?>" 
                                                     class="w-12 h-12 object-contain bg-white rounded-xl p-1 border border-slate-800 shadow" 
                                                     alt="Logo">
                                            <?php else: ?>
                                                <div class="w-12 h-12 rounded-xl bg-slate-800 flex items-center justify-center text-slate-500 border border-slate-700">
                                                    <span class="material-symbols-outlined">image</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 font-bold text-white text-base"><?= htmlspecialchars($m['name']) ?></td>
                                        <td class="p-4 text-slate-400 font-mono"><?= htmlspecialchars($m['slug']) ?></td>
                                        <td class="p-4">
                                            <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                                                <?= htmlspecialchars($m['category_name'] ?? 'Kategorisiz') ?>
                                            </span>
                                        </td>
                                        <td class="p-4">
                                            <?php if ($m['is_popular'] == 1): ?>
                                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-red-500/10 text-red-400 border border-red-500/20 flex items-center gap-1 w-max">
                                                    <span class="material-symbols-outlined text-xs font-black">grade</span> Evet
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-800 text-slate-400 border border-slate-700 w-max">Hayır</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 font-semibold text-white">
                                            <?= htmlspecialchars($m['sort_order'] ?? 0) ?>
                                        </td>
                                        <td class="p-4 text-slate-400 max-w-xs truncate" title="<?= htmlspecialchars($m['description'] ?? '') ?>">
                                            <?= htmlspecialchars($m['description'] ?? '-') ?>
                                        </td>
                                        <td class="p-4 pr-6 text-right space-x-2">
                                            <button onclick="editMarket(<?= htmlspecialchars(json_encode($m)) ?>)" 
                                                    class="inline-flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                <span class="material-symbols-outlined text-xs">edit</span>
                                                Düzenle
                                            </button>
                                            <a href="delete.php?type=market&id=<?= $m['id'] ?>" 
                                               onclick="return confirm('Bu marketi sildiğinizde, bu markete ait tüm broşürler de silinecektir. Emin misiniz?')"
                                               class="inline-flex items-center gap-1 bg-red-950/40 hover:bg-red-900/60 text-red-400 px-3 py-1.5 rounded-lg text-xs font-bold border border-red-900/30 transition">
                                                <span class="material-symbols-outlined text-xs">delete</span>
                                                Sil
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal Form -->
    <div id="modal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm overflow-y-auto flex items-start justify-center p-4 md:p-10">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg shadow-2xl my-auto animate-in fade-in zoom-in duration-250">
            <div class="p-6 border-b border-slate-800 flex justify-between items-center bg-slate-950/40">
                <h3 id="modal-title" class="font-title text-xl font-bold text-white">Yeni Market Ekle</h3>
                <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                <input type="hidden" id="form-id" name="id">
                <input type="hidden" id="form-existing-logo" name="existing_logo">
                
                <div>
                    <label for="form-name" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Market Adı *</label>
                    <input type="text" id="form-name" name="name" required
                           class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                           placeholder="BİM, A101, Şok vb.">
                </div>

                <div>
                    <label for="form-slug" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Slug (URL Adı)</label>
                    <input type="text" id="form-slug" name="slug"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                           placeholder="bim (Boş bırakılırsa otomatik üretilir)">
                </div>

                <div>
                    <label for="form-category" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kategori *</label>
                    <select id="form-category" name="category_id" required
                            class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition">
                        <option value="">Kategori Seçin</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Market Logosu</label>
                    <div class="flex items-center gap-4">
                        <div id="logo-preview-container" class="w-16 h-16 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-center overflow-hidden shrink-0">
                            <span id="logo-preview-placeholder" class="material-symbols-outlined text-slate-600">image</span>
                            <img id="logo-preview-img" class="w-full h-full object-contain hidden bg-white p-1">
                        </div>
                        <div class="flex-1">
                            <input type="file" id="form-logo" name="logo" accept="image/*" class="hidden" onchange="previewLogo(this)">
                            <button type="button" onclick="document.getElementById('form-logo').click()" 
                                    class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2.5 rounded-xl text-sm font-semibold transition">
                                Dosya Seç
                            </button>
                            <p class="text-xs text-slate-500 mt-2">Önerilen: 300x300 kare PNG / WEBP</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="form-description" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Açıklama</label>
                    <textarea id="form-description" name="description" rows="2"
                              class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                              placeholder="Market hakkında kısa tanıtım metni..."></textarea>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="form-is-popular" name="is_popular" value="1"
                           class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-red-600 focus:ring-red-500 focus:ring-offset-slate-900">
                    <label for="form-is-popular" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Popüler Market (Anasayfa Popüler Listesinde Göster)</label>
                </div>

                <div id="sort-order-container" class="hidden">
                    <label for="form-sort-order" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Sıralama Önceliği (Popüler Marketler)</label>
                    <input type="number" id="form-sort-order" name="sort_order" min="0" value="0"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                           placeholder="Örn: 1 (Düşük sayılar önceliklidir)">
                </div>

                <div class="border-t border-slate-800 pt-4 space-y-4">
                    <h4 class="font-title text-sm font-bold text-slate-300">Otomatik Kazıma (Scraper) Ayarları</h4>
                    
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="form-scraper-active" name="scraper_active" value="1"
                               class="w-4 h-4 rounded bg-slate-950 border border-slate-800 text-red-600 focus:ring-red-500 focus:ring-offset-slate-900">
                        <label for="form-scraper-active" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Otomatik Kazıma Aktif</label>
                    </div>

                    <div>
                        <label for="form-scraper-url" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Hedef Kazıma URL'si</label>
                        <input type="url" id="form-scraper-url" name="scraper_url"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                               placeholder="https://example.com/aktuel-urunler">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="form-scraper-container" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kart (Container) Seçici</label>
                            <input type="text" id="form-scraper-container" name="scraper_container"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                                   placeholder=".brochure-card">
                        </div>
                        <div>
                            <label for="form-scraper-title" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Başlık Seçici</label>
                            <input type="text" id="form-scraper-title" name="scraper_title"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                                   placeholder="h3.title">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="form-scraper-cover" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kapak Resmi Seçici</label>
                            <input type="text" id="form-scraper-cover" name="scraper_cover"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                                   placeholder="img.cover">
                        </div>
                        <div>
                            <label for="form-scraper-detail-link" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Detay Link Seçici</label>
                            <input type="text" id="form-scraper-detail-link" name="scraper_detail_link"
                                   class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                                   placeholder="a.detail-btn">
                        </div>
                    </div>

                    <div>
                        <label for="form-scraper-page-image" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Detay Sayfası Sayfa Resimleri Seçici</label>
                        <input type="text" id="form-scraper-page-image" name="scraper_page_image"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                               placeholder=".brochure-pages img">
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="closeModal()" 
                            class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold px-5 py-2.5 rounded-xl transition">
                        İptal
                    </button>
                    <button type="submit" name="save" 
                            class="bg-red-600 hover:bg-red-500 text-white font-bold px-6 py-2.5 rounded-xl transition shadow-lg shadow-red-600/10">
                        Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS Helper for Modals -->
    <script>
        const modal = document.getElementById('modal');
        const modalTitle = document.getElementById('modal-title');
        const formId = document.getElementById('form-id');
        const formName = document.getElementById('form-name');
        const formSlug = document.getElementById('form-slug');
        const formCategory = document.getElementById('form-category');
        const formDescription = document.getElementById('form-description');
        const formExistingLogo = document.getElementById('form-existing-logo');
        
        // Scraper settings elements
        const formScraperActive = document.getElementById('form-scraper-active');
        const formScraperUrl = document.getElementById('form-scraper-url');
        const formScraperContainer = document.getElementById('form-scraper-container');
        const formScraperTitle = document.getElementById('form-scraper-title');
        const formScraperCover = document.getElementById('form-scraper-cover');
        const formScraperDetailLink = document.getElementById('form-scraper-detail-link');
        const formScraperPageImage = document.getElementById('form-scraper-page-image');
        
        const logoPreviewPlaceholder = document.getElementById('logo-preview-placeholder');
        const logoPreviewImg = document.getElementById('logo-preview-img');

        function openModal() {
            modalTitle.innerText = "Yeni Market Ekle";
            formId.value = "";
            formName.value = "";
            formSlug.value = "";
            formCategory.value = "";
            formDescription.value = "";
            formExistingLogo.value = "";
            
            // Clear scraper settings
            formScraperActive.checked = false;
            formScraperUrl.value = "";
            formScraperContainer.value = "";
            formScraperTitle.value = "";
            formScraperCover.value = "";
            formScraperDetailLink.value = "";
            formScraperPageImage.value = "";
            
            document.getElementById('form-is-popular').checked = false;
            document.getElementById('form-sort-order').value = 0;
            document.getElementById('sort-order-container').classList.add('hidden');
            
            logoPreviewImg.src = "";
            logoPreviewImg.classList.add('hidden');
            logoPreviewPlaceholder.classList.remove('hidden');
            
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        function editMarket(market) {
            modalTitle.innerText = "Marketi Düzenle";
            formId.value = market.id;
            formName.value = market.name;
            formSlug.value = market.slug;
            formCategory.value = market.category_id;
            formDescription.value = market.description || "";
            formExistingLogo.value = market.logo || "";
            
            // Set scraper settings
            formScraperActive.checked = market.scraper_active == 1;
            formScraperUrl.value = market.scraper_url || "";
            formScraperContainer.value = market.scraper_container || "";
            formScraperTitle.value = market.scraper_title || "";
            formScraperCover.value = market.scraper_cover || "";
            formScraperDetailLink.value = market.scraper_detail_link || "";
            formScraperPageImage.value = market.scraper_page_image || "";
            
            document.getElementById('form-is-popular').checked = market.is_popular == 1;
            document.getElementById('form-sort-order').value = market.sort_order || 0;
            if (market.is_popular == 1) {
                document.getElementById('sort-order-container').classList.remove('hidden');
            } else {
                document.getElementById('sort-order-container').classList.add('hidden');
            }
            
            if (market.logo) {
                logoPreviewImg.src = "../uploads/markets/" + market.logo;
                logoPreviewImg.classList.remove('hidden');
                logoPreviewPlaceholder.classList.add('hidden');
            } else {
                logoPreviewImg.src = "";
                logoPreviewImg.classList.add('hidden');
                logoPreviewPlaceholder.classList.remove('hidden');
            }
            
            modal.classList.remove('hidden');
        }

        // Toggle sort order input based on popular status checkbox
        document.getElementById('form-is-popular').addEventListener('change', function() {
            const container = document.getElementById('sort-order-container');
            if (this.checked) {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        });

        function previewLogo(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    logoPreviewImg.src = e.target.result;
                    logoPreviewImg.classList.remove('hidden');
                    logoPreviewPlaceholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
