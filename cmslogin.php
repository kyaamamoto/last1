<?php
require_once 'admin_session_config.php';
require_once 'funcs.php';

// CSRFトークンの生成
$csrf_token = $_SESSION['csrf_token'];

// オプション: 管理者のみがアクセス可能にする場合
if (isset($_SESSION['kanri_flg']) && $_SESSION['kanri_flg'] == 1) {
    // 管理者用の追加機能やメッセージをここに追加
} else {
    // 一般ユーザー向けの表示や機能制限をここに追加
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>ユーザー登録</title>
  <link rel="stylesheet" href="./css/style4.css">
  <style>
    .message {
      color: green;
      font-weight: bold;
      margin-top: 10px;
    }
    .error {
      color: red;
      font-weight: bold;
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <img src="img/ZOUUUbanner.jpg" alt="ZOUUU Banner">
    <h1>ユーザー登録</h1>
    <?php
    // エラーメッセージの表示
    if (isset($_SESSION['registration_error'])) {
        echo "<p class='error'>" . h($_SESSION['registration_error']) . "</p>";
        unset($_SESSION['registration_error']);
    }
    ?>
    <form method="post" action="user_insert.php" onsubmit="return validateForm()">
      <div class="form-group">
        <label for="name">名前:</label>
        <input type="text" name="name" id="name" required>
      </div>
      <div class="form-group">
        <label for="lid">Login ID:</label>
        <input type="text" name="lid" id="lid" required>
      </div>
      <div class="form-group">
        <label for="lpw">Login PW:</label>
        <input type="password" name="lpw" id="lpw" required>
      </div>
      <div class="form-group">
        <label>管理FLG:</label>
        <div>
          <label><input type="radio" name="kanri_flg" value="0" checked> 一般</label>
          <label><input type="radio" name="kanri_flg" value="1"> 管理者</label>
        </div>
      </div>
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
      <button type="submit" class="btn">送信</button>
    </form>
    <div class="btn-register mt-3">
      <a href="login.php">ログインはこちら</a>
    </div>
  </div>

  <script>
  function validateForm() {
      var name = document.forms[0]["name"].value;
      var lid = document.forms[0]["lid"].value;
      var lpw = document.forms[0]["lpw"].value;
      if (name == "" || lid == "" || lpw == "") {
          alert("全ての項目を入力してください");
          return false;
      }
      return true;
  }
  </script>
</body>
</html>