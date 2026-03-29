<?php
header('Content-Type: application/json');
$pdo = new PDO('mysql:host=localhost;dbname=baselog_202603;charset=utf8', 'root', '');

$counts = $pdo->query("SELECT * FROM game_counts WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$latest = $pdo->query("SELECT * FROM judgments ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'counts' => $counts,
    'latest' => $latest ?: ['id' => 0, 'is_cancelled' => 0, 'requested_change' => 0]
]);