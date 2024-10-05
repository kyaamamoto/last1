<?php
session_start();
require_once('funcs.php');

// DB接続
$prod_db = "zouuu_zouuu_db";
$prod_host = "mysql635.db.sakura.ne.jp";
$prod_id = "zouuu";
$prod_pw = "パスワード";

try {
    $pdo = new PDO('mysql:dbname=' . $prod_db . ';charset=utf8;host=' . $prod_host, $prod_id, $prod_pw);
} catch (PDOException $e) {
    exit('DBConnectError:' . $e->getMessage());
}

// 最新のデータを取得するクエリ
$query = "
    SELECT 
        interest_furusato_tax, interest_local_events, interest_volunteer, interest_local_products, 
        interest_relocation, interest_business_support, interest_startup, interest_employment 
    FROM future_involvement 
    WHERE (user_id, updated_at) IN (
        SELECT user_id, MAX(updated_at)
        FROM future_involvement
        GROUP BY user_id
    )
";
$stmt = $pdo->prepare($query);
$status = $stmt->execute();

// データを取得
$data = [];
if ($status == false) {
    $error = $stmt->errorInfo();
    exit("ErrorQuery:".$error[2]);
} else {
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $result;
    }
}

// データをJSON形式にエンコード
$jsonData = json_encode($data);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>将来の関わり方分析</title>
    <link href="http://localhost/gs_code/zouuu/css/style2.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
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
        .data-quality-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .chart-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 60vh;
            margin: 0 auto;
            max-width: 80%; /* コンテナの最大幅を設定 */
        }
        #futureInvolvementChart {
            width: 100% !important;
            height: auto !important;
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
    <li class="breadcrumb-item"><a href="analysis2.php">会員情報分析</a></li>
    <li class="breadcrumb-item active" aria-current="page">将来の関わり方分析</li>
  </ol>
</nav>

<!-- メインコンテンツ -->
<div class="container">
    <h4>ユーザーの将来の関わり方分析</h4>

    <!-- <div id="debugInfo" style="background-color: #f0f0f0; padding: 10px; margin-bottom: 20px;"></div> -->

   <!-- グラフ表示エリア -->
        <div class="chart-container">
            <canvas id="futureInvolvementChart"></canvas>
        </div>

    <!-- グラフの解釈ガイド -->
    <div class="mt-4">
        <h5>グラフの見方</h5>
        <ul>
            <li>各棒は異なる関心分野を表しています。</li>
            <li>棒の色は関心度のレベルを示しています（濃い色ほど関心が高い）。</li>
            <li>棒の高さは各関心度レベルのユーザー数を表しています。</li>
            <li>グラフは有効なデータのみを反映しています。</li>
        </ul>
    </div>

    <!-- ナビゲーションボタン -->
    <div class="text-center mt-5 mb-5">
        <a href="analysis2.php" class="btn btn-secondary btn-lg mr-3">戻る</a>
        <a href="cms.php" class="btn btn-primary btn-lg">Home</a>
    </div>
</div>

<!-- グラフ描画スクリプト -->
<script>
try {
    const futureData = <?php echo $jsonData; ?>;

    const labels = [
        'ふるさと納税', '地域イベント', 'ボランティア', '地域産品', 
        '移住', 'ビジネス支援', 'スタートアップ', '雇用'
    ];

    const keyMapping = {
        'ふるさと納税': 'interest_furusato_tax',
        '地域イベント': 'interest_local_events',
        'ボランティア': 'interest_volunteer',
        '地域産品': 'interest_local_products',
        '移住': 'interest_relocation',
        'ビジネス支援': 'interest_business_support',
        'スタートアップ': 'interest_startup',
        '雇用': 'interest_employment'
    };

    const interestLevels = ['全く関心がない', 'あまり関心がない', '普通', '関心がある', '非常に関心がある'];
    const colors = [
        'rgba(255, 99, 132, 0.7)',
        'rgba(255, 159, 64, 0.7)',
        'rgba(255, 205, 86, 0.7)',
        'rgba(75, 192, 192, 0.7)',
        'rgba(54, 162, 235, 0.7)'
    ];

    const datasets = interestLevels.map((level, index) => {
        const data = labels.map(label => {
            const key = keyMapping[label];
            let count = 0;
            futureData.forEach(item => {
                if (item[key] === index + 1) {
                    count++;
                }
            });
            return count;
        });
        return {
            label: level,
            data: data,
            backgroundColor: colors[index],
        };
    });

    const ctx = document.getElementById('futureInvolvementChart');
    if (!ctx) {
        throw new Error('Canvas element not found');
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            scales: {
                x: { 
                    stacked: true,
                    title: {
                        display: true,
                        text: '関心分野'
                    }
                },
                y: { 
                    stacked: true,
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'ユーザー数'
                    }
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                title: {
                    display: true,
                    text: '将来の地域との関わり方に対する関心度',
                    font: {
                        size: 18
                    },
                    padding: {
                        top: 10,
                        bottom: 30
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.parsed.y}人`;
                        }
                    }
                }
            }
        }
    });

} catch (error) {
    console.error('グラフの描画中にエラーが発生しました:', error);
}
</script>

<!-- BootstrapのJS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>