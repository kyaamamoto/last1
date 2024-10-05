<?php
// エラー表示（開発時のみ有効にし、本番環境では無効にすること）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// セッション設定
ini_set('session.cookie_lifetime', 0);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // HTTPSの場合のみ使用
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.save_path', '/path/to/secure/session/storage'); // 適切なパスに変更すること

// セッション開始
session_start();

// CSRF対策
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// デバッグ情報の出力（開発時のみ使用し、本番環境では削除すること）
function debugInfo() {
    echo "<pre>";
    var_dump($_SESSION);
    echo "session.save_path: " . ini_get('session.save_path') . "\n";
    echo "session.name: " . ini_get('session.name') . "\n";
    echo "session.gc_maxlifetime: " . ini_get('session.gc_maxlifetime') . "\n";
    echo "Current Session ID: " . session_id() . "\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
    echo "</pre>";
}

// デバッグ情報を表示（必要に応じてコメントアウト）
// debugInfo();

// セッションにユーザーIDが存在しない場合、ログイン画面にリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: holder.php');
    exit();
}

// XSS対策
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ユーザー情報の取得（実際のデータベース接続と照会が必要）
function getUserInfo($userId) {
    // ここでデータベースからユーザー情報を取得する
    // 例: return $db->query("SELECT * FROM users WHERE id = ?", [$userId])->fetch();
    return ['name' => 'テストユーザー']; // ダミーデータ
}

$userInfo = getUserInfo($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登録完了</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>登録が完了しました</h2>
    <p>ようこそ、<?= h($userInfo['name']) ?>さん。データは正常に登録されました。</p>
    
    <p>
        あなたの登録情報に基づいたレポートを作成しました。<br>
        <a href="generate_report.php">レポートを見る</a>
    </p>

    <form action="logout.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <button type="submit">ログアウト</button>
    </form>

    <p><a href="dashboard.php">ダッシュボードへ</a></p>
</body>
</html>