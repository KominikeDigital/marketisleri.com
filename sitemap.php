<?php
require 'config.php';

// Set content type to XML
header("Content-Type: application/xml; charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Homepage -->
    <url>
        <loc><?= htmlspecialchars($site_url) ?>/</loc>
        <priority>1.0</priority>
        <changefreq>daily</changefreq>
    </url>

    <!-- Static Pages -->
    <url>
        <loc><?= htmlspecialchars($site_url) ?>/gizlilik-politikasi.php</loc>
        <priority>0.5</priority>
        <changefreq>monthly</changefreq>
    </url>
    <url>
        <loc><?= htmlspecialchars($site_url) ?>/kullanim-kosullari.php</loc>
        <priority>0.5</priority>
        <changefreq>monthly</changefreq>
    </url>
    <url>
        <loc><?= htmlspecialchars($site_url) ?>/cerez-politikasi.php</loc>
        <priority>0.5</priority>
        <changefreq>monthly</changefreq>
    </url>

    <!-- Dynamic Market Pages -->
    <?php
    try {
        $markets_stmt = $pdo->query("SELECT id FROM markets");
        while ($market = $markets_stmt->fetch()) {
            ?>
            <url>
                <loc><?= htmlspecialchars($site_url) ?>/index.php?market=<?= $market['id'] ?></loc>
                <priority>0.7</priority>
                <changefreq>daily</changefreq>
            </url>
            <?php
        }
    } catch (PDOException $e) {
        // Fail silently
    }
    ?>

    <!-- Dynamic Brochure Viewer Pages -->
    <?php
    try {
        $brochures_stmt = $pdo->query("SELECT id, created_at FROM brochures WHERE show_on_homepage = 1 ORDER BY created_at DESC");
        while ($brochure = $brochures_stmt->fetch()) {
            $lastmod = !empty($brochure['created_at']) ? date('Y-m-d', strtotime($brochure['created_at'])) : date('Y-m-d');
            ?>
            <url>
                <loc><?= htmlspecialchars($site_url) ?>/viewer.php?id=<?= $brochure['id'] ?></loc>
                <lastmod><?= $lastmod ?></lastmod>
                <priority>0.8</priority>
                <changefreq>weekly</changefreq>
            </url>
            <?php
        }
    } catch (PDOException $e) {
        // Fail silently
    }
    ?>
</urlset>
