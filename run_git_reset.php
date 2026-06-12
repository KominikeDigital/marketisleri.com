<?php
// run_git_reset.php - Multi-method Git Reset
// Bu dosya sunucudaki Git index çakışmalarını temizlemek için farklı çalıştırma metotlarını dener.

header("Content-Type: text/html; charset=utf-8");
echo "<h2>Git Reset Denetleyici & Çözücü</h2>";

$git_path = "/usr/local/cpanel/3rdparty/bin/git";

$commands = [
    "$git_path config core.fileMode false",
    "$git_path config core.autocrlf false",
    "$git_path reset --hard origin/main",
    "$git_path clean -fd",
    "$git_path status"
];

function try_exec($cmd) {
    echo "<h3 style='border-bottom: 2px solid #cbd5e1; padding-bottom: 4px; color: #1e293b;'>Çalıştırılıyor: <code>" . htmlspecialchars($cmd) . "</code></h3>";
    
    // Method 1: shell_exec
    if (function_exists('shell_exec')) {
        echo "<b>shell_exec ile deneniyor... </b>";
        $out = @shell_exec("$cmd 2>&1");
        if ($out !== null) {
            echo "<span style='color:green;'>Başarılı!</span>";
            echo "<pre style='background:#f1f5f9; padding:8px; border-radius:4px; font-family:monospace;'>" . htmlspecialchars((string)$out) . "</pre>";
            return true;
        } else {
            echo "<span style='color:red;'>Null döndü.</span><br>";
        }
    }
    
    // Method 2: exec
    if (function_exists('exec')) {
        echo "<b>exec ile deneniyor... </b>";
        $output = [];
        $result_code = -1;
        @exec("$cmd 2>&1", $output, $result_code);
        if ($result_code === 0 || count($output) > 0) {
            echo "<span style='color:green;'>Başarılı (Kod: $result_code)!</span>";
            echo "<pre style='background:#f1f5f9; padding:8px; border-radius:4px; font-family:monospace;'>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
            return true;
        } else {
            echo "<span style='color:red;'>Başarısız.</span><br>";
        }
    }
    
    // Method 3: system
    if (function_exists('system')) {
        echo "<b>system ile deneniyor... </b>";
        ob_start();
        $res = @system("$cmd 2>&1");
        $out = ob_get_clean();
        if ($out !== false && strlen($out) > 0) {
            echo "<span style='color:green;'>Başarılı!</span>";
            echo "<pre style='background:#f1f5f9; padding:8px; border-radius:4px; font-family:monospace;'>" . htmlspecialchars($out) . "</pre>";
            return true;
        } else {
            echo "<span style='color:red;'>Başarısız.</span><br>";
        }
    }
    
    // Method 4: passthru
    if (function_exists('passthru')) {
        echo "<b>passthru ile deneniyor... </b>";
        ob_start();
        @passthru("$cmd 2>&1");
        $out = ob_get_clean();
        if ($out !== false && strlen($out) > 0) {
            echo "<span style='color:green;'>Başarılı!</span>";
            echo "<pre style='background:#f1f5f9; padding:8px; border-radius:4px; font-family:monospace;'>" . htmlspecialchars($out) . "</pre>";
            return true;
        } else {
            echo "<span style='color:red;'>Başarısız.</span><br>";
        }
    }
    
    // Method 5: proc_open
    if (function_exists('proc_open')) {
        echo "<b>proc_open ile deneniyor... </b>";
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        $process = @proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            if (strlen($out) > 0 || strlen($err) > 0) {
                echo "<span style='color:green;'>Başarılı!</span>";
                echo "<pre style='background:#f1f5f9; padding:8px; border-radius:4px; font-family:monospace;'>Stdout: " . htmlspecialchars($out) . "\nStderr: " . htmlspecialchars($err) . "</pre>";
                return true;
            }
        }
        echo "<span style='color:red;'>Başarısız.</span><br>";
    }
    
    echo "<span style='color:red; font-weight:bold;'>Yöntemlerin hiçbiri bu komut için çıktı üretemedi!</span><br>";
    return false;
}

foreach ($commands as $cmd) {
    try_exec($cmd);
}

echo "<h3 style='color: green;'>İşlemler tamamlandı. Lütfen cPanel Git sayfasından pull yapmayı tekrar deneyin.</h3>";
echo "<p>Eğer tüm yöntemler başarısız olduysa, cPanel ana sayfasındaki <b>Terminal</b> aracını açıp şu komutları sırasıyla yazarak sıfırlayabilirsiniz:</p>";
echo "<pre style='background:#0f172a; color:#f1f5f9; padding:12px; border-radius:8px; font-family:monospace;'>cd public_html\ngit config core.fileMode false\ngit config core.autocrlf false\ngit reset --hard origin/main</pre>";
