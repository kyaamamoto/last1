<!-- confirm.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>確認ページ</title>
    <link rel="stylesheet" href="./css/style.css">
</head>
<body>
    <div class="container">
        <h1>確認ページ</h1>
        <form action="insert.php" method="post">
            <?php
            // POSTデータを取得
            $name = $_POST["name"] ?? '';
            $birthDay = $_POST["birthDay"] ?? '';
            $tel = $_POST["tel"] ?? '';
            $email = $_POST["email"] ?? '';
            $jusho1 = $_POST["jusho1"] ?? '';
            $jusho2 = $_POST["jusho2"] ?? '';
            $jusho3 = $_POST["jusho3"] ?? '';
            $jusho4 = $_POST["jusho4"] ?? '';
            $kanshin1 = $_POST["kanshin1"] ?? '';
            $kanshin2 = $_POST["kanshin2"] ?? '';
            $kanshin3 = $_POST["kanshin3"] ?? '';
            $food = $_POST["food"] ?? [];
            $travel = $_POST["travel"] ?? [];
            $shumi = $_POST["shumi"] ?? [];
            $action = $_POST["action"] ?? [];

            // 入力データを表示
            echo "<p>お名前: $name</p>";
            echo "<p>生年月日: $birthDay</p>";
            echo "<p>電話番号: $tel</p>";
            echo "<p>Email: $email</p>";
            echo "<p>お住まいの都道府県: $jusho1</p>";
            echo "<p>市区町村: $jusho2</p>";
            echo "<p>ご出身の都道府県: $jusho3</p>";
            echo "<p>市区町村: $jusho4</p>";
            echo "<p>お住まいの地域への興味・関心: $kanshin1</p>";
            echo "<p>出身地への興味・関心: $kanshin2</p>";
            echo "<p>その他地域への興味・関心: $kanshin3</p>";
            echo "<p>地域の食べ物: " . implode(", ", $food) . "</p>";
            echo "<p>旅行・観光: " . implode(", ", $travel) . "</p>";
            echo "<p>趣味・娯楽: " . implode(", ", $shumi) . "</p>";
            echo "<p>地域活動: " . implode(", ", $action) . "</p>";
            ?>

            <!-- POSTデータをhiddenフィールドで送信 -->
            <input type="hidden" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="birthDay" value="<?php echo htmlspecialchars($birthDay, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="tel" value="<?php echo htmlspecialchars($tel, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="jusho1" value="<?php echo htmlspecialchars($jusho1, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="jusho2" value="<?php echo htmlspecialchars($jusho2, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="jusho3" value="<?php echo htmlspecialchars($jusho3, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="jusho4" value="<?php echo htmlspecialchars($jusho4, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="kanshin1" value="<?php echo htmlspecialchars($kanshin1, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="kanshin2" value="<?php echo htmlspecialchars($kanshin2, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="kanshin3" value="<?php echo htmlspecialchars($kanshin3, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="food" value="<?php echo htmlspecialchars(json_encode($food), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="travel" value="<?php echo htmlspecialchars(json_encode($travel), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="shumi" value="<?php echo htmlspecialchars(json_encode($shumi), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="<?php echo htmlspecialchars(json_encode($action), ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-group center">
                <button type="button" class="submit-btn" onclick="history.back()">戻る</button>   
                <button type="submit" class="submit-btn">送信</button>
            </div>
        </form>
    </div>
</body>
</html>