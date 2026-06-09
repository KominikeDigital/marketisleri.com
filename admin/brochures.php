<?php
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

// Handle Add/Edit Brochure
if (isset($_POST['save'])) {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
    $market_id = intval($_POST['market_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $content_type = $_POST['content_type'] ?? 'images'; // images or pdf

    if ($market_id === 0 || empty($title) || empty($start_date) || empty($end_date)) {
        $error = "Lütfen tüm gerekli alanları doldurun.";
    } else {
        $cover_name = $_POST['existing_cover'] ?? '';
        
        // 1. Cover Image Upload
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['cover_image']['tmp_name'];
            $file_name = $_FILES['cover_image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['png', 'jpg', 'jpeg', 'webp'];
            if (in_array($file_ext, $allowed_exts)) {
                // Delete old cover if replacing
                if (!empty($cover_name) && file_exists('../uploads/brochures/' . $cover_name)) {
                    @unlink('../uploads/brochures/' . $cover_name);
                }
                
                $cover_name = 'cover-' . time() . '-' . rand(100, 999) . '.' . $file_ext;
                move_uploaded_file($file_tmp, '../uploads/brochures/' . $cover_name);
            } else {
                $error = "Kapak görseli formatı geçersiz. Sadece PNG, JPG, JPEG, WEBP yüklenebilir.";
            }
        } elseif ($id === null) {
            $error = "Yeni broşür için kapak görseli yüklenmelidir.";
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                if ($id === null) {
                    // Insert Brochure
                    $stmt = $pdo->prepare("INSERT INTO brochures (market_id, title, cover_image, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$market_id, $title, $cover_name, $start_date, $end_date]);
                    $brochure_id = $pdo->lastInsertId();
                } else {
                    $brochure_id = $id;
                    // Update Brochure metadata
                    $stmt = $pdo->prepare("UPDATE brochures SET market_id = ?, title = ?, cover_image = ?, start_date = ?, end_date = ? WHERE id = ?");
                    $stmt->execute([$market_id, $title, $cover_name, $start_date, $end_date, $brochure_id]);
                }

                // Handle content uploads (PDF vs Images)
                if ($content_type === 'pdf') {
                    // Upload PDF
                    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['pdf_file']['tmp_name'];
                        $file_name = $_FILES['pdf_file']['name'];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                        if ($file_ext === 'pdf') {
                            // If editing and had old pdf, delete it
                            if ($id !== null) {
                                $old_pdf = $pdo->query("SELECT pdf_path FROM brochures WHERE id = $brochure_id")->fetchColumn();
                                if (!empty($old_pdf) && file_exists('../uploads/brochures/pdfs/' . $old_pdf)) {
                                    @unlink('../uploads/brochures/pdfs/' . $old_pdf);
                                }
                                
                                // Delete existing image pages if converting from images to PDF
                                $old_pages = $pdo->prepare("SELECT image_path FROM brochure_pages WHERE brochure_id = ?");
                                $old_pages->execute([$brochure_id]);
                                while($op = $old_pages->fetchColumn()) {
                                    if (file_exists('../uploads/brochures/pages/' . $op)) {
                                        @unlink('../uploads/brochures/pages/' . $op);
                                    }
                                }
                                $pdo->prepare("DELETE FROM brochure_pages WHERE brochure_id = ?")->execute([$brochure_id]);
                            }

                            $pdf_name = 'brochure-' . $brochure_id . '-' . time() . '.pdf';
                            move_uploaded_file($file_tmp, '../uploads/brochures/pdfs/' . $pdf_name);
                            
                            $update_stmt = $pdo->prepare("UPDATE brochures SET pdf_path = ? WHERE id = ?");
                            $update_stmt->execute([$pdf_name, $brochure_id]);
                        } else {
                            throw new Exception("Yüklenen dosya PDF formatında olmalıdır.");
                        }
                    } elseif ($id === null) {
                        throw new Exception("Lütfen broşürün PDF dosyasını yükleyin.");
                    }
                } else {
                    // Upload multiple images
                    if (isset($_FILES['image_pages']) && !empty($_FILES['image_pages']['name'][0])) {
                        // If editing, clean up old pages first
                        if ($id !== null) {
                            // Delete old PDF file if converting from PDF to images
                            $old_pdf = $pdo->query("SELECT pdf_path FROM brochures WHERE id = $brochure_id")->fetchColumn();
                            if (!empty($old_pdf) && file_exists('../uploads/brochures/pdfs/' . $old_pdf)) {
                                @unlink('../uploads/brochures/pdfs/' . $old_pdf);
                            }
                            $pdo->prepare("UPDATE brochures SET pdf_path = NULL WHERE id = ?")->execute([$brochure_id]);

                            // Delete existing image pages
                            $old_pages = $pdo->prepare("SELECT image_path FROM brochure_pages WHERE brochure_id = ?");
                            $old_pages->execute([$brochure_id]);
                            while($op = $old_pages->fetchColumn()) {
                                if (file_exists('../uploads/brochures/pages/' . $op)) {
                                    @unlink('../uploads/brochures/pages/' . $op);
                                }
                            }
                            $pdo->prepare("DELETE FROM brochure_pages WHERE brochure_id = ?")->execute([$brochure_id]);
                        }

                        $files = $_FILES['image_pages'];
                        $count = count($files['name']);
                        
                        // Sort files by name to maintain order if structured (like page1, page2, etc)
                        $file_array = [];
                        for ($i = 0; $i < $count; $i++) {
                            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                $file_array[] = [
                                    'name' => $files['name'][$i],
                                    'tmp_name' => $files['tmp_name'][$i]
                                ];
                            }
                        }
                        
                        // Sort by filename naturally
                        usort($file_array, function($a, $b) {
                            return strnatcmp($a['name'], $b['name']);
                        });

                        $page_num = 1;
                        $insert_page_stmt = $pdo->prepare("INSERT INTO brochure_pages (brochure_id, page_number, image_path) VALUES (?, ?, ?)");
                        
                        foreach ($file_array as $file) {
                            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (in_array($file_ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                                $page_img_name = 'page-' . $brochure_id . '-' . $page_num . '-' . time() . '.' . $file_ext;
                                move_uploaded_file($file['tmp_name'], '../uploads/brochures/pages/' . $page_img_name);
                                
                                $insert_page_stmt->execute([$brochure_id, $page_num, $page_img_name]);
                                $page_num++;
                            }
                        }
                    } elseif ($id === null) {
                        throw new Exception("Lütfen broşür görsellerini yükleyin.");
                    }
                }

                $pdo->commit();
                $success = "Broşür başarıyla kaydedildi.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "İşlem sırasında hata oluştu: " . $e->getMessage();
            }
        }
    }
}

$today = date('Y-m-d');

// Fetch Markets for selection
$markets = $pdo->query("SELECT * FROM markets ORDER BY name ASC")->fetchAll();

// Fetch Brochures
$brochures_stmt = $pdo->query("SELECT b.*, m.name as market_name FROM brochures b JOIN markets m ON b.market_id = m.id ORDER BY b.created_at DESC");
$brochures = $brochures_stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broşür Yönetimi - marketisleri.com</title>
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
            <a href="brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">menu_book</span>
                Broşürler
            </a>
            <a href="magic_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">auto_fix</span>
                Sihirli Broşür Ekle
            </a>
            <a href="cron_setup.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">schedule</span>
                Otomasyon &amp; Cron
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
            <h1 class="font-title text-2xl font-bold text-white font-bold">Broşür Yönetimi</h1>
            <div class="flex items-center gap-4">
                <button onclick="openModal()" class="flex items-center gap-2 bg-red-600 hover:bg-red-500 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-lg shadow-red-600/10">
                    <span class="material-symbols-outlined text-lg">add_to_photos</span>
                    Yeni Broşür Ekle
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

            <!-- Brochures Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                <div class="p-6 border-b border-slate-800">
                    <h3 class="font-title text-xl font-bold text-white">Broşürler Listesi</h3>
                </div>

                <?php if (empty($brochures)): ?>
                    <div class="py-20 text-center text-slate-500">
                        <span class="material-symbols-outlined text-5xl mb-3 block text-slate-600">menu_book</span>
                        Henüz broşür yüklenmemiş. Sağ üst köşeden ilk broşürünüzü yükleyin!
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-800 text-slate-400 text-xs font-semibold uppercase tracking-wider bg-slate-950/40">
                                    <th class="p-4 pl-6">Kapak</th>
                                    <th class="p-4">Başlık</th>
                                    <th class="p-4">Market</th>
                                    <th class="p-4">Tarih Aralığı</th>
                                    <th class="p-4">Tip</th>
                                    <th class="p-4">Durum</th>
                                    <th class="p-4 pr-6 text-right">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 text-sm">
                                <?php foreach ($brochures as $b): ?>
                                    <tr class="hover:bg-slate-800/20 transition-all">
                                        <td class="p-4 pl-6">
                                            <img src="../uploads/brochures/<?= htmlspecialchars($b['cover_image']) ?>" 
                                                 class="w-12 h-16 object-cover rounded-lg border border-slate-800 shadow-md bg-slate-950" 
                                                 alt="Cover" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'100\'><rect width=\'80\' height=\'100\' fill=\'%231e293b\'/><text x=\'50%%27 y=\'50%%27 dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'sans-serif\' font-size=\'10\' fill=\'%2364748b\'>RESİM YOK</text></svg>'">
                                        </td>
                                        <td class="p-4 font-bold text-white text-base"><?= htmlspecialchars($b['title']) ?></td>
                                        <td class="p-4 text-slate-300"><?= htmlspecialchars($b['market_name']) ?></td>
                                        <td class="p-4 text-slate-400">
                                            <div class="flex flex-col">
                                                <span>Başlangıç: <?= htmlspecialchars($b['start_date']) ?></span>
                                                <span>Bitiş: <?= htmlspecialchars($b['end_date']) ?></span>
                                            </div>
                                        </td>
                                        <td class="p-4">
                                            <?php if (!empty($b['pdf_path'])): ?>
                                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-red-500/10 text-red-400 border border-red-500/20">PDF</span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">Görsel</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4">
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
                                        <td class="p-4 pr-6 text-right space-x-2">
                                            <a href="../viewer.php?id=<?= $b['id'] ?>" target="_blank" 
                                               class="inline-flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                <span class="material-symbols-outlined text-xs">visibility</span>
                                                Gör
                                            </a>
                                            <button onclick="editBrochure(<?= htmlspecialchars(json_encode($b)) ?>)" 
                                                    class="inline-flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                <span class="material-symbols-outlined text-xs">edit</span>
                                                Düzenle
                                            </button>
                                            <a href="delete.php?type=brochure&id=<?= $b['id'] ?>" 
                                               onclick="return confirm('Bu broşürü ve tüm sayfalarını silmek istediğinizden emin misiniz?')"
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
    <div id="modal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-250">
            <div class="p-6 border-b border-slate-800 flex justify-between items-center bg-slate-950/40">
                <h3 id="modal-title" class="font-title text-xl font-bold text-white">Yeni Broşür Ekle</h3>
                <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                <input type="hidden" id="form-id" name="id">
                <input type="hidden" id="form-existing-cover" name="existing_cover">
                
                <div>
                    <label for="form-market" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Market *</label>
                    <select id="form-market" name="market_id" required
                            class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition">
                        <option value="">Market Seçin</option>
                        <?php foreach ($markets as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="form-title" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Başlık *</label>
                    <input type="text" id="form-title" name="title" required
                           class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                           placeholder="BİM 6 Haziran 2026 Aktüel Kataloğu">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="form-start-date" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Başlangıç Tarihi *</label>
                        <input type="date" id="form-start-date" name="start_date" required
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition">
                    </div>
                    <div>
                        <label for="form-end-date" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Bitiş Tarihi *</label>
                        <input type="date" id="form-end-date" name="end_date" required
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kapak Görseli *</label>
                    <div class="flex items-center gap-4">
                        <div id="cover-preview-container" class="w-16 h-20 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-center overflow-hidden shrink-0">
                            <span id="cover-preview-placeholder" class="material-symbols-outlined text-slate-600">image</span>
                            <img id="cover-preview-img" class="w-full h-full object-cover hidden">
                        </div>
                        <div class="flex-1">
                            <input type="file" id="form-cover" name="cover_image" accept="image/*" class="hidden" onchange="previewCover(this)">
                            <button type="button" onclick="document.getElementById('form-cover').click()" 
                                    class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2.5 rounded-xl text-sm font-semibold transition">
                                Kapak Seç
                            </button>
                            <p class="text-xs text-slate-500 mt-2">Katalogların ana listede görünecek ön yüzü.</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-800 pt-4">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Broşür İçerik Türü</label>
                    <div class="flex gap-4 mb-4">
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input type="radio" name="content_type" value="images" checked onchange="toggleContentInputs()"
                                   class="text-red-600 focus:ring-red-500 bg-slate-950 border-slate-800">
                            Çoklu Görsel Yükle (PNG/JPG)
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input type="radio" name="content_type" value="pdf" onchange="toggleContentInputs()"
                                   class="text-red-600 focus:ring-red-500 bg-slate-950 border-slate-800">
                            Tek PDF Dosyası Yükle
                        </label>
                    </div>

                    <!-- Images Upload Input -->
                    <div id="input-group-images" class="space-y-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Sayfa Görsellerini Seçin</label>
                        <input type="file" name="image_pages[]" multiple accept="image/*"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 text-slate-300 rounded-xl px-4 py-2 outline-none text-sm file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-800 file:text-white hover:file:bg-slate-700">
                        <p class="text-xs text-slate-500">Birden fazla görsel seçebilirsiniz. Dosya adlarına göre sıralanırlar (örn: sayfa1.jpg, sayfa2.jpg).</p>
                    </div>

                    <!-- PDF Upload Input -->
                    <div id="input-group-pdf" class="space-y-2 hidden">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">PDF Dosyası Seçin</label>
                        <input type="file" name="pdf_file" accept="application/pdf"
                               class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 text-slate-300 rounded-xl px-4 py-2 outline-none text-sm file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-800 file:text-white hover:file:bg-slate-700">
                        <p class="text-xs text-slate-500">Tek bir PDF dosyası yükleyin. Sayfalar otomatik olarak tarayıcıda render edilecektir.</p>
                    </div>
                    
                    <div id="edit-keep-notice" class="hidden mt-3 text-xs bg-amber-500/10 border border-amber-500/20 text-amber-300 p-3 rounded-xl">
                        💡 Düzenlerken yeni görsel veya PDF yüklemezseniz, broşürün mevcut sayfaları korunacaktır.
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
        const formMarket = document.getElementById('form-market');
        const formTitle = document.getElementById('form-title');
        const formStartDate = document.getElementById('form-start-date');
        const formEndDate = document.getElementById('form-end-date');
        const formExistingCover = document.getElementById('form-existing-cover');
        const coverPreviewPlaceholder = document.getElementById('cover-preview-placeholder');
        const coverPreviewImg = document.getElementById('cover-preview-img');
        
        const groupImages = document.getElementById('input-group-images');
        const groupPdf = document.getElementById('input-group-pdf');
        const editNotice = document.getElementById('edit-keep-notice');

        function openModal() {
            modalTitle.innerText = "Yeni Broşür Ekle";
            formId.value = "";
            formMarket.value = "";
            formTitle.value = "";
            formStartDate.value = "";
            formEndDate.value = "";
            formExistingCover.value = "";
            
            coverPreviewImg.src = "";
            coverPreviewImg.classList.add('hidden');
            coverPreviewPlaceholder.classList.remove('hidden');
            
            // Default to images
            document.querySelector('input[name="content_type"][value="images"]').checked = true;
            editNotice.classList.add('hidden');
            
            toggleContentInputs();
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        function editBrochure(brochure) {
            modalTitle.innerText = "Broşürü Düzenle";
            formId.value = brochure.id;
            formMarket.value = brochure.market_id;
            formTitle.value = brochure.title;
            formStartDate.value = brochure.start_date;
            formEndDate.value = brochure.end_date;
            formExistingCover.value = brochure.cover_image || "";
            
            if (brochure.cover_image) {
                coverPreviewImg.src = "../uploads/brochures/" + brochure.cover_image;
                coverPreviewImg.classList.remove('hidden');
                coverPreviewPlaceholder.classList.add('hidden');
            } else {
                coverPreviewImg.src = "";
                coverPreviewImg.classList.add('hidden');
                coverPreviewPlaceholder.classList.remove('hidden');
            }
            
            if (brochure.pdf_path && brochure.pdf_path !== "") {
                document.querySelector('input[name="content_type"][value="pdf"]').checked = true;
            } else {
                document.querySelector('input[name="content_type"][value="images"]').checked = true;
            }
            
            editNotice.classList.remove('hidden');
            toggleContentInputs();
            modal.classList.remove('hidden');
        }

        function previewCover(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    coverPreviewImg.src = e.target.result;
                    coverPreviewImg.classList.remove('hidden');
                    coverPreviewPlaceholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function toggleContentInputs() {
            const selectedType = document.querySelector('input[name="content_type"]:checked').value;
            if (selectedType === 'pdf') {
                groupImages.classList.add('hidden');
                groupPdf.classList.remove('hidden');
            } else {
                groupImages.classList.remove('hidden');
                groupPdf.classList.add('hidden');
            }
        }
    </script>
</body>
</html>