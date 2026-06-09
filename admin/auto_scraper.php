<?php
/**
 * auto_scraper.php
 * Automatic brochure scraper – fetches new brochures from aktuelbrosurler.com
 * for all markets that have scraper_active=1 configured.
 *
 * Triggered by:
 *  - cPanel Cron Job (CLI):  php /path/to/auto_scraper.php
 *  - Admin UI (HTTP):        GET/POST admin/auto_scraper.php?run=1&secret=TOKEN
 */

define('SCRAPER_START', microtime(true));

// Detect CLI mode
$is_cli = (php_sapi_name() === 'cli');

// In HTTP mode: require admin session or secret token
if (!$is_cli) {
    require '../config.php';
    $secret = trim($_GET['secret'] ?? '');
    $valid_secret = md5($admin_pass . 'scraper_secret_2026');
    $from_admin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
    $from_token = ($secret === $valid_secret);

    if (!$from_admin && !$from_token) {
        http_response_code(403);
        die(json_encode(['error' => 'Yetkisiz erişim.']));
    }

    // Only run if explicitly triggered
    if (!isset($_GET['run']) && !isset($_POST['run'])) {
        die(json_encode(['error' => 'run parametresi eksik.']));
    }

    header('Content-Type: text/plain; charset=utf-8');
    // Disable output buffering so progress shows live
    if (ob_get_level()) ob_end_flush();
    ob_implicit_flush(true);
} else {
    // CLI mode: bootstrap manually
    chdir(dirname(__DIR__));
    require 'config.php';
}

// ─── Log helpers ─────────────────────────────────────────────────────────────
function log_line(string $msg): void {
    echo $msg . "\n";
    flush();
}

// ─── cURL fetch helper ────────────────────────────────────────────────────────
function curl_get(string $url, array $extra_headers = [], int $timeout = 20): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => array_merge([
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: tr-TR,tr;q=0.9,en;q=0.7',
        ], $extra_headers),
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ?: '';
}

// ─── Image download helper ───────────────────────────────────────────────────
function download_image(string $url, string $dest, array $extra_headers = []): bool {
    $dir = dirname($dest);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $ch = curl_init($url);
    $fp = fopen($dest, 'wb');
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => array_merge([
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        ], $extra_headers),
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $code !== 200 || filesize($dest) < 500) {
        @unlink($dest);
        return false;
    }
    return true;
}

// ─── Turkish month → date parser ─────────────────────────────────────────────
function parse_turkish_date_range(string $text): array {
    $months = [
        'ocak' => 1, 'şubat' => 2, 'mart' => 3, 'nisan' => 4,
        'mayıs' => 5, 'haziran' => 6, 'temmuz' => 7, 'ağustos' => 8,
        'eylül' => 9, 'ekim' => 10, 'kasım' => 11, 'aralık' => 12,
    ];
    $now   = new DateTime();
    $year  = (int)$now->format('Y');
    $text  = mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)));

    $fmt = fn($y, $m, $d) => sprintf('%04d-%02d-%02d', $y, $m, $d);
    $add = fn($d, $n) => (new DateTime($d))->modify("+$n days")->format('Y-m-d');

    // Cross-month range e.g. "30 Mayıs - 5 Haziran"
    if (preg_match('/(\d+)\s+([a-zşğçıöü]+)\s*[-–]\s*(\d+)\s+([a-zşğçıöü]+)/', $text, $m)) {
        $sm = $months[$m[2]] ?? 0; $em = $months[$m[4]] ?? 0;
        if ($sm && $em) {
            return [
                'start' => $fmt($year, $sm, (int)$m[1]),
                'end'   => $fmt($year, $em, (int)$m[3]),
            ];
        }
    }

    // Same-month range e.g. "03-29 Haziran"
    if (preg_match('/(\d+)\s*[-–]\s*(\d+)\s+([a-zşğçıöü]+)/', $text, $m)) {
        $mo = $months[$m[3]] ?? 0;
        if ($mo) return ['start' => $fmt($year, $mo, (int)$m[1]), 'end' => $fmt($year, $mo, (int)$m[2])];
    }

    // Single date e.g. "25 Mayıs"
    if (preg_match('/(\d+)\s+([a-zşğçıöü]+)/', $text, $m)) {
        $mo = $months[$m[2]] ?? 0;
        if ($mo) {
            $start = $fmt($year, $mo, (int)$m[1]);
            return ['start' => $start, 'end' => $add($start, 7)];
        }
    }

    // Fallback: today → today+7
    $today = $now->format('Y-m-d');
    return ['start' => $today, 'end' => $add($today, 7)];
}

// ─── Resolve relative/protocol-relative URLs ─────────────────────────────────
function resolve_url(string $url, string $base): string {
    if (str_starts_with($url, 'http')) return $url;
    if (str_starts_with($url, '//')) return 'https:' . $url;
    $p = parse_url($base);
    $origin = $p['scheme'] . '://' . $p['host'];
    return $origin . '/' . ltrim($url, '/');
}

// ─── aktuelbrosurler.com iframe page extractor ───────────────────────────────
function fetch_aktuelbrosurler_pages(string $detail_url): array {
    $detail_html = curl_get($detail_url);
    if (!$detail_html) return [];

    // Try iframe pattern: brosur.aspx?id=xxxxx
    if (preg_match('/brosur\.aspx\?id=([a-f0-9]+)/i', $detail_html, $im)) {
        $iframe_url  = 'https://aktuelbrosurler.com/brosur.aspx?id=' . $im[1];
        $iframe_html = curl_get($iframe_url, ["Referer: $detail_url"]);
        $pages = [];
        preg_match_all("/'l':\s*'([^']+)'/", $iframe_html, $pm);
        foreach ($pm[1] as $img) {
            $img = str_replace('\\u0026', '&', $img);
            if ($img && !in_array($img, $pages)) $pages[] = $img;
        }
        if ($pages) return $pages;
    }

    // Fallback: look for .brosur-pages img or similar selectors
    preg_match_all('/<img[^>]+(?:src|data-src)=["\']([^"\']+(?:\.jpg|\.webp|\.png|\.jpeg)[^"\']*)["\'][^>]*>/i', $detail_html, $imgs);
    $pages = [];
    foreach ($imgs[1] as $src) {
        if (strpos($src, 'logo') === false && strpos($src, 'icon') === false) {
            $pages[] = $src;
        }
    }
    return array_values(array_unique($pages));
}

// ─── Main scraper ─────────────────────────────────────────────────────────────
function run_scraper(PDO $pdo): array {
    $uploads_dir = dirname(__DIR__) . '/uploads';
    $results = ['total_new' => 0, 'markets_processed' => 0, 'errors' => []];

    $markets = $pdo->query(
        "SELECT * FROM markets WHERE scraper_active = 1 AND scraper_url IS NOT NULL AND scraper_url != ''"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($markets)) {
        log_line('⚠️  Aktif scraper ayarı olan market bulunamadı.');
        return $results;
    }

    log_line("📋 " . count($markets) . " adet aktif market taranacak.\n");

    foreach ($markets as $market) {
        $results['markets_processed']++;
        $name   = $market['name'];
        $slug   = $market['slug'];
        $url    = $market['scraper_url'];

        log_line("🔍 [{$name}] Taranıyor: {$url}");

        $html = curl_get($url);
        if (!$html) {
            log_line("  ❌ Sayfa alınamadı.");
            $results['errors'][] = "$name: Sayfa alınamadı";
            continue;
        }

        // ── Parse brochure cards ──────────────────────────────────────────────
        // aktuelbrosurler.com structure: <a class="brosur-link" href="...">
        //   <div class="media-wrapper"><img src="..." /></div>
        //   <div class="excerpt"><p>TITLE</p></div>
        // </a>
        preg_match_all('/<a[^>]+class=["\'][^"\']*brosur-link[^"\']*["\'][^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $html, $cards, PREG_SET_ORDER);

        if (empty($cards)) {
            log_line("  ⚠️  Hiç broşür kartı bulunamadı (muhtemelen JS ile yükleniyor).");
            continue;
        }

        log_line("  📦 " . count($cards) . " broşür kartı bulundu.");
        $new_count = 0;
        $ts = time();

        foreach ($cards as $ci => $card) {
            $card_href = $card[1];
            $card_html = $card[2];

            // Extract title from .excerpt p
            $title = '';
            if (preg_match('/<p[^>]*>(.*?)<\/p>/si', $card_html, $tm)) {
                $title = trim(strip_tags($tm[1]));
            }
            if (!$title) $title = $name . ' Kataloğu';
            else $title = $name . ' ' . $title;

            // Parse dates from title
            $dates = parse_turkish_date_range($title);
            $start_date = $dates['start'];
            $end_date   = $dates['end'];

            // Extract cover image
            // aktuelbrosurler.com uses data-img attribute on .media-wrapper div (not <img>!)
            $cover_url = '';
            // 1st priority: data-img on the media-wrapper div
            if (preg_match('/class=["\'][^"\']*media-wrapper[^"\']*["\'][^>]+data-img=["\']([^"\']+)["\']/', $card_html, $im)) {
                $cover_url = trim($im[1]);
            } elseif (preg_match('/data-img=["\']([^"\']+)["\']/', $card_html, $im)) {
                $cover_url = trim($im[1]);
            }
            // 2nd priority: data-src on any img
            if (!$cover_url && preg_match('/<img[^>]+data-src=["\']([^"\']+)["\'][^>]*>/si', $card_html, $im)) {
                $cover_url = trim($im[1]);
            }
            // 3rd priority: src on any img (skip data: URIs and tiny placeholders)
            if (!$cover_url && preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/si', $card_html, $im)) {
                $src = trim($im[1]);
                if (!empty($src) && !str_starts_with($src, 'data:') && strlen($src) > 10) {
                    $cover_url = $src;
                }
            }

            // Resolve URLs
            $cover_url  = $cover_url ? resolve_url($cover_url, $url) : '';
            $detail_url = $card_href ? resolve_url($card_href, $url) : '';

            // Fallback: if still no cover, try to get first page from detail page
            $cover_from_logo = false;
            if (!$cover_url && $detail_url) {
                log_line("    ⚠️  [{$ci}] Kapak resmi yok, detay sayfasından deneniyor: {$detail_url}");
                $detail_pages = fetch_aktuelbrosurler_pages($detail_url);
                if (!empty($detail_pages)) {
                    $cover_url = resolve_url($detail_pages[0], $url);
                    log_line("    -> Detay sayfasından kapak alındı: {$cover_url}");
                }
            }

            // Fallback: use market logo as cover
            if (!$cover_url) {
                if (!empty($market['logo'])) {
                    $logo_src = dirname(__DIR__) . '/uploads/markets/' . $market['logo'];
                    if (file_exists($logo_src)) {
                        $cover_from_logo = true;
                        log_line("    ⚠️  [{$ci}] Kapak resmi yok, market logosu kullanılacak.");
                    } else {
                        log_line("    ⚠️  [{$ci}] Kapak resmi ve logo dosyası yok, atlanıyor.");
                        continue;
                    }
                } else {
                    log_line("    ⚠️  [{$ci}] Kapak resmi yok ve market logosu tanımlı değil, atlanıyor.");
                    continue;
                }
            }

            // Check for duplicate in DB
            $exist = $pdo->prepare("SELECT id FROM brochures WHERE market_id = ? AND title = ? AND start_date = ?");
            $exist->execute([$market['id'], $title, $start_date]);
            if ($exist->fetchColumn()) {
                log_line("    ↩ Zaten var: \"{$title}\" ({$start_date})");
                continue;
            }

            log_line("    🌟 Yeni broşür: \"{$title}\" ({$start_date} – {$end_date})");

            // ── Download cover image ─────────────────────────────────────────
            $cover_name = $slug . '_auto_' . $ci . '_cover_' . $ts . '.jpg';
            $cover_dest = $uploads_dir . '/brochures/' . $cover_name;

            if ($cover_from_logo) {
                // Copy market logo as cover
                $logo_src = dirname(__DIR__) . '/uploads/markets/' . $market['logo'];
                if (!is_dir($uploads_dir . '/brochures')) mkdir($uploads_dir . '/brochures', 0755, true);
                if (!copy($logo_src, $cover_dest)) {
                    log_line("    ❌ Logo kopyalanamadı: {$logo_src}");
                    continue;
                }
                log_line("    -> Market logosu kapak olarak kopyalandı.");
            } elseif (!download_image($cover_url, $cover_dest)) {
                log_line("    ❌ Kapak indirilemedi: {$cover_url}");
                continue;
            }

            // ── Insert brochure record ────────────────────────────────────────
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO brochures (market_id, title, cover_image, start_date, end_date) VALUES (?, ?, ?, ?, ?)"
                );
                $ins->execute([$market['id'], $title, $cover_name, $start_date, $end_date]);
                $brochure_id = (int)$pdo->lastInsertId();
            } catch (PDOException $e) {
                log_line("    ❌ DB kayıt hatası: " . $e->getMessage());
                @unlink($cover_dest);
                continue;
            }

            // ── Fetch & download detail pages ─────────────────────────────────
            $page_images = [];
            if ($detail_url) {
                $page_images = fetch_aktuelbrosurler_pages($detail_url);
            }
            if (empty($page_images)) {
                $page_images = [$cover_url];
            }

            log_line("    📄 " . count($page_images) . " sayfa indiriliyor...");
            foreach ($page_images as $pnum => $page_url) {
                $p = $pnum + 1;
                $page_name = $slug . '_auto_' . $ci . '_p' . $p . '_' . $ts . '.jpg';
                $page_dest = $uploads_dir . '/brochures/pages/' . $page_name;

                if (download_image($page_url, $page_dest)) {
                    $pdo->prepare(
                        "INSERT INTO brochure_pages (brochure_id, page_number, image_path) VALUES (?, ?, ?)"
                    )->execute([$brochure_id, $p, $page_name]);
                } else {
                    log_line("    ⚠️  Sayfa {$p} indirilemedi.");
                }
            }

            log_line("    ✅ Kaydedildi. ID: {$brochure_id}");
            $new_count++;
            $results['total_new']++;
        }

        log_line("  [{$name}] Tamamlandı. {$new_count} yeni broşür eklendi.\n");
    }

    return $results;
}

// ─── Entry point ──────────────────────────────────────────────────────────────
log_line('====================================================');
log_line('⏰ Broşür Otomatik Kazıcı Başlatıldı: ' . date('d.m.Y H:i:s'));
log_line('====================================================');
log_line('');

// Record start time in settings table for "last run" tracking
try {
    $pdo->prepare("INSERT INTO settings (key_name, value_text) VALUES ('scraper_last_run', ?)
        ON DUPLICATE KEY UPDATE value_text = ?")->execute([date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
} catch (PDOException $e) {
    // SQLite fallback
    try {
        $pdo->prepare("INSERT OR REPLACE INTO settings (key_name, value_text) VALUES ('scraper_last_run', ?)")
            ->execute([date('Y-m-d H:i:s')]);
    } catch (PDOException $e2) {}
}

$results = run_scraper($pdo);

$elapsed = round(microtime(true) - SCRAPER_START, 2);
log_line('====================================================');
log_line("✅ Tamamlandı!");
log_line("   Taranan Market: " . $results['markets_processed']);
log_line("   Eklenen Broşür: " . $results['total_new']);
if (!empty($results['errors'])) {
    log_line("   Hatalar: " . count($results['errors']));
    foreach ($results['errors'] as $err) log_line("     - $err");
}
log_line("   Süre: {$elapsed} saniye");
log_line('====================================================');

// Record result in settings
try {
    $summary = "Taranan: {$results['markets_processed']}, Eklenen: {$results['total_new']}, Süre: {$elapsed}s";
    $pdo->prepare("INSERT INTO settings (key_name, value_text) VALUES ('scraper_last_result', ?)
        ON DUPLICATE KEY UPDATE value_text = ?")->execute([$summary, $summary]);
} catch (PDOException $e) {
    try {
        $pdo->prepare("INSERT OR REPLACE INTO settings (key_name, value_text) VALUES ('scraper_last_result', ?)")
            ->execute([$summary]);
    } catch (PDOException $e2) {}
}

if ($is_cli) exit(0);
