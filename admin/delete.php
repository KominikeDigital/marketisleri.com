<?php require '../config.php';
if(!isset($_SESSION['admin'])) die("Yetkisiz");
$id = $_GET['id'];
$type = $_GET['type'];

if($type == 'market') $pdo->prepare("DELETE FROM markets WHERE id = ?")->execute([$id]);
if($type == 'brochure') {
    $pdo->prepare("DELETE FROM brochure_pages WHERE brochure_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM brochures WHERE id = ?")->execute([$id]);
}
header("Location: " . ($type == 'market' ? 'markets.php' : 'brochures.php'));
?>