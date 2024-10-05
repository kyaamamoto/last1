<?php
session_start();
require_once('funcs.php');

// DB接続
$prod_db = "zouuu_zouuu_db";
$prod_host = "mysql635.db.sakura.ne.jp";
$prod_id = "zouuu";
$prod_pw = "12345678qju";

try {
    $pdo = new PDO('mysql:dbname=' . $prod_db . ';charset=utf8;host=' . $prod_host, $prod_id, $prod_pw);
} catch (PDOException $e) {
    exit('DBConnectError:' . $e->getMessage());
}

// 最新のデータ（updated_atが最新のもの）を取得するクエリ
$queries = [
    "SELECT birthplace, COUNT(*) as count FROM past_involvement WHERE (id, updated_at) IN (SELECT id, MAX(updated_at) FROM past_involvement GROUP BY user_id) GROUP BY birthplace",
    "SELECT place_of_residence, COUNT(*) as count FROM past_involvement WHERE (id, updated_at) IN (SELECT id, MAX(updated_at) FROM past_involvement GROUP BY user_id) GROUP BY place_of_residence",
    "SELECT travel_experience, COUNT(*) as count FROM past_involvement WHERE (id, updated_at) IN (SELECT id, MAX(updated_at) FROM past_involvement GROUP BY user_id) GROUP BY travel_experience",
    "SELECT volunteer_experience, COUNT(*) as count FROM past_involvement WHERE (id, updated_at) IN (SELECT id, MAX(updated_at) FROM past_involvement GROUP BY user_id) GROUP BY volunteer_experience",
    "SELECT donation_experience, COUNT(*) as count FROM past_involvement WHERE (id, updated_at) IN (SELECT id, MAX(updated_at) FROM past_involvement GROUP BY user_id) GROUP BY donation_experience",
    "SELECT product_purchase, COUNT(*) as count FROM past_involvement WHERE (id, updated_at) IN (SELECT id, MAX(updated_at) FROM past_involvement GROUP BY user_id) GROUP BY product_purchase",
    "SELECT work_experience, COUNT(*) as count FROM past_involvement WHERE (id, updated_at) IN (SELECT id, MAX(updated_at) FROM past_involvement GROUP BY user_id) GROUP BY work_experience"
];

$data = [];
foreach ($queries as $query) {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $data[] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// データをJSON形式にエンコード
$jsonData = array_map('json_encode', $data);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>会員情報分析 - ZOUUU Platform</title>
    <link href="http://localhost/gs_code/zouuu/css/style2.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-custom {
            background-color: #0c344e;
        }
        .navbar-custom .nav-link, .navbar-custom .navbar-brand {
            color: white;
        }
        h4 {
            text-align: center;
            margin-bottom: 20px;
            color: #0c344e;
        }
        .chart-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .chart-item {
            width: 30%;
            margin-bottom: 30px;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        @media (max-width: 992px) {
            .chart-item {
                width: 100%;
            }
        }
        
    .button-container {
        display: flex;
        justify-content: center;
        margin-top: 30px;
        margin-bottom: 30px;
    }
    .btn-next, .btn-back {
        color: white;
        margin: 0 10px;
    }
    .btn-next {
        background-color: #007bff;
        border-color: #007bff;
    }
    .btn-next:hover {
        background-color: #0056b3;
        border-color: #0056b3;
    }
    .btn-back {
        background-color: #6c757d;
        border-color: #6c757d;
    }
    .btn-back:hover {
        background-color: #5a6268;
        border-color: #545b62;
    }

    </style>
</head>
<body>

<!-- ヘッダー -->
<nav class="navbar navbar-expand-lg navbar-custom">
    <a class="navbar-brand" href="cms.php">
        <img src="./img/ZOUUU.png" alt="ZOUUU Logo" class="d-inline-block align-top" height="30">
        ZOUUU Platform
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <span class="nav-link">ようこそ <?php echo htmlspecialchars($_SESSION['name']); ?> さん</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cms.php">Home</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">ログアウト</a>
            </li>
        </ul>
    </div>
</nav>

<!-- パンくずリスト -->
<nav aria-label="breadcrumb" class="mt-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="cms.php">ホーム</a></li>
    <li class="breadcrumb-item active" aria-current="page">会員情報分析</li>
  </ol>
</nav>

<div class="container">
    <h4 class="mt-4 mb-4">会員の地域との関わり分析</h4>

    <!-- グラフを表示するエリア -->
    <div class="chart-container">
        <div class="chart-item">
            <h5>出身地情報</h5>
            <canvas id="birthplaceChart"></canvas>
        </div>
        <div class="chart-item">
            <h5>居住地情報</h5>
            <canvas id="residenceChart"></canvas>
        </div>
        <div class="chart-item">
            <h5>旅行経験</h5>
            <canvas id="travelChart"></canvas>
        </div>
        <div class="chart-item">
            <h5>ボランティア経験</h5>
            <canvas id="volunteerChart"></canvas>
        </div>
        <div class="chart-item">
            <h5>ふるさと納税経験</h5>
            <canvas id="donationChart"></canvas>
        </div>
        <div class="chart-item">
            <h5>地域物産品購入</h5>
            <canvas id="productPurchaseChart"></canvas>
        </div>
        <div class="chart-item">
            <h5>仕事での地域との関わり</h5>
            <canvas id="workChart"></canvas>
        </div>
    </div>

    <!-- ボタン配置 -->
    <div class="button-container">
        <a href="cms.php" class="btn btn-back btn-lg">戻る</a>
        <a href="analysis3.php" class="btn btn-next btn-lg">次へ</a>
    </div>
</div>

<script>
    // PHPから取得したデータをJavaScriptで使用
    const birthplaceData = <?php echo $jsonData[0]; ?>;
    const residenceData = <?php echo $jsonData[1]; ?>;
    const travelData = <?php echo $jsonData[2]; ?>;
    const volunteerData = <?php echo $jsonData[3]; ?>;
    const donationData = <?php echo $jsonData[4]; ?>;
    const productPurchaseData = <?php echo $jsonData[5]; ?>;
    const workData = <?php echo $jsonData[6]; ?>;

    // analysis3.phpと統一された色スキーム
    const colors = {
        blue: 'rgba(54, 162, 235, 0.7)',
        yellow: 'rgba(255, 206, 86, 0.7)',
        gray: 'rgba(201, 203, 207, 0.7)'
    };

    // グラフ作成の共通関数
    function createPieChart(ctx, labels, data, customColors) {
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: customColors,
                    borderColor: 'white',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                const total = tooltipItem.dataset.data.reduce((a, b) => a + b, 0);
                                const value = tooltipItem.raw;
                                const percentage = ((value / total) * 100).toFixed(2);
                                return `${tooltipItem.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 各グラフの作成
    createPieChart(document.getElementById('birthplaceChart').getContext('2d'), 
        ['出身地あり', '出身地なし'], 
        birthplaceData.map(item => item.count), 
        [colors.blue, colors.gray]
    );

    createPieChart(document.getElementById('residenceChart').getContext('2d'), 
        ['現在住んでいる', '過去に住んでいた', '住んだことがない'], 
        residenceData.map(item => item.count), 
        [colors.blue, colors.yellow, colors.gray]
    );

    createPieChart(document.getElementById('travelChart').getContext('2d'), 
        ['旅行経験あり', '旅行経験なし'], 
        travelData.map(item => item.count), 
        [colors.blue, colors.gray]
    );

    createPieChart(document.getElementById('volunteerChart').getContext('2d'), 
        ['ボランティア経験あり', 'ボランティア経験なし'], 
        volunteerData.map(item => item.count), 
        [colors.blue, colors.gray]
    );

    createPieChart(document.getElementById('donationChart').getContext('2d'), 
        ['ふるさと納税経験あり', 'ふるさと納税経験なし'], 
        donationData.map(item => item.count), 
        [colors.blue, colors.gray]
    );

    createPieChart(document.getElementById('productPurchaseChart').getContext('2d'), 
        ['物産品購入経験あり', '物産品購入経験なし'], 
        productPurchaseData.map(item => item.count), 
        [colors.blue, colors.gray]
    );

    createPieChart(document.getElementById('workChart').getContext('2d'), 
        ['地域との仕事の関わりあり', '地域との仕事の関わりなし'], 
        workData.map(item => item.count), 
        [colors.blue, colors.gray]
    );
</script>

<!-- BootstrapのJS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>