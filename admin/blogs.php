<?php
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

// Helper function to create slug
function createSlug($string) {
    $replace = array(
        '?' => '', '*' => '', '!' => '', '#' => '', '$' => '', '%' => '', '&' => '', '(' => '', ')' => '', '=' => '',
        '/' => '', '\\' => '', '|' => '', '{' => '', '}' => '', '[' => '', ']' => '', ':' => '', ';' => '', ',' => '',
        '.' => '', '<' => '', '>' => '', '"' => '', '\'' => '', '`' => '', '~' => '', '^' => '', '+' => '',
        'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'I' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o',
        'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u', ' ' => '-', '--' => '-', '---' => '-'
    );
    $string = str_replace(array_keys($replace), array_values($replace), $string);
    $string = strtolower(trim($string, '-'));
    return preg_replace('/-+/', '-', $string);
}

// Handle Quick Seed
if (isset($_POST['quick_seed'])) {
    try {
        $pdo->exec("DELETE FROM blog_posts");
        try {
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name='blog_posts'");
        } catch (PDOException $e) {}
        
        require_once dirname(__DIR__) . '/lib/default_blogs.php';
        $posts = $seeded_posts;
        
        $default_cover = "uploads/blog_cover_default.png";
        $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, summary, cover_image, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        
        $inserted = 0;
        $days_offset = 100;
        foreach ($posts as $idx => $p) {
            $slug = createSlug($p['title']);
            
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ?");
            $check_stmt->execute([$slug]);
            if ($check_stmt->fetchColumn() > 0) {
                $slug .= "-" . ($idx + 1);
            }
            
            $created_at = date('Y-m-d H:i:s', strtotime("-$days_offset days +" . ($idx * 24) . " hours"));
            $stmt->execute([
                $p['title'],
                $slug,
                $p['content'],
                $p['summary'],
                $default_cover,
                $created_at
            ]);
            $inserted++;
        }
        $success = "$inserted adet blog yazısı başarıyla yüklendi!";
    } catch (Exception $e) {
        $error = "Seed hatası: " . $e->getMessage();
    }
}

// Handle Add/Edit Blog Post
if (isset($_POST['save'])) {
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? intval($_POST['id']) : null;
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($slug)) {
        $slug = createSlug($title);
    } else {
        $slug = createSlug($slug);
    }
    
    if (empty($title) || empty($content)) {
        $error = "Lütfen gerekli alanları (Başlık, İçerik) doldurun.";
    } else {
        // Cover Image Upload Handling
        $cover_name = $_POST['existing_cover'] ?? 'uploads/blog_cover_default.png';
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['cover_image']['tmp_name'];
            $file_name = $_FILES['cover_image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['png', 'jpg', 'jpeg', 'webp'];
            if (in_array($file_ext, $allowed_exts)) {
                // Ensure target folder exists
                if (!is_dir('../uploads/blog')) {
                    mkdir('../uploads/blog', 0755, true);
                }
                
                // Delete old custom logo/cover if replacing
                if (!empty($cover_name) && $cover_name !== 'uploads/blog_cover_default.png' && file_exists('../' . $cover_name)) {
                    @unlink('../' . $cover_name);
                }
                
                // Set clean unique file name
                $new_filename = 'blog-' . $slug . '-' . time() . '.' . $file_ext;
                $cover_name = 'uploads/blog/' . $new_filename;
                $dest_path = '../' . $cover_name;
                
                if (!move_uploaded_file($file_tmp, $dest_path)) {
                    $error = "Resim yüklenirken bir hata oluştu.";
                } else {
                    compress_and_resize_image($dest_path, 800, 80);
                }
            } else {
                $error = "Geçersiz resim formatı. Sadece PNG, JPG, JPEG ve WEBP kabul edilir.";
            }
        }
        
        if (!$error) {
            if ($id === null) {
                // Check if slug is unique
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ?");
                $check_stmt->execute([$slug]);
                if ($check_stmt->fetchColumn() > 0) {
                    $slug = $slug . '-' . rand(100, 999);
                }
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, summary, content, cover_image) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $slug, $summary, $content, $cover_name]);
                    $success = "Blog yazısı başarıyla eklendi.";
                } catch (PDOException $e) {
                    $error = "Kaydetme hatası: " . $e->getMessage();
                }
            } else {
                // Check if slug is unique for other posts
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ? AND id != ?");
                $check_stmt->execute([$slug, $id]);
                if ($check_stmt->fetchColumn() > 0) {
                    $slug = $slug . '-' . rand(100, 999);
                }
                
                try {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, slug = ?, summary = ?, content = ?, cover_image = ? WHERE id = ?");
                    $stmt->execute([$title, $slug, $summary, $content, $cover_name, $id]);
                    $success = "Blog yazısı başarıyla güncellendi.";
                } catch (PDOException $e) {
                    $error = "Güncelleme hatası: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all Blog Posts
$posts_stmt = $pdo->query("SELECT * FROM blog_posts ORDER BY created_at DESC");
$blog_posts = $posts_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Yönetimi - marketisleri.com</title>
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
                <span class="material-symbols-outlined text-lg">space_dashboard</span> Dashboard
            </a>
            <a href="markets.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">storefront</span> Marketler
            </a>
            <a href="brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">menu_book</span> Broşürler
            </a>
            <a href="magic_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">auto_fix</span> Sihirli Broşür Ekle
            </a>
            <a href="amazon_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">shopping_basket</span> Amazon Broşür Ekle
            </a>
            <a href="hepsiburada_import.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">local_mall</span> Hepsiburada Broşür Ekle
            </a>
            <a href="cron_setup.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">schedule</span> Otomasyon &amp; Cron
            </a>
            <a href="apply_scrapers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">build</span> Scraper Ayarları
            </a>
            <a href="analyze_brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">explore</span> Broşür AI Analizi
            </a>
            <a href="blogs.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">article</span> Blog Yazıları
            </a>
            <a href="subscribers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">mail</span> Aboneler
            </a>
            <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">settings</span> Ayarlar
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-950/20 hover:text-red-300 transition-all font-semibold">
                <span class="material-symbols-outlined text-lg">logout</span> Oturumu Kapat
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        <!-- Header -->
        <header class="h-20 bg-slate-900/40 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-8 shrink-0">
            <h1 class="font-title text-2xl font-bold text-white font-bold">Blog Yazıları Yönetimi</h1>
            <div class="flex items-center gap-4">
                <form method="POST" onsubmit="return confirm('Mevcut tüm yazıları silip 50 adet varsayılan yazıyı yüklemek istediğinizden emin misiniz?')" class="inline">
                    <button type="submit" name="quick_seed" value="1" class="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2.5 rounded-xl font-semibold transition border border-slate-700 text-sm">
                        <span class="material-symbols-outlined text-sm">auto_awesome</span>
                        Acil Seeder (Hızlı Doldur)
                    </button>
                </form>
                <button onclick="openModal()" class="flex items-center gap-2 bg-red-600 hover:bg-red-500 text-white px-5 py-2.5 rounded-xl font-bold transition shadow-lg shadow-red-600/10 text-sm">
                    <span class="material-symbols-outlined text-lg">add_circle</span>
                    Yeni Yazı Ekle
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

            <!-- Blog List Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                <div class="p-6 border-b border-slate-800">
                    <h3 class="font-title text-xl font-bold text-white">Yazılar Listesi</h3>
                </div>

                <?php if (empty($blog_posts)): ?>
                    <div class="py-20 text-center text-slate-500">
                        <span class="material-symbols-outlined text-5xl mb-3 block text-slate-600">article</span>
                        Henüz blog yazısı eklenmemiş. Sağ üst köşeden ilk yazınızı ekleyin veya Hızlı Doldur seeder seçeneğini kullanın!
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-800 text-slate-400 text-xs font-semibold uppercase tracking-wider bg-slate-950/40">
                                    <th class="p-4 pl-6">Kapak Görseli</th>
                                    <th class="p-4">Başlık</th>
                                    <th class="p-4">Slug (URL)</th>
                                    <th class="p-4">Özet</th>
                                    <th class="p-4">Tarih</th>
                                    <th class="p-4 pr-6 text-right">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800 text-sm">
                                <?php foreach ($blog_posts as $post): ?>
                                    <tr class="hover:bg-slate-800/20 transition-all">
                                        <td class="p-4 pl-6">
                                            <img src="../<?= htmlspecialchars($post['cover_image'] ?: 'uploads/blog_cover_default.png') ?>" 
                                                 class="w-16 h-10 object-cover rounded-lg border border-slate-800 shadow" 
                                                 alt="Cover" onerror="this.src='../uploads/blog_cover_default.png'">
                                        </td>
                                        <td class="p-4 font-bold text-white max-w-xs truncate"><?= htmlspecialchars($post['title']) ?></td>
                                        <td class="p-4 text-slate-400 font-mono text-xs"><?= htmlspecialchars($post['slug']) ?></td>
                                        <td class="p-4 text-slate-400 max-w-sm truncate"><?= htmlspecialchars($post['summary'] ?? '-') ?></td>
                                        <td class="p-4 text-slate-400 text-xs"><?= date('d.m.Y H:i', strtotime($post['created_at'])) ?></td>
                                        <td class="p-4 pr-6 text-right space-x-2 shrink-0 whitespace-nowrap">
                                            <button onclick="editPost(<?= htmlspecialchars(json_encode($post)) ?>)" 
                                                    class="inline-flex items-center gap-1 bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                                <span class="material-symbols-outlined text-xs">edit</span>
                                                Düzenle
                                            </button>
                                            <a href="delete.php?type=blog&id=<?= $post['id'] ?>" 
                                               onclick="return confirm('Bu blog yazısını silmek istediğinizden emin misiniz?')"
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
        <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-2xl shadow-2xl my-auto animate-in fade-in zoom-in duration-250">
            <div class="p-6 border-b border-slate-800 flex justify-between items-center bg-slate-950/40">
                <h3 id="modal-title" class="font-title text-xl font-bold text-white">Yeni Blog Yazısı Ekle</h3>
                <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                <input type="hidden" id="form-id" name="id">
                <input type="hidden" id="form-existing-cover" name="existing_cover">
                
                <div>
                    <label for="form-title" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Başlık *</label>
                    <input type="text" id="form-title" name="title" required
                           class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                           placeholder="Yazı başlığını girin..." oninput="autoSlug()">
                </div>

                <div>
                    <label for="form-slug" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Slug (URL Adı)</label>
                    <input type="text" id="form-slug" name="slug"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                           placeholder="baslik-url-adi (Boş bırakılırsa otomatik üretilir)">
                </div>

                <div>
                    <label for="form-summary" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Özet (Liste Görünümünde Gösterilir)</label>
                    <textarea id="form-summary" name="summary" rows="2"
                              class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none transition"
                              placeholder="Yazının kısa özeti..."></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kapak Görseli</label>
                    <div class="flex items-center gap-4">
                        <div id="cover-preview-container" class="w-24 h-16 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-center overflow-hidden shrink-0">
                            <span id="cover-preview-placeholder" class="material-symbols-outlined text-slate-600">image</span>
                            <img id="cover-preview-img" class="w-full h-full object-cover hidden">
                        </div>
                        <div class="flex-1">
                            <input type="file" id="form-cover" name="cover_image" accept="image/*" class="hidden" onchange="previewCover(this)">
                            <button type="button" onclick="document.getElementById('form-cover').click()" 
                                    class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2.5 rounded-xl text-sm font-semibold transition">
                                Görsel Seç
                            </button>
                            <p class="text-xs text-slate-500 mt-2">PNG, JPG, JPEG, WEBP. Maks 2MB.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="form-content" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Yazı İçeriği (HTML formatında yazabilirsiniz) *</label>
                    <textarea id="form-content" name="content" rows="10" required
                              class="w-full bg-slate-950 border border-slate-800 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-white rounded-xl px-4 py-2.5 outline-none font-mono text-sm transition"
                              placeholder="<h2>Başlık</h2><p>Paragraf içeriği...</p>"></textarea>
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

    <!-- JS Helpers -->
    <script>
        const modal = document.getElementById('modal');
        const modalTitle = document.getElementById('modal-title');
        const formId = document.getElementById('form-id');
        const formTitle = document.getElementById('form-title');
        const formSlug = document.getElementById('form-slug');
        const formSummary = document.getElementById('form-summary');
        const formContent = document.getElementById('form-content');
        const formExistingCover = document.getElementById('form-existing-cover');
        
        const coverPreviewPlaceholder = document.getElementById('cover-preview-placeholder');
        const coverPreviewImg = document.getElementById('cover-preview-img');

        let isSlugEdited = false;

        function openModal() {
            modalTitle.textContent = "Yeni Blog Yazısı Ekle";
            formId.value = "";
            formTitle.value = "";
            formSlug.value = "";
            formSummary.value = "";
            formContent.value = "";
            formExistingCover.value = "uploads/blog_cover_default.png";
            
            coverPreviewImg.src = "";
            coverPreviewImg.classList.add('hidden');
            coverPreviewPlaceholder.classList.remove('hidden');
            
            isSlugEdited = false;
            modal.classList.remove('hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
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

        function editPost(post) {
            modalTitle.textContent = "Blog Yazısını Düzenle";
            formId.value = post.id;
            formTitle.value = post.title;
            formSlug.value = post.slug;
            formSummary.value = post.summary || "";
            formContent.value = post.content;
            formExistingCover.value = post.cover_image || "uploads/blog_cover_default.png";
            
            const coverPath = "../" + (post.cover_image || "uploads/blog_cover_default.png");
            coverPreviewImg.src = coverPath;
            coverPreviewImg.classList.remove('hidden');
            coverPreviewPlaceholder.classList.add('hidden');
            
            isSlugEdited = true;
            modal.classList.remove('hidden');
        }

        // Slug generation
        function autoSlug() {
            if (!isSlugEdited) {
                let title = formTitle.value;
                let slug = title
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                formSlug.value = slug;
            }
        }

        formSlug.addEventListener('input', () => {
            isSlugEdited = formSlug.value !== "";
        });
    </script>
</body>
</html>
