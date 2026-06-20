<?php
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = $_GET['type'] ?? '';

if ($id > 0 && ($type === 'market' || $type === 'brochure' || $type === 'blog')) {
    try {
        $pdo->beginTransaction();

        if ($type === 'market') {
            $m_stmt = $pdo->prepare("SELECT logo FROM markets WHERE id = ?");
            $m_stmt->execute([$id]);
            $logo = $m_stmt->fetchColumn();

            $b_stmt = $pdo->prepare("SELECT id, cover_image, pdf_path FROM brochures WHERE market_id = ?");
            $b_stmt->execute([$id]);
            $brochures = $b_stmt->fetchAll();

            foreach ($brochures as $b) {
                $pages_stmt = $pdo->prepare("SELECT image_path FROM brochure_pages WHERE brochure_id = ?");
                $pages_stmt->execute([$b['id']]);
                $b['pages'] = $pages_stmt->fetchAll(PDO::FETCH_COLUMN);

                $pdo->prepare("DELETE FROM brochure_products WHERE brochure_id = ?")->execute([$b['id']]);
                $pdo->prepare("DELETE FROM brochure_pages WHERE brochure_id = ?")->execute([$b['id']]);
                $pdo->prepare("DELETE FROM brochures WHERE id = ?")->execute([$b['id']]);

                if (!empty($b['cover_image']) && file_exists('../uploads/brochures/' . $b['cover_image'])) {
                    @unlink('../uploads/brochures/' . $b['cover_image']);
                }
                if (!empty($b['pdf_path']) && file_exists('../uploads/brochures/pdfs/' . $b['pdf_path'])) {
                    @unlink('../uploads/brochures/pdfs/' . $b['pdf_path']);
                }
                foreach ($b['pages'] as $img) {
                    if (!empty($img) && file_exists('../uploads/brochures/pages/' . $img)) {
                        @unlink('../uploads/brochures/pages/' . $img);
                    }
                }
            }

            $pdo->prepare("UPDATE price_alerts SET market_id = NULL WHERE market_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM markets WHERE id = ?")->execute([$id]);

            if (!empty($logo) && file_exists('../uploads/markets/' . $logo)) {
                @unlink('../uploads/markets/' . $logo);
            }

            $_SESSION['flash_success'] = "Market ve ilişkili broşürleri silindi.";

        } elseif ($type === 'brochure') {
            $b_stmt = $pdo->prepare("SELECT cover_image, pdf_path FROM brochures WHERE id = ?");
            $b_stmt->execute([$id]);
            $b = $b_stmt->fetch();

            if ($b) {
                $pages_stmt = $pdo->prepare("SELECT image_path FROM brochure_pages WHERE brochure_id = ?");
                $pages_stmt->execute([$id]);
                $pages = $pages_stmt->fetchAll(PDO::FETCH_COLUMN);

                $pdo->prepare("DELETE FROM brochure_products WHERE brochure_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM brochure_pages WHERE brochure_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM brochures WHERE id = ?")->execute([$id]);

                if (!empty($b['cover_image']) && file_exists('../uploads/brochures/' . $b['cover_image'])) {
                    @unlink('../uploads/brochures/' . $b['cover_image']);
                }
                if (!empty($b['pdf_path']) && file_exists('../uploads/brochures/pdfs/' . $b['pdf_path'])) {
                    @unlink('../uploads/brochures/pdfs/' . $b['pdf_path']);
                }
                foreach ($pages as $img) {
                    if (!empty($img) && file_exists('../uploads/brochures/pages/' . $img)) {
                        @unlink('../uploads/brochures/pages/' . $img);
                    }
                }
            }

            $_SESSION['flash_success'] = "Broşür ve analiz ürünleri silindi.";

        } elseif ($type === 'blog') {
            $blog_stmt = $pdo->prepare("SELECT cover_image FROM blog_posts WHERE id = ?");
            $blog_stmt->execute([$id]);
            $cover = $blog_stmt->fetchColumn();

            $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);

            if (!empty($cover) && $cover !== 'uploads/blog_cover_default.png' && file_exists('../' . $cover)) {
                @unlink('../' . $cover);
            }

            $_SESSION['flash_success'] = "Blog yazısı silindi.";
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_error'] = "Silme işlemi tamamlanamadı: " . $e->getMessage();
    }
}

$redirect = 'index.php';
if ($type === 'market') {
    $redirect = 'markets.php';
} elseif ($type === 'brochure') {
    $redirect = 'brochures.php';
} elseif ($type === 'blog') {
    $redirect = 'blogs.php';
}
header("Location: " . $redirect);
exit;
?>
