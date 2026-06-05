<?php require '../config.php'; if(!isset($_SESSION['admin'])) header("Location: login.php");
if(isset($_POST['add'])){
    $stmt = $pdo->prepare("INSERT INTO markets (name, slug, logo, description, category_id) VALUES (?,?,?,?,?)");
    $stmt->execute([$_POST['name'], $_POST['slug'], $_POST['logo'], $_POST['description'], $_POST['category_id']]);
    header("Location: markets.php");
}
?>
<!DOCTYPE html>
<html lang="tr"><head><script src="https://cdn.tailwindcss.com"></script><title>Marketler</title></head>
<body class="bg-gray-50 flex">
    <div class="w-64 bg-gray-900 h-screen text-white p-6 fixed">
        <nav class="space-y-4">
            <a href="index.php" class="block p-2 hover:bg-gray-800 rounded">Dashboard</a>
            <a href="markets.php" class="block p-2 bg-gray-800 rounded">Marketler</a>
            <a href="brochures.php" class="block p-2 hover:bg-gray-800 rounded">Broşürler</a>
            <a href="logout.php" class="block p-2 text-red-400">Çıkış</a>
        </nav>
    </div>
    <main class="ml-64 p-10 w-full">
        <div class="flex justify-between mb-6">
            <h2 class="text-3xl font-bold">Market Yönetimi</h2>
            <button onclick="document.getElementById('modal').classList.remove('hidden')" class="bg-red-600 text-white px-4 py-2 rounded-lg font-bold">+ Market Ekle</button>
        </div>
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-100"><tr><th class="p-4">Market</th><th class="p-4">Slug</th><th class="p-4">İşlem</th></tr></thead>
                <tbody>
                    <?php $stmt = $pdo->query("SELECT * FROM markets"); while($row = $stmt->fetch()): ?>
                    <tr class="border-t"><td class="p-4"><?= $row['name'] ?></td><td class="p-4"><?= $row['slug'] ?></td><td class="p-4"><a href="delete.php?type=market&id=<?= $row['id'] ?>" class="text-red-500">Sil</a></td></tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
    <div id="modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center">
        <form method="POST" class="bg-white p-8 rounded-2xl w-96">
            <h3 class="text-xl font-bold mb-4">Yeni Market</h3>
            <input type="text" name="name" placeholder="Market Adı" class="w-full p-2 border rounded mb-3" required>
            <input type="text" name="slug" placeholder="market-url-adi" class="w-full p-2 border rounded mb-3" required>
            <input type="text" name="logo" placeholder="Logo Dosya Adı (Örn: bim.png)" class="w-full p-2 border rounded mb-3">
            <textarea name="description" placeholder="Açıklama" class="w-full p-2 border rounded mb-3"></textarea>
            <select name="category_id" class="w-full p-2 border rounded mb-4">
                <?php $cats = $pdo->query("SELECT * FROM categories"); while($c = $cats->fetch()) echo "<option value='{$c['id']}'>{$c['name']}</option>"; ?>
            </select>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="px-4 py-2">İptal</button>
                <button name="add" class="bg-red-600 text-white px-4 py-2 rounded-lg">Kaydet</button>
            </div>
        </form>
    </div>
</body></html>