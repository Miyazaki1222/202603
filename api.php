<?php
$dsn = 'mysql:host=localhost;dbname=baselog_202603;charset=utf8';
// 接続エラーを表示するように設定
try {
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'input') {
    $type = $_GET['type'] ?? '';
    // is_cancelled を明示的に 0 でインサート
    $stmt = $pdo->prepare("INSERT INTO judgments (judge_type, is_cancelled) VALUES (?, 0)");
    $stmt->execute([$type]);
    updateCounts($pdo); 
} 

elseif ($action === 'cancel') {
    // 最新の「まだ取り消されていない判定」を1件取り消す（論理削除）
    $pdo->query("UPDATE judgments SET is_cancelled = 1 WHERE is_cancelled = 0 ORDER BY id DESC LIMIT 1");
    updateCounts($pdo);
}

elseif ($action === 'next_batter') {
    // 【新規】BSリセット用のログを挿入
    $stmt = $pdo->prepare("INSERT INTO judgments (judge_type, is_cancelled) VALUES ('ResetBS', 0)");
    $stmt->execute();
    updateCounts($pdo);
}

elseif ($action === 'request') {
    // 最新の判定に対してリクエストフラグを立てる
    $pdo->query("UPDATE judgments SET requested_change = 1 WHERE is_cancelled = 0 ORDER BY id DESC LIMIT 1");
}

elseif ($action === 'reset') {
    // 全リセット（全データをキャンセル扱いにし、カウントを0にする）
    $pdo->query("UPDATE judgments SET is_cancelled = 1");
    updateCounts($pdo);
}

// --- カウント再計算関数 ---
function updateCounts($pdo) {
    // 有効なログのみを取得
    $stmt = $pdo->query("SELECT judge_type FROM judgments WHERE is_cancelled = 0 ORDER BY id ASC");
    $logs = $stmt->fetchAll();

    $b = 0; $s = 0; $o = 0;

    foreach ($logs as $log) {
        $type = $log['judge_type'];

        if ($type === 'Ball') {
            $b++;
            if ($b >= 4) { $b = 0; $s = 0; } // 四球
        } 
        elseif ($type === 'Strike') {
            $s++;
            if ($s >= 3) { $b = 0; $s = 0; $o++; } // 三振
        } 
        elseif ($type === 'Foul') {
            if ($s < 2) { $s++; }
        }
        elseif ($type === 'Out') {
            $o++;
            $b = 0; $s = 0; // 凡退
        }
        elseif ($type === 'ResetBS') {
            // 【重要】ここでボール・ストライクのみをクリア
            $b = 0; $s = 0; 
        }

        // 3アウトでチェンジ
        if ($o >= 3) { $b = 0; $s = 0; $o = 0; }
    }

    // 計算結果を反映
    $stmt = $pdo->prepare("UPDATE game_counts SET ball = ?, strike = ?, outs = ? WHERE id = 1");
    $stmt->execute([$b, $s, $o]);
}

echo json_encode(['status' => 'success']);