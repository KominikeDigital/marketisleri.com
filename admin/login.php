<?php require '../config.php';
if (isset($_POST['login'])) {
    if ($_POST['user'] == $admin_user && $_POST['pass'] == $admin_pass) {
        $_SESSION['admin'] = true;
        header("Location: index.php");
    } else { $error = "Hatalı giriş!"; }
}
?>
<!DOCTYPE html>
<html lang="tr"><head><script src="https://cdn.tailwindcss.com"></script><title>Admin Giriş</title></head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <form method="POST" class="bg-white p-8 rounded-xl shadow-lg w-96">
        <h2 class="text-2xl font-bold mb-6 text-center">Admin Girişi</h2>
        <?php if(isset($error)) echo "<p class='text-red-500 mb-4 text-center'>$error</p>"; ?>
        <input type="text" name="user" placeholder="Kullanıcı" class="w-full p-3 border rounded mb-4" required>
        <input type="password" name="pass" placeholder="Şifre" class="w-full p-3 border rounded mb-6" required>
        <button name="login" class="w-full bg-red-600 text-white p-3 rounded-lg font-bold">Giriş Yap</button>
    </form>
</body></html>