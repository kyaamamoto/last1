<?php
session_start();
require_once 'funcs.php';

// CSRFトークンの検証
validateToken($_POST['csrf_token'] ?? '');

$lid = sanitize($_POST["lid"] ?? '');
$lpw = $_POST["lpw"] ?? ''; // パスワードはサニタイズしない

// 入力チェック
if (empty($lid) || empty($lpw)) {
    $_SESSION['login_error'] = "ログインIDとパスワードを入力してください。";
    redirect('login.php');
}

try {
    $pdo = db_conn();
    
    // ユーザー検索
    $stmt = $pdo->prepare("SELECT * FROM user_table WHERE lid = :lid");
    $stmt->bindValue(':lid', $lid, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ユーザーが存在し、パスワードが一致する場合
    if ($user && password_verify($lpw, $user['lpw'])) {
        // セッションハイジャック対策
        session_regenerate_id(true);
        
        // ログイン情報をセッションに保存
        $_SESSION['chk_ssid'] = session_id();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['kanri_flg'] = $user['kanri_flg'];

        // ログイン成功時のリダイレクト
        redirect('cms.php');  // ここをselect.phpからcms.phpに変更
    } else {
        // ログイン失敗
        $_SESSION['login_error'] = "ログインIDまたはパスワードが間違っています。";
        redirect('login.php');
    }
} catch (PDOException $e) {
    error_log("ログインエラー: " . $e->getMessage());
    $_SESSION['login_error'] = "ログイン中にエラーが発生しました。管理者にお問い合わせください。";
    redirect('login.php');
}
?>