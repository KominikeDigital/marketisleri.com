<?php require 'config.php'; 
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT b.*, m.name as market_name FROM brochures b JOIN markets m ON b.market_id = m.id WHERE b.id = ?");
$stmt->execute([$id]);
$brochure = $stmt->fetch();
if (!$brochure) die("Broşür bulunamadı!");

$pages_stmt = $pdo->prepare("SELECT * FROM brochure_pages WHERE brochure_id = ? ORDER BY page_number ASC");
$pages_stmt->execute([$id]);
$pages = $pages_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <title><?= $brochure['market_name'] ?> - Broşür İzleyici</title>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow p-4 flex justify-between items-center max-w-7xl mx-auto">
        <a href="index.php" class="font-bold text-red-600 text-xl">← Geri Dön</a>
        <h1 class="font-bold text-lg"><?= $brochure['title'] ?></h1>
        <div class="w-20"></div>
    </nav>

    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden p-4 text-center">
            <img id="mainImg" src="uploads/brochures/<?= $pages[0]['image_path'] ?>" class="w-full h-auto rounded-xl shadow-inner">
            <div class="flex justify-center items-center gap-6 mt-6">
                <button onclick="changePage(-1)" class="bg-gray-200 p-3 rounded-full hover:bg-gray-300 transition">←</button>
                <span id="pageNo" class="font-bold text-lg">Sayfa 1 / <?= count($pages) ?></span>
                <button onclick="changePage(1)" class="bg-gray-200 p-3 rounded-full hover:bg-gray-300 transition">→</button>
            </div>
        </div>
    </div>

    <script>
        const pages = <?= json_encode(array_column($pages, 'image_path')) ?>;
        let current = 0;
        function changePage(dir) {
            current += dir;
            if (current < 0) current = 0;
            if (current >= pages.length) current = pages.length - 1;
            document.getElementById('mainImg').src = 'uploads/brochures/' + pages[current];
            document.getElementById('pageNo').innerText = 'Sayfa ' + (current + 1) + ' / ' + pages.length;
        }
    </script>
</body>
</html>