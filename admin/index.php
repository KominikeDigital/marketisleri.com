<?php require 'config.php'; ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@700;800&family=Hanken+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <title>marketisleri.com - Güncel Market Broşürleri</title>
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .headline { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#fff8f7] text-[#291715]">
    <header class="fixed top-0 w-full z-50 bg-white shadow-sm h-16 flex items-center justify-between px-6 max-w-7xl mx-auto">
        <a class="headline text-2xl font-black text-[#bd001a]" href="/">marketisleri.com</a>
        <div class="hidden md:flex gap-6 font-bold text-gray-600">
            <a href="#" class="text-[#bd001a] border-b-2 border-[#bd001a]">Süpermarket</a>
            <a href="#">Yapı Market</a>
            <a href="#">Teknoloji</a>
        </div>
        <button class="bg-[#bd001a] text-white px-6 py-2 rounded-full font-bold">Giriş Yap</button>
    </header>

    <main class="pt-24 max-w-7xl mx-auto px-6">
        <section class="text-center py-16">
            <h1 class="headline text-5xl font-extrabold mb-6">Hangi marketin broşürünü arıyorsunuz?</h1>
            <p class="text-xl text-gray-500 mb-10">En güncel market indirimleri ve aktüel ürünleri tek yerde.</p>
            <div class="max-w-2xl mx-auto relative">
                <input type="text" class="w-full p-5 pl-12 rounded-full border shadow-lg focus:ring-2 focus:ring-red-500 outline-none" placeholder="BİM, A101, Migros ara...">
                <span class="absolute left-4 top-5 material-symbols-outlined text-gray-400">search</span>
            </div>
        </section>

        <h2 class="headline text-3xl font-bold mb-8">Yeni Yayınlanan Broşürler</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-20">
            <?php
            $stmt = $pdo->query("SELECT b.*, m.name as market_name FROM brochures b JOIN markets m ON b.market_id = m.id ORDER BY created_at DESC");
            while($row = $stmt->fetch()): ?>
                <div class="bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all group cursor-pointer" onclick="window.location='viewer.php?id=<?= $row['id'] ?>'">
                    <div class="relative aspect-[3/4] overflow-hidden">
                        <img src="uploads/brochures/<?= $row['cover_image'] ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                        <div class="absolute top-4 left-4 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded uppercase">YENİ</div>
                    </div>
                    <div class="p-5">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="font-bold text-lg"><?= $row['market_name'] ?></span>
                        </div>
                        <p class="text-gray-500 text-sm mb-4"><?= $row['title'] ?></p>
                        <div class="flex justify-between items-center border-t pt-4">
                            <span class="text-red-600 font-bold text-xs">Bitiş: <?= $row['end_date'] ?></span>
                            <a href="viewer.php?id=<?= $row['id'] ?>" class="text-[#bd001a] font-bold">İncele →</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </main>
</body>
</html>