<?php
require_once 'admin_session_config.php';
require_once 'funcs.php';

// CSRFトークンの検証
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    exit('不正なリクエストです。');
}

$lid = $_POST["lid"];
$lpw = $_POST["lpw"];

// 入力チェック（省略）

try {
    $pdo = db_conn();
    // ユーザー情報を取得する
    $stmt = $pdo->prepare("SELECT * FROM user_table WHERE lid = :lid");
    $stmt->bindValue(':lid', $lid, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // パスワードの確認
    if ($user && password_verify($lpw, $user['lpw'])) {
        $_SESSION['admin_auth'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['kanri_flg'] = $user['kanri_flg'];
        
        // 管理者の場合
        if ($user['kanri_flg'] == 1) {
            header("Location: cms.php"); // 管理者のダッシュボードへ
        } else {
            header("Location: cms.php?view=content_management"); // 一般ユーザーのダッシュボードへ
        }
        exit();
    } else {
        $_SESSION['login_error'] = 'ログインに失敗しました。';
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    exit('データベースエラー');
}