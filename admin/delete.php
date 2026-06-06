<?php
require '../config.php';

// Authentication Check
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = $_GET['type'] ?? '';

if ($id > 0 && ($type === 'market' || $type === 'brochure')) {
    
    if ($type === 'market') {
        // 1. Fetch market details to delete logo file
        $m_stmt = $pdo->prepare("SELECT logo FROM markets WHERE id = ?");
        $m_stmt->execute([$id]);
        $logo = $m_stmt->fetchColumn();
        
        if (!empty($logo) && file_exists('../uploads/markets/' . $logo)) {
            @unlink('../uploads/markets/' . $logo);
        }
        
        // 2. Fetch all brochures of this market to clean up their assets
        $b_stmt = $pdo->prepare("SELECT id, cover_image, pdf_path FROM brochures WHERE market_id = ?");
        $b_stmt->execute([$id]);
        $brochures = $b_stmt->fetchAll();
        
        foreach ($brochures as $b) {
            $b_id = $b['id'];
            
            // Delete cover
            if (!empty($b['cover_image']) && file_exists('../uploads/brochures/' . $b['cover_image'])) {
                @unlink('../uploads/brochures/' . $b['cover_image']);
            }
            
            // Delete PDF
            if (!empty($b['pdf_path']) && file_exists('../uploads/brochures/pdfs/' . $b['pdf_path'])) {
                @unlink('../uploads/brochures/pdfs/' . $b['pdf_path']);
            }
            
            // Delete pages
            $pages_stmt = $pdo->prepare("SELECT image_path FROM brochure_pages WHERE brochure_id = ?");
            $pages_stmt->execute([$b_id]);
            while ($img = $pages_stmt->fetchColumn()) {
                if (!empty($img) && file_exists('../uploads/brochures/pages/' . $img)) {
                    @unlink('../uploads/brochures/pages/' . $img);
                }
            }
        }
        
        // 3. Delete from DB (Foreign keys ON DELETE CASCADE will handle cascading in SQLite/MySQL if configured, 
        // but let's do it explicitly to guarantee compatibility in all setups)
        foreach ($brochures as $b) {
            $pdo->prepare("DELETE FROM brochure_pages WHERE brochure_id = ?")->execute([$b['id']]);
            $pdo->prepare("DELETE FROM brochures WHERE id = ?")->execute([$b['id']]);
        }
        
        $pdo->prepare("DELETE FROM markets WHERE id = ?")->execute([$id]);
        
    } elseif ($type === 'brochure') {
        // 1. Fetch brochure details
        $b_stmt = $pdo->prepare("SELECT cover_image, pdf_path FROM brochures WHERE id = ?");
        $b_stmt->execute([$id]);
        $b = $b_stmt->fetch();
        
        if ($b) {
            // Delete cover
            if (!empty($b['cover_image']) && file_exists('../uploads/brochures/' . $b['cover_image'])) {
                @unlink('../uploads/brochures/' . $b['cover_image']);
            }
            
            // Delete PDF
            if (!empty($b['pdf_path']) && file_exists('../uploads/brochures/pdfs/' . $b['pdf_path'])) {
                @unlink('../uploads/brochures/pdfs/' . $b['pdf_path']);
            }
            
            // Delete pages
            $pages_stmt = $pdo->prepare("SELECT image_path FROM brochure_pages WHERE brochure_id = ?");
            $pages_stmt->execute([$id]);
            while ($img = $pages_stmt->fetchColumn()) {
                if (!empty($img) && file_exists('../uploads/brochures/pages/' . $img)) {
                    @unlink('../uploads/brochures/pages/' . $img);
                }
            }
            
            // 2. Delete from DB
            $pdo->prepare("DELETE FROM brochure_pages WHERE brochure_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM brochures WHERE id = ?")->execute([$id]);
        }
    }
}

$redirect = ($type === 'market') ? 'markets.php' : 'brochures.php';
header("Location: " . $redirect);
exit;
?>