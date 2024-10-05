<?php
session_start();
require_once 'funcs.php';

// CSRFトークンの検証
validateToken($_POST['csrf_token'] ?? '');

$email = filter_var($_POST["email"] ?? '', FILTER_SANITIZE_EMAIL);
$lpw = $_POST["lpw"] ?? '';

// 入力チェック
if (empty($email) || empty($lpw)) {
    $_SESSION['login_error'] = "メールアドレスとパスワードを入力してください。";
    redirect('login_holder.php');
}

try {
    $pdo = db_conn();
    
    // ユーザー検索
    $stmt = $pdo->prepare("SELECT * FROM holder_table WHERE email = :email");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
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

        // ログイン成功時のリダイレクト
        redirect('education.php');
    } else {
        // ログイン失敗
        $_SESSION['login_error'] = "メールアドレスまたはパスワードが間違っています。";
        redirect('login_holder.php');
    }
} catch (PDOException $e) {
    error_log("ログインエラー: " . $e->getMessage());
    $_SESSION['login_error'] = "ログイン中にエラーが発生しました。管理者にお問い合わせください。";
    redirect('login_holder.php');
}
?>