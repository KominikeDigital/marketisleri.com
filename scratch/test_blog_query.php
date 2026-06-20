<?php
require 'config.php';

$search_query = '';
$current_page = 1;
$posts_per_page = 9;
$offset = 0;

$params = [];
$where_clause = "";

$select_query = "SELECT * FROM blog_posts $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$select_stmt = $pdo->prepare($select_query);
$select_stmt->bindValue(1, (int)$posts_per_page, PDO::PARAM_INT);
$select_stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
$select_stmt->execute();
$results_bound = $select_stmt->fetchAll();

echo "Results with bound parameters: " . count($results_bound) . "\n";

$select_query_raw = "SELECT * FROM blog_posts $where_clause ORDER BY created_at DESC LIMIT $posts_per_page OFFSET $offset";
$select_stmt_raw = $pdo->query($select_query_raw);
$results_raw = $select_stmt_raw->fetchAll();

echo "Results with concatenated parameters: " . count($results_raw) . "\n";
