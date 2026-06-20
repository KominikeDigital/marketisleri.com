<?php
/**
 * Blog Posts Seeder Script
 * Creates 100 high-quality, smart-shopping focused articles in Turkish.
 */

// Load database configuration
require_once dirname(__DIR__) . '/config.php';

// Set script execution time limit to unlimited
set_time_limit(0);

echo "Starting blog seeder...\n";

// Clear existing posts
try {
    $pdo->exec("DELETE FROM blog_posts");
    // Reset SQLite autoincrement sequence if applicable
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='blog_posts'");
} catch (PDOException $e) {
    // Fail silently (sequence table might not exist in MySQL or sqlite depending on driver)
}

// Load default 100 blog posts from lib/default_blogs.php
require_once dirname(__DIR__) . '/lib/default_blogs.php';
$posts = $seeded_posts;

// Default cover image path relative to the application base or local assets
$default_cover = "uploads/blog_cover_default.png";

// Insert into Database
$stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, summary, cover_image, created_at) VALUES (?, ?, ?, ?, ?, ?)");

$inserted = 0;
$days_offset = 100; // scatter created_at dates over 100 days to look authentic

function createSlug($string) {
    $replace = array(
        '?' => '', '*' => '', '!' => '', '#' => '', '$' => '', '%' => '', '&' => '', '(' => '', ')' => '', '=' => '',
        '/' => '', '\\' => '', '|' => '', '{' => '', '}' => '', '[' => '', ']' => '', ':' => '', ';' => '', ',' => '',
        '.' => '', '<' => '', '>' => '', '"' => '', '\'' => '', '`' => '', '~' => '', '^' => '', '+' => '',
        'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'I' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o',
        'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u', ' ' => '-', '--' => '-', '---' => '-'
    );
    $string = str_replace(array_keys($replace), array_values($replace), $string);
    $string = strtolower(trim($string, '-'));
    return preg_replace('/-+/', '-', $string);
}

foreach ($posts as $idx => $p) {
    $slug = createSlug($p['title']);
    
    // Ensure slug uniqueness (append counter if needed)
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ?");
    $check_stmt->execute([$slug]);
    if ($check_stmt->fetchColumn() > 0) {
        $slug .= "-" . ($idx + 1);
    }
    
    $created_at = date('Y-m-d H:i:s', strtotime("-$days_offset days +" . ($idx * 24) . " hours"));
    
    try {
        $stmt->execute([
            $p['title'],
            $slug,
            $p['content'],
            $p['summary'],
            $default_cover,
            $created_at
        ]);
        $inserted++;
    } catch (PDOException $e) {
        echo "Error inserting post: " . $p['title'] . " - " . $e->getMessage() . "\n";
    }
}

echo "Successfully seeded $inserted / 100 blog posts!\n";
