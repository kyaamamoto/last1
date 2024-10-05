<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'funcs.php';
require_once 'session_config.php';

// ログインチェック
loginCheck();

$pdo = db_conn();
$user_id = $_SESSION['user_id'];

// 最新のデータを取得する関数
function fetchLatestData($pdo, $table, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE user_id = :user_id ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// スキルラベル取得関数
function getSkillLabel($value) {
    $labels = ['', '苦手', 'やや苦手', 'どちらでもない', 'やや得意', '得意'];
    return $labels[$value] ?? '';
}

// 興味関心ラベル取得関数
function getInterestLabel($value) {
    $labels = ['', '全く関心がない', 'あまり関心がない', '普通', '関心がある', '非常に関心がある'];
    return $labels[$value] ?? '';
}

try {
    $skill_data = fetchLatestData($pdo, 'skill_check', $user_id);
    $past_data = fetchLatestData($pdo, 'past_involvement', $user_id);
    $future_data = fetchLatestData($pdo, 'future_involvement', $user_id);

    if (!$skill_data && !$past_data && !$future_data) {
        throw new Exception("必要なデータが見つかりません");
    }

    $user_data = array_merge($skill_data ?: [], $past_data ?: [], $future_data ?: []);

} catch (Exception $e) {
    error_log("Error in confirmation.php: " . $e->getMessage());
    $_SESSION['error_message'] = "データの取得中にエラーが発生しました: " . $e->getMessage();
    redirect('holder.php');
}

// CSRFトークンを生成
$csrf_token = generateToken();

// 登録完了処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_registration'])) {
    validateToken($_POST['csrf_token']);
    
    // ここで最終的なデータの保存や更新を行うことができます
    // 例: 
    // saveOrUpdateData($pdo, 'skill_check', $user_id, $skill_data);
    // saveOrUpdateData($pdo, 'past_involvement', $user_id, $past_data);
    // saveOrUpdateData($pdo, 'future_involvement', $user_id, $future_data);

    $_SESSION['success_message'] = "ふるさとIDの登録が完了しました。";
    redirect('holder.php');
}

// データの保存や更新を行う関数（必要に応じて実装）
function saveOrUpdateData($pdo, $table, $user_id, $data) {
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $updates = [];
    foreach (array_keys($data) as $key) {
        $updates[] = "$key = VALUES($key)";
    }
    $updateString = implode(', ', $updates);

    $sql = "INSERT INTO $table (user_id, $columns, created_at, updated_at) 
            VALUES (:user_id, $placeholders, NOW(), NOW())
            ON DUPLICATE KEY UPDATE $updateString, updated_at = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    foreach ($data as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->execute();
}

// 以下、HTMLの表示部分
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登録内容の確認 - ふるさとID</title>
    <link rel="icon" type="image/png" href="https://zouuu.sakura.ne.jp/zouuu/img/IDfavicon.ico">
    <link rel="stylesheet" href="./css/styleholder.css">
</head>
<body>
    <header>
        <div class="logo">
            <a href="holder.php"><img src="https://zouuu.sakura.ne.jp/zouuu/img/fIDLogo.png" alt="ふるさとID ロゴ"></a>
        </div>
        <nav>
            <ul>
                <li><a href="skill_check.php">ふるさとID申請</a></li>
                <li><a href="#">ふるさとID活動記録</a></li>
                <li><a href="#">ふるさと×ウェルビーイング</a></li>
                <li><a href="logoutmypage.php">ログアウト</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h2>登録内容の確認</h2>
        <?php if (isset($_SESSION['error_message'])): ?>
            <p class="error"><?php echo h($_SESSION['error_message']); ?></p>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- スキルチェック -->
        <section>
            <h3>スキルチェック</h3>
            <?php
            if ($skill_data) {
                $skills = [
                    'cooking' => '料理', 'cleaning' => '掃除', 'childcare' => '子育て', 
                    'communication' => 'コミュニケーション', 'foreign_language' => '外国語',
                    'logical_thinking' => '論理的思考', 'it_skill' => 'IT', 'data_skill' => 'データ収集・分析'
                ];
                foreach ($skills as $key => $label) {
                    echo "<p>" . h($label) . ": " . h(getSkillLabel($skill_data[$key])) . "</p>";
                }
            } else {
                echo "<p>スキルデータが見つかりません。</p>";
            }
            ?>
            <p><a href="skill_check.php">スキルチェックを修正する</a></p>
        </section>

        <!-- 過去の地域との関わり -->
        <section>
            <h3>過去の地域との関わり</h3>
            <?php if ($past_data): ?>
                <p>出身地である: <?php echo h($past_data['birthplace'] === 'yes' ? 'はい' : 'いいえ'); ?></p>
                <p>居住地である: <?php echo h($past_data['place_of_residence']); ?></p>
                <p>旅行経験: <?php echo h($past_data['travel_experience'] === 'yes' ? 'あり' : 'なし'); ?></p>
                <p>訪問頻度: <?php echo h($past_data['visit_frequency']); ?></p>
                <p>滞在期間: <?php echo h($past_data['stay_duration']); ?></p>
                <p>ボランティア経験: <?php echo h($past_data['volunteer_experience'] === 'yes' ? 'あり' : 'なし'); ?></p>
                <?php if ($past_data['volunteer_experience'] === 'yes'): ?>
                    <p>ボランティア活動内容: <?php echo h(implode(', ', json_decode($past_data['volunteer_activity'], true))); ?></p>
                    <p>ボランティア頻度: <?php echo h($past_data['volunteer_frequency']); ?></p>
                <?php endif; ?>
                <p>ふるさと納税経験: <?php echo h($past_data['donation_experience'] === 'yes' ? 'あり' : 'なし'); ?></p>
                <?php if ($past_data['donation_experience'] === 'yes'): ?>
                    <p>寄付回数: <?php echo h($past_data['donation_count']); ?></p>
                    <p>寄付理由: <?php echo h($past_data['donation_reason']); ?></p>
                <?php endif; ?>
                <p>物産品購入経験: <?php echo h($past_data['product_purchase'] === 'yes' ? 'あり' : 'なし'); ?></p>
                <?php if ($past_data['product_purchase'] === 'yes'): ?>
                    <p>購入頻度: <?php echo h($past_data['purchase_frequency']); ?></p>
                    <p>購入理由: <?php echo h($past_data['purchase_reason']); ?></p>
                <?php endif; ?>
                <p>仕事での関わり: <?php echo h($past_data['work_experience'] === 'yes' ? 'あり' : 'なし'); ?></p>
                <?php if ($past_data['work_experience'] === 'yes'): ?>
                    <p>仕事の種類: <?php echo h(implode(', ', json_decode($past_data['work_type'], true))); ?></p>
                    <p>仕事での関わり頻度: <?php echo h($past_data['work_frequency']); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p>過去の関わりデータが見つかりません。</p>
            <?php endif; ?>
            <p><a href="past_involvement.php">過去の関わりを修正する</a></p>
        </section>

        <!-- 今後の地域との関わり方 -->
        <section>
            <h3>今後の地域との関わり方</h3>
            <?php if ($future_data): ?>
                <?php
                $future_interests = [
                    'interest_furusato_tax' => 'ふるさと納税',
                    'interest_local_events' => '地域イベントへの参加',
                    'interest_volunteer' => '地域でのボランティア活動',
                    'interest_local_products' => '地域物産品の購入',
                    'interest_relocation' => '移住や長期滞在',
                    'interest_business_support' => '地域ビジネスの支援',
                    'interest_startup' => '地域での起業',
                    'interest_employment' => '地域での就職・転職'
                ];

                foreach ($future_interests as $key => $label) {
                    echo "<p>" . h($label) . ": " . h(getInterestLabel($future_data[$key])) . "</p>";
                }

                if (!empty($future_data['interest_other'])) {
                    echo "<p>その他: " . h($future_data['interest_other']) . "</p>";
                }
                ?>
            <?php else: ?>
                <p>今後の関わりデータが見つかりません。</p>
            <?php endif; ?>
            <p><a href="future_involvement.php">今後の関わり方を修正する</a></p>
        </section>

        <!-- 登録完了ボタン -->
        <form action="confirmation.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="submit" name="complete_registration" value="登録を完了する">
        </form>
    </main>

    <footer>
        <p>&copy; 2024 ふるさとID. All rights reserved.</p>
    </footer>
</body>
</html>