<?php
define('DEBUG', false);  // 開発環境ではtrue、本番環境ではfalse

function debug_log($message) {
    if (DEBUG) {
        error_log($message);
    }
}

ini_set('display_errors', DEBUG ? 1 : 0);
ini_set('display_startup_errors', DEBUG ? 1 : 0);
error_reporting(DEBUG ? E_ALL : 0);

require_once 'admin_session_config.php';
require_once 'funcs.php';

// 管理者認証チェック
if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
    header("Location: login.php");
    exit();
}

// 一般的なログインチェック
loginCheck();

// データベース接続
try {
    $pdo = db_conn();
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// 統計情報の取得
function getStatistics($pdo) {
    $stats = [];
    $tables = ['holder_table', 'toiawase_table', 'user_table', 'gs_chiiki_frontier'];
    foreach ($tables as $table) {
        $stats[$table] = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }
    return $stats;
}

// 学習テーマの進捗状況を取得する関数
function getUserSelectedFrontierProgress($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT f.id, f.name, f.category, f.image_url, ufp.status, ufp.start_time, ufp.completion_time
        FROM gs_chiiki_frontier f
        JOIN user_frontier_progress ufp ON f.id = ufp.frontier_id
        WHERE ufp.user_id = :user_id
        ORDER BY CASE 
            WHEN ufp.status = 'in_progress' THEN 1
            WHEN ufp.status = 'not_started' THEN 2
            ELSE 3 
        END,
        ufp.start_time DESC
    ");
    
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 予約状況を取得する関数
function getUserBookingStatus($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            br.id as booking_id,
            br.status as booking_status,
            brs.id as slot_id,
            brs.date,
            brs.start_time,
            brs.end_time,
            brs.is_confirmed
        FROM 
            booking_requests br
        JOIN 
            booking_request_slots brs ON br.id = brs.booking_request_id
        WHERE 
            br.user_id = :user_id
        ORDER BY 
            br.created_at DESC, brs.date ASC, brs.start_time ASC
    ");
    
    $stmt->execute([':user_id' => $user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bookings = [];
    foreach ($results as $row) {
        if (!isset($bookings[$row['booking_id']])) {
            $bookings[$row['booking_id']] = [
                'id' => $row['booking_id'],
                'status' => $row['booking_status'],
                'slots' => []
            ];
        }
        $bookings[$row['booking_id']]['slots'][] = [
            'id' => $row['slot_id'],
            'date' => $row['date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'is_confirmed' => $row['is_confirmed']
        ];
    }

    return array_values($bookings);
}

// 進捗率を計算する関数
function calculateProgress($user_id, $pdo) {
    debug_log("Calculating progress for User ID: " . $user_id);

    $total_items = 8;
    $completed_items = 0;

    $stmt = $pdo->prepare("SELECT * FROM holder_table WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {
        debug_log("User data not found for User ID: " . $user_id);
        return 0;
    }

    $fields_to_check = ['theme', 'inquiry_content', 'hypothesis', 'learning_report', 'factor_analysis', 'summary'];
    foreach ($fields_to_check as $field) {
        if (!empty($user[$field])) {
            $completed_items++;
            debug_log("$field is set");
        } else {
            debug_log("$field is not set");
        }
    }

    $frontierProgress = getUserSelectedFrontierProgress($pdo, $user_id);
    if (count(array_filter($frontierProgress, function($f) { return $f['status'] == 'completed'; })) > 0) {
        $completed_items++;
        debug_log("Frontier progress found");
    } else {
        debug_log("No completed frontier progress");
    }

    $bookingStatus = getUserBookingStatus($pdo, $user_id);
    $confirmedBookings = array_filter($bookingStatus, function($booking) {
        return $booking['status'] === 'confirmed' || 
               count(array_filter($booking['slots'], function($slot) { return $slot['is_confirmed'] == 1; })) > 0;
    });

    if (count($confirmedBookings) > 0) {
        $completed_items++;
        debug_log("Booking confirmed");
    } else {
        debug_log("No confirmed bookings");
    }

    $progress = ($completed_items / $total_items) * 100;

    if (!empty($user['presentation_url'])) {
        $progress += 20;
        debug_log("Presentation URL set - Adding 20%");
    } else {
        debug_log("Presentation URL not set");
    }

    $progress = min($progress, 100);
    debug_log("Final progress calculation: $progress%");

    return $progress;
}

// 進捗データの取得
function getProgressData($pdo) {
    $users = $pdo->query("SELECT id, name FROM holder_table")->fetchAll(PDO::FETCH_ASSOC);
    $progress_data = [];
    $user_names = [];

    foreach ($users as $user) {
        $progress = calculateProgress($user['id'], $pdo);
        $progress_data[] = $progress;
        $user_names[] = $user['name'];
        debug_log("User ID: {$user['id']}, Name: {$user['name']}, Progress: {$progress}%");
    }

    return ['progress_data' => $progress_data, 'user_names' => $user_names];
}

// メインの処理
$statistics = getStatistics($pdo);
$progress_info = getProgressData($pdo);

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ZOUUU Platform - 管理者ダッシュボード</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .navbar-custom {
            background-color: #0c344e;
        }
        .navbar-custom .nav-link, .navbar-custom .navbar-brand {
            color: white;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-custom">
    <a class="navbar-brand" href="#">
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
                <a class="nav-link" href="#">Home</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">ログアウト</a>
            </li>
        </ul>
    </div>
</nav>

<nav aria-label="breadcrumb" class="mt-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="cms.php">ホーム</a></li>
    <li class="breadcrumb-item active" aria-current="page">管理ダッシュボード</li>
  </ol>
</nav>

<div class="container mt-5">
    <h2 class="mb-4">管理ダッシュボード</h2>
    
    <div class="row">
        <div class="col-lg-12">
            <?php if ($_SESSION['kanri_flg'] == 1): // 管理者の場合 ?>
                <!-- 学習進捗管理グラフセクション -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="m-0">学習進捗管理</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="progressChart" width="400" height="100"></canvas>
                    </div>
                </div>

                <!-- 詳細確認セクション -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="m-0">学習進捗状況確認</h5>
                    </div>
                    <div class="card-body">
                        <div class="btn-group d-flex flex-wrap justify-content-center" role="group" aria-label="User Details">
                            <?php
                            $stmt = $pdo->query("SELECT id, name FROM holder_table");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<a href="progress_detail.php?user_id=' . $row['id'] . '" class="btn btn-primary btn-sm m-1">';
                                echo htmlspecialchars($row['name']) . '<br>進捗状況';
                                echo '</a>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- ユーザー管理セクション -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="m-0">ユーザー管理</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="card-title">関係者情報</h6>
                        <p class="card-text">ZOUUU会員、管理者、地域フロンティアの情報を確認できます。</p>
                        <div class="d-flex flex-wrap">
                            <a href="select.php" class="btn btn-primary mr-2 mb-2">ZOUUU会員情報 <span class="badge badge-light"><?php echo $statistics['holder_table']; ?></span></a>
                            <a href="user_table.php" class="btn btn-primary mr-2 mb-2">管理者情報 <span class="badge badge-light"><?php echo $statistics['user_table']; ?></span></a>
                            <a href="frontier_list.php" class="btn btn-primary mb-2">地域フロンティア一覧 <span class="badge badge-light"><?php echo $statistics['gs_chiiki_frontier']; ?></span></a>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <!-- 共通のコンテンツ管理エリア -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="m-0">コンテンツ管理</h5>
                </div>
                <div class="card-body">
                    <h6 class="card-title">地域フロンティア管理</h6>
                    <p class="card-text">地域フロンティアの登録と体験申込の管理を行います。</p>
                    <div class="d-flex flex-wrap">
                        <a href="frontier_register.php" class="btn btn-primary mr-2 mb-2">地域フロンティア登録</a>
                        <a href="booking_status_update.php" class="btn btn-primary mr-2 mb-2">体験申込日程確認</a>
                        <a href="admin_reservations.php" class="btn btn-primary mb-2">体験受入NG日程登録</a>
                    </div>
                </div>
            </div>

          <!-- お知らせ機能セクション -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="m-0">お知らせ機能</h5>
            </div>
            <div class="card-body">
                <label for="user-select">ユーザーを選択:</label>
                <select id="user-select" class="form-control mb-3" multiple>
                    <!-- <option value="">ユーザーを選択</option> -->
                    <?php
                    // ユーザーリストを取得
                    $stmt = $pdo->query("SELECT id, name FROM holder_table");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo '<option value="' . h($row['id']) . '">' . h($row['name']) . '</option>';
                    }
                    ?>
                </select>

                <label for="message-input">メッセージを入力:</label>
                <textarea id="message-input" class="form-control mb-3" placeholder="メッセージを入力" rows="4"></textarea>
                
                <button id="send-message" class="btn btn-primary">送信</button>
                <button id="view-notifications" class="btn btn-secondary">過去のお知らせ</button>

                <div id="response" class="mt-3"></div> <!-- ここは1つだけ残す -->
            </div>
        </div>

<footer class="footer bg-light text-center py-3 mt-4">
    <div class="container">
        <span class="text-muted">Copyright &copy; 2024 <a href="#">ZOUUU</a>. All rights reserved.</span>
    </div>
</footer>

<!-- jQueryとBootstrap、Chart.jsのライブラリを読み込む -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    var ctx = document.getElementById('progressChart').getContext('2d');
    var progressChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($progress_info['user_names']); ?>,
            datasets: [{
                label: '学習進捗率 (%)',
                data: <?php echo json_encode($progress_info['progress_data']); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    document.getElementById('send-message').addEventListener('click', function() {
    const selectedOptions = document.getElementById('user-select').selectedOptions;
    const userIds = Array.from(selectedOptions).map(option => option.value);
    const message = document.getElementById('message-input').value;

    if (userIds.length === 0 || !message) {
        alert("ユーザーを選択し、メッセージを入力してください。");
        return;
    }

    fetch('send_notification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_ids=${JSON.stringify(userIds)}&message=${encodeURIComponent(message)}`
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('response').innerText = data;
        document.getElementById('message-input').value = ''; // 入力フィールドをクリア
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('response').innerText = 'メッセージ送信中にエラーが発生しました。';
    });
});

document.getElementById('view-notifications').addEventListener('click', function() {
    window.location.href = 'view_notifications.php'; // 正しい遷移先
});

</script>
</body>
</html>