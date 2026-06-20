<?php
require '../config.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

// ─── Full scraper map (authoritative, always up to date) ─────────────────────
// Local DB slug => aktuelbrosurler.com URL
$SCRAPER_MAP = [
    'bim'                            => 'https://aktuelbrosurler.com/bim/brosurler',
    'a101'                           => 'https://aktuelbrosurler.com/a101/brosurler',
    'sok'                            => 'https://aktuelbrosurler.com/sok-market/brosurler',
    'migros'                         => 'https://aktuelbrosurler.com/migros/brosurler',
    'carrefoursa'                    => 'https://aktuelbrosurler.com/carrefour/brosurler',
    'tarim-kredi-market'             => 'https://aktuelbrosurler.com/tarim-kredi-kooperatif_market/brosurler',
    'tar-m-kredi-market-1780824588'  => 'https://aktuelbrosurler.com/tarim-kredi-kooperatif_market/brosurler',
    'metro'                          => 'https://aktuelbrosurler.com/metrotoptancimarket/brosurler',
    'ozdilek'                        => 'https://aktuelbrosurler.com/ozdilek/brosurler',
    'file'                           => 'https://aktuelbrosurler.com/file-market/brosurler',
    'bizim-toptan-satis-magazalari'  => 'https://aktuelbrosurler.com/bizimtoptanmarket/brosurler',
    'bizim-toptan'                   => 'https://aktuelbrosurler.com/bizimtoptanmarket/brosurler',
    'tespo-cash-carry'               => 'https://aktuelbrosurler.com/tespo/brosurler',
    'akyurt-supermarket'             => 'https://aktuelbrosurler.com/akyurtsupermarket/brosurler',
    'ali-pehlivanoglu'               => 'https://aktuelbrosurler.com/alipehlivanoglu/brosurler',
    'altun-market'                   => 'https://aktuelbrosurler.com/altunmarket/brosurler',
    'altunbilekler-market'           => 'https://aktuelbrosurler.com/altunbileklermarket/brosurler',
    'anpa-gross'                     => 'https://aktuelbrosurler.com/anpa-gross/brosurler',
    'arden-market'                   => 'https://aktuelbrosurler.com/arden-market/brosurler',
    'aypa-market'                    => 'https://aktuelbrosurler.com/aypa_market/brosurler',
    'baris-gross-market'             => 'https://aktuelbrosurler.com/barisgrossmarket/brosurler',
    'basdas-market'                  => 'https://aktuelbrosurler.com/basdasmarket/brosurler',
    'basgimpa'                       => 'https://aktuelbrosurler.com/basgimpa/brosurler',
    'beykoz-market'                  => 'https://aktuelbrosurler.com/beykoz_market/brosurler',
    'bicen-market'                   => 'https://aktuelbrosurler.com/bicenmarket/brosurler',
    'bonveno'                        => 'https://aktuelbrosurler.com/Bonveno/brosurler',
    'cagdas-market'                  => 'https://aktuelbrosurler.com/cagdas-market/brosurler',
    'cagri-market'                   => 'https://aktuelbrosurler.com/cagrimarket/brosurler',
    'carsi-market'                   => 'https://aktuelbrosurler.com/carsi-market/brosurler',
    'damla-hipermarket'              => 'https://aktuelbrosurler.com/damla_hipermarket/brosurler',
    'egesok-market'                  => 'https://aktuelbrosurler.com/egesok/brosurler',
    'esenlik-market'                 => 'https://aktuelbrosurler.com/esenlik_market/brosurler',
    'essen-market'                   => 'https://aktuelbrosurler.com/essen_market/brosurler',
    'etik-hipermarket'               => 'https://aktuelbrosurler.com/etik-hipermarket/brosurler',
    'hakmar'                         => 'https://aktuelbrosurler.com/hakmar/brosurler',
    'hakmar-ekspres'                 => 'https://aktuelbrosurler.com/hakmar-ekspres/brosurler',
    'onur-market'                    => 'https://aktuelbrosurler.com/onurmarket/brosurler',
    'ozhan-marketler'                => 'https://aktuelbrosurler.com/ozhanmarketler/brosurler',
    'sembol-center'                  => 'https://aktuelbrosurler.com/sembolcenter/brosurler',
    'serra-grup-market'              => 'https://aktuelbrosurler.com/grup_serra_market/brosurler',
    'sevikoglu-market'               => 'https://aktuelbrosurler.com/sevikoglu_market/brosurler',
    'seyhanlar-market'               => 'https://aktuelbrosurler.com/seyhanlargrospermarket/brosurler',
    'show-hipermarket'               => 'https://aktuelbrosurler.com/show_hipermarket/brosurler',
    'sultan-market'                  => 'https://aktuelbrosurler.com/sultanmarket/brosurler',
    'tahtakale-spot'                 => 'https://aktuelbrosurler.com/tahtakale-spot/brosurler',
    'tema-market'                    => 'https://aktuelbrosurler.com/tema-market/brosurler',
    'ucler-market'                   => 'https://aktuelbrosurler.com/uclermarket/brosurler',
    'snowy-ulu-kardesler'            => 'https://aktuelbrosurler.com/ulukardesler/brosurler',
    'yunus-market'                   => 'https://aktuelbrosurler.com/yunusmarket/brosurler',
    'sehzade-market'                 => 'https://aktuelbrosurler.com/sehzademarket/brosurler',
    'macrocenter'                    => 'https://aktuelbrosurler.com/macrocenter/brosurler',
    'namli-hipermarket'              => 'https://aktuelbrosurler.com/namlihipermarket/brosurler',
    'oruc-market'                    => 'https://aktuelbrosurler.com/oruc-market/brosurler',
];

// ─── Markets to ensure exist ──────────────────────────────────────────────────
$MARKETS_TO_ENSURE = [
    ['BİM',                  'bim',                   'bim-1780746538.png',        'BİM Aktüel Ürünler ve İndirim Broşürleri',   1],
    ['A101',                 'a101',                  'a101-1780746532.jpg',       'A101 Aldın Aldın İndirim Kataloğu',          1],
    ['ŞOK',                  'sok',                   'sok-1780746544.png',        'ŞOK Haftanın Fırsatları Kataloğu',           1],
    ['Akyurt Süpermarket',   'akyurt-supermarket',    'akyurt-supermarket.png',    'Akyurt Süpermarket İndirim Kataloğu',        1],
    ['Ali Pehlivanoğlu',     'ali-pehlivanoglu',      'ali-pehlivanoglu.png',      'Ali Pehlivanoğlu İndirim Broşürleri',        1],
    ['Altun Market',         'altun-market',          'altun-market.png',          'Altun Market Aktüel Ürünler',                1],
    ['Altunbilekler Market', 'altunbilekler-market',  'altunbilekler-market.png',  'Altunbilekler Market İndirim Broşürü',       1],
    ['Anpa Gross',           'anpa-gross',            'anpa-gross.png',            'Anpa Gross İndirim Broşürleri',              1],
    ['Arden Market',         'arden-market',          'arden-market.png',          'Arden Market Aktüel Ürünler',                1],
    ['Aypa Market',          'aypa-market',           'aypa-market.png',           'Aypa Market İndirim Kataloğu',               1],
    ['Barış Gross Market',   'baris-gross-market',    'baris-gross-market.png',    'Barış Gross Market İndirim Broşürü',         1],
    ['Başdaş Market',        'basdas-market',         'basdas-market.png',         'Başdaş Market Aktüel Ürünler',               1],
    ['Başgimpa',             'basgimpa',              'basgimpa.png',              'Başgimpa İndirim Broşürleri',                1],
    ['Beykoz Market',        'beykoz-market',         'beykoz-market.png',         'Beykoz Market İndirim Kataloğu',             1],
    ['Biçen Market',         'bicen-market',          'bicen-market.png',          'Biçen Market Aktüel Ürünler',                1],
    ['Bizim Toptan',         'bizim-toptan',          'bizim-toptan.png',          'Bizim Toptan İndirim Broşürleri',            1],
    ['Bonveno',              'bonveno',               'bonveno.png',               'Bonveno Market Aktüel Ürünler',              1],
    ['Çağdaş Market',        'cagdas-market',         'cagdas-market.png',         'Çağdaş Market İndirim Kataloğu',             1],
    ['Çağrı Market',         'cagri-market',          'cagri-market.png',          'Çağrı Market Aktüel Ürünler',                1],
    ['Çarşı Market',         'carsi-market',          'carsi-market.png',          'Çarşı Market İndirim Broşürü',               1],
    ['Damla Hipermarket',    'damla-hipermarket',     'damla-hipermarket.png',     'Damla Hipermarket Aktüel Ürünler',           1],
    ['Egeşok Market',        'egesok-market',         'egesok-market.png',         'Egeşok Market İndirim Kataloğu',             1],
    ['Esenlik Market',       'esenlik-market',        'esenlik-market.png',        'Esenlik Market Aktüel Ürünler',              1],
    ['Essen Market',         'essen-market',          'essen-market.png',          'Essen Market İndirim Broşürü',               1],
    ['Etik Hipermarket',     'etik-hipermarket',      'etik-hipermarket.png',      'Etik Hipermarket Aktüel Ürünler',            1],
    ['Hakmar',               'hakmar',                'hakmar.png',                'Hakmar İndirim Broşürleri',                  1],
    ['Hakmar Ekspres',       'hakmar-ekspres',        'hakmar-ekspres.png',        'Hakmar Ekspres Aktüel Ürünler',              1],
    ['Migros',               'migros',                'migros.png',                'Migros Haftanın Fırsatları Kataloğu',        1],
    ['CarrefourSA',          'carrefoursa',           'carrefoursa.png',           'CarrefourSA İndirim ve Kampanya Broşürleri', 1],
    ['Metro',                'metro',                 'metro.png',                 'Metro İndirim ve Fırsatları',                1],
    ['Onur Market',          'onur-market',           'onur-market.png',           'Onur Market İndirim Broşürü',                1],
    ['Özhan Marketler',      'ozhan-marketler',       'ozhan-marketler.png',       'Özhan Marketler Aktüel Ürünler',             1],
    ['Sembol Center',        'sembol-center',         'sembol-center.png',         'Sembol Center İndirim Kataloğu',             1],
    ['Serra Grup Market',    'serra-grup-market',     'serra-grup-market.png',     'Serra Grup Market Aktüel Ürünler',           1],
    ['Şevikoğlu Market',     'sevikoglu-market',      'sevikoglu-market.png',      'Şevikoğlu Market İndirim Broşürü',           1],
    ['Seyhanlar Market',     'seyhanlar-market',      'seyhanlar-market.png',      'Seyhanlar Market Aktüel Ürünler',            1],
    ['Show Hipermarket',     'show-hipermarket',      'show-hipermarket.png',      'Show Hipermarket İndirim Kataloğu',          1],
    ['Sultan Market',        'sultan-market',         'sultan-market.png',         'Sultan Market Aktüel Ürünler',               1],
    ['Tahtakale Spot',       'tahtakale-spot',        'tahtakale-spot.png',        'Tahtakale Spot İndirim Broşürü',             1],
    ['Tema Market',          'tema-market',           'tema-market.png',           'Tema Market Aktüel Ürünler',                 1],
    ['Üçler Market',         'ucler-market',          'ucler-market.png',          'Üçler Market İndirim Kataloğu',              1],
    ['Snowy Ulu Kardeşler',  'snowy-ulu-kardesler',   'Snowy Ulu Kardeşler.png',   'Snowy Ulu Kardeşler İndirim Broşürleri',     1],
    ['Yunus Market',         'yunus-market',          'yunus-market.png',          'Yunus Market İndirim Broşürü',               1],
    ['Şehzade Market',       'sehzade-market',        'sehzade-market.png',        'Şehzade Market İndirim Kataloğu',            1],
    ['MacroCenter',          'macrocenter',           'macrocenter.png',           'MacroCenter Aktüel Ürünler',                 1],
    ['Namlı Hipermarket',    'namli-hipermarket',     'namli-hipermarket.png',     'Namlı Hipermarket İndirim Broşürü',          1],
    ['Oruç Market',          'oruc-market',           'oruc-market.png',           'Oruç Market Aktüel Ürünler',                 1],
    ['Özdilek',              'ozdilek',               'ozdilek.png',               'Özdilek İndirim ve Kampanya Broşürleri',     2],
    ['File Market',          'file',                  'file.png',                  'File Market Aktüel Ürünler',                 1],
    ['Tespo Cash & Carry',   'tespo-cash-carry',      'tespo-cash-carry.png',      'Tespo Cash & Carry İndirim Broşürleri',      1],
    ['Tarım Kredi Market',   'tarim-kredi-market',    'tarim-kredi-market.png',    'Tarım Kredi Market İndirim ve Fırsatları',   1],
];

$log    = [];
$errors = [];

if (isset($_POST['apply'])) {
    // Step 1: Ensure all markets exist
    $created = 0;
    foreach ($MARKETS_TO_ENSURE as $m) {
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM markets WHERE slug = ?");
            $chk->execute([$m[1]]);
            if ($chk->fetchColumn() == 0) {
                try {
                    $ins = $pdo->prepare("INSERT INTO markets (name, slug, logo, description, category_id) VALUES (?,?,?,?,?)");
                    $ins->execute([$m[0], $m[1], $m[2], $m[3], $m[4]]);
                    $log[] = "✅ Oluşturuldu: {$m[0]} ({$m[1]})";
                    $created++;
                } catch (PDOException $e) {
                    $errors[] = "❌ {$m[0]}: " . $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            $errors[] = "❌ {$m[0]} kontrol: " . $e->getMessage();
        }
    }
    $log[] = "📦 Yeni market oluşturuldu: $created";

    // Step 2: Force-update all scraper settings
    $updated = 0;
    $not_found = 0;
    foreach ($SCRAPER_MAP as $slug => $url) {
        try {
            $upd = $pdo->prepare("UPDATE markets SET 
                scraper_url = ?,
                scraper_container = 'a.brosur-link',
                scraper_title = '.excerpt p',
                scraper_cover = '.media-wrapper',
                scraper_detail_link = '',
                scraper_page_image = '',
                scraper_active = 1
                WHERE slug = ?");
            $upd->execute([$url, $slug]);
            if ($upd->rowCount() > 0) {
                $log[] = "🔧 Güncellendi: $slug → $url";
                $updated++;
            } else {
                $log[] = "⚠️  DB'de bulunamadı: $slug";
                $not_found++;
            }
        } catch (PDOException $e) {
            $errors[] = "❌ $slug güncelleme: " . $e->getMessage();
        }
    }
    $log[] = "🔄 Güncellenen market: $updated | Bulunamayan: $not_found";

    // Step 3: Show final active count
    $active = (int)$pdo->query("SELECT COUNT(*) FROM markets WHERE scraper_active = 1")->fetchColumn();
    $log[] = "✅ Toplam aktif scraper: $active market";
}

// ─── Detect PHP path ──────────────────────────────────────────────────────────
$php_path = PHP_BINARY ?: (shell_exec('which php') ?: '/usr/bin/php');
$php_path = trim($php_path);
$php_version = PHP_VERSION;
$doc_root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
$script_path = $doc_root . '/admin/auto_scraper.php';

// Current counts
$active_count = (int)$pdo->query("SELECT COUNT(*) FROM markets WHERE scraper_active = 1 AND scraper_url IS NOT NULL AND scraper_url != ''")->fetchColumn();
$total_count  = (int)$pdo->query("SELECT COUNT(*) FROM markets")->fetchColumn();
$broken_count = (int)$pdo->query("SELECT COUNT(*) FROM markets WHERE scraper_active = 1 AND scraper_url NOT LIKE '%aktuelbrosurler.com%' AND scraper_url IS NOT NULL AND scraper_url != ''")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scraper Ayarlarını Uygula - marketisleri.com</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../uploads/tailwind.min.css">
    <style>
        body { font-family: 'Hanken Grotesk', sans-serif; }
        .font-title { font-family: 'Plus Jakarta Sans', sans-serif; }
        .log-box {
            background: #020617; color: #4ade80; font-family: 'Courier New', monospace;
            font-size: 12px; padding: 1rem; border-radius: 10px;
            border: 1px solid #1e3a2f; max-height: 400px; overflow-y: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shrink-0">
        <div class="p-6 border-b border-slate-800">
            <a href="index.php" class="font-title text-xl font-black text-white flex items-center gap-2">
                <?php if (file_exists('../uploads/logo.png')): ?>
                    <img src="../uploads/logo.png" alt="logo" class="h-8 w-auto object-contain">
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
            <a href="cron_setup.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">schedule</span> Otomasyon &amp; Cron
            </a>
            <a href="apply_scrapers.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-amber-600 text-white font-semibold transition-all">
                <span class="material-symbols-outlined text-lg">build</span> Scraper Ayarları
            </a>
            <a href="analyze_brochures.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
                <span class="material-symbols-outlined text-lg">explore</span> Broşür AI Analizi
            </a>
            <a href="blogs.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-white transition-all">
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

    <!-- Main -->
    <main class="flex-1 flex flex-col overflow-y-auto">
        <header class="h-20 bg-slate-900/40 backdrop-blur-md border-b border-slate-800 flex items-center px-8 shrink-0">
            <h1 class="font-title text-2xl font-bold text-white">Scraper Ayarlarını Uygula</h1>
        </header>

        <div class="p-8 space-y-6 max-w-4xl w-full mx-auto">

            <!-- Status -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
                    <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider mb-1">Toplam Market</div>
                    <div class="text-3xl font-black text-white"><?= $total_count ?></div>
                </div>
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
                    <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider mb-1">Aktif Scraper</div>
                    <div class="text-3xl font-black text-emerald-400"><?= $active_count ?></div>
                </div>
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5">
                    <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider mb-1">Yanlış URL ⚠️</div>
                    <div class="text-3xl font-black text-<?= $broken_count > 0 ? 'red' : 'emerald' ?>-400"><?= $broken_count ?></div>
                    <div class="text-slate-500 text-xs mt-1">aktuelbrosurler.com dışında</div>
                </div>
            </div>

            <?php if ($broken_count > 0 || $active_count < 10): ?>
            <!-- Warning Banner -->
            <div class="bg-red-500/10 border border-red-500/30 rounded-2xl p-5 flex gap-4 items-start">
                <span class="material-symbols-outlined text-red-400 text-2xl shrink-0">warning</span>
                <div>
                    <div class="font-bold text-red-300 text-lg">Scraper ayarları güncellenmesi gerekiyor!</div>
                    <div class="text-slate-400 text-sm mt-1">
                        <?= $broken_count ?> market yanlış URL'e sahip (örn: money.com.tr gibi eski ayarlar).
                        <?= max(0, 50 - $active_count) ?>+ market scraper'ı henüz aktifleştirilmemiş.
                        Aşağıdaki butona basarak tüm ayarları otomatik düzeltin.
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 flex gap-3 items-center">
                <span class="material-symbols-outlined text-emerald-400">check_circle</span>
                <div class="text-emerald-300 font-semibold">Tüm scraper ayarları doğru görünüyor. <?= $active_count ?> market aktif.</div>
            </div>
            <?php endif; ?>

            <!-- PHP Path Info -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 space-y-3">
                <div class="flex items-center gap-2 font-title font-bold text-white">
                    <span class="material-symbols-outlined text-blue-400">terminal</span>
                    PHP Yolu Tespiti (Cron için)
                </div>
                <div class="grid grid-cols-1 gap-2 text-sm">
                    <div class="flex items-center gap-3 bg-slate-950 rounded-xl px-4 py-3">
                        <span class="text-slate-400 w-32 shrink-0">PHP Binary:</span>
                        <code class="text-emerald-400 font-mono"><?= htmlspecialchars($php_path) ?></code>
                        <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($php_path) ?>').then(()=>alert('Kopyalandı!'))" 
                                class="ml-auto text-slate-500 hover:text-white transition">
                            <span class="material-symbols-outlined text-sm">content_copy</span>
                        </button>
                    </div>
                    <div class="flex items-center gap-3 bg-slate-950 rounded-xl px-4 py-3">
                        <span class="text-slate-400 w-32 shrink-0">PHP Sürümü:</span>
                        <code class="text-blue-300 font-mono"><?= htmlspecialchars($php_version) ?></code>
                    </div>
                    <div class="flex items-center gap-3 bg-slate-950 rounded-xl px-4 py-3">
                        <span class="text-slate-400 w-32 shrink-0">Script Yolu:</span>
                        <code class="text-purple-300 font-mono text-xs"><?= htmlspecialchars($script_path) ?></code>
                    </div>
                </div>
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-3 text-sm">
                    <div class="font-semibold text-amber-300 mb-1">⚠️ Cron Komutunu Düzeltmeniz Gerekiyor</div>
                    <div class="text-slate-400 mb-2">Cron'u şu komutla güncelleyin (kopyalayın):</div>
                    <?php
                    $cron_cmd = trim($php_path) . ' ' . trim($script_path) . ' >> /home/marketis/scraper.log 2>&1';
                    ?>
                    <div class="flex gap-2 items-center">
                        <code id="correct-cron" class="flex-1 bg-slate-950 text-emerald-300 font-mono text-xs rounded-lg px-3 py-2 break-all"><?= htmlspecialchars($cron_cmd) ?></code>
                        <button onclick="navigator.clipboard.writeText(document.getElementById('correct-cron').innerText).then(()=>{this.textContent='✓'; setTimeout(()=>this.innerHTML='<span class=\'material-symbols-outlined text-sm\'>content_copy</span>',2000)})"
                                class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-2 rounded-lg transition shrink-0">
                            <span class="material-symbols-outlined text-sm">content_copy</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Apply Form -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden">
                <div class="p-6 border-b border-slate-800">
                    <h3 class="font-title text-xl font-bold text-white">Tüm Scraper Ayarlarını Uygula</h3>
                    <p class="text-slate-400 text-sm mt-1">
                        Bu işlem tüm marketleri DB'ye ekler ve hepsinin scraper URL'lerini aktuelbrosurler.com ile günceller.
                        Eski/yanlış URL'ler (money.com.tr gibi) düzeltilir.
                    </p>
                </div>
                <div class="p-6">
                    <?php if (!empty($log)): ?>
                        <div class="log-box mb-4"><?= htmlspecialchars(implode("\n", array_merge($log, $errors))) ?></div>
                        <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-xl p-4 text-emerald-300 font-semibold text-sm">
                            ✅ İşlem tamamlandı. Artık <a href="cron_setup.php" class="underline hover:text-white">"Şimdi Çalıştır"</a> ile scraperı test edebilirsiniz.
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <button type="submit" name="apply" value="1"
                                    class="w-full flex items-center justify-center gap-3 bg-amber-600 hover:bg-amber-500 text-white font-black text-lg px-6 py-4 rounded-2xl transition shadow-lg shadow-amber-600/20">
                                <span class="material-symbols-outlined text-2xl">build</span>
                                Tüm Scraper Ayarlarını Şimdi Uygula (<?= count($SCRAPER_MAP) ?> market)
                            </button>
                            <p class="text-slate-500 text-xs text-center mt-3">
                                Mevcut broşürler silinmez. Sadece scraper URL ve ayarları güncellenir.
                            </p>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Current DB State -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden">
                <div class="p-6 border-b border-slate-800">
                    <h3 class="font-title text-lg font-bold text-white">Mevcut Scraper Durumu</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-950/40 text-slate-400 text-xs uppercase tracking-wider">
                            <tr>
                                <th class="p-3 pl-5">Market</th>
                                <th class="p-3">Slug</th>
                                <th class="p-3">Scraper URL</th>
                                <th class="p-3">Aktif</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                        <?php
                        $all = $pdo->query("SELECT name, slug, scraper_url, scraper_active FROM markets ORDER BY scraper_active DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($all as $m):
                            $is_correct = $m['scraper_active'] && str_contains($m['scraper_url'] ?? '', 'aktuelbrosurler.com');
                            $is_broken  = $m['scraper_active'] && $m['scraper_url'] && !str_contains($m['scraper_url'], 'aktuelbrosurler.com');
                            $row_class  = $is_broken ? 'bg-red-950/20' : '';
                        ?>
                        <tr class="hover:bg-slate-800/20 transition <?= $row_class ?>">
                            <td class="p-3 pl-5 font-semibold text-white"><?= htmlspecialchars($m['name']) ?></td>
                            <td class="p-3 text-slate-400 font-mono text-xs"><?= htmlspecialchars($m['slug']) ?></td>
                            <td class="p-3 text-slate-400 text-xs max-w-xs truncate" title="<?= htmlspecialchars($m['scraper_url'] ?? '') ?>">
                                <?php if ($is_broken): ?>
                                    <span class="text-red-400">⚠️ <?= htmlspecialchars($m['scraper_url']) ?></span>
                                <?php elseif ($is_correct): ?>
                                    <span class="text-emerald-400">✓ <?= htmlspecialchars($m['scraper_url']) ?></span>
                                <?php else: ?>
                                    <span class="text-slate-600">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <?php if ($m['scraper_active']): ?>
                                    <span class="text-xs bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2 py-1 rounded-full">Aktif</span>
                                <?php else: ?>
                                    <span class="text-xs bg-slate-800 text-slate-500 px-2 py-1 rounded-full">Pasif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
