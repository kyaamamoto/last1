<?php
// セッションを開始
session_start();

// フォームのデータをセッションに保存
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['form'] = $_POST;
}

// CSRFトークンの生成
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$form = $_SESSION['form'];
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>確認ページ</title>
    <style>
        body {
            font-family: 'Lato', sans-serif;
            background-color: #fff;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .confirm-container {
            max-width: 800px;
            margin: 20px;
            padding: 40px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #ffffff;
        }
        .confirm-container h2 {
            margin-bottom: 20px;
            font-size: 36px;
            color: #0c344e;
            letter-spacing: 2px;
        }
        .confirm-container p {
            font-size: 16px;
            margin-bottom: 20px;
            color: #333;
            text-align: left;
        }
        .confirm-container .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .confirm-container button {
            padding: 15px 30px;
            background-color: #0c344e;
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .confirm-container button:hover {
            background-color: #176599;
        }
    </style>
</head>
<body>
    <div class="confirm-container">
        <h2>お問い合わせ内容確認</h2>
        <form method="post" action="toiawasefurusatoID.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <p>会社名・部署名: <?php echo htmlspecialchars($form['Division'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p>ご担当者名: <?php echo htmlspecialchars($form['Name'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p>メールアドレス: <?php echo htmlspecialchars($form['E-mail'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p>電話番号: <?php echo htmlspecialchars($form['Tel'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p>お問い合わせ内容: <?php echo nl2br(htmlspecialchars($form['Message'], ENT_QUOTES, 'UTF-8')); ?></p>
            <div class="button-container">
                <button type="button" onclick="history.back()">戻る</button>
                <button type="submit">送信</button>
            </div>
        </form>
    </div>
</body>
</html>