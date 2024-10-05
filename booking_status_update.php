<?php
require_once 'admin_session_config.php';
require_once 'funcs.php';

// 管理者認証チェック
if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
    header("Location: login.php");
    exit();
}

// 一般的なログインチェック（loginCheck関数を使用）
loginCheck();

// データベース接続
$pdo = db_conn();

$message = '';
$error = '';

// Ajaxリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // ステータス更新処理
    if (isset($_POST['slot_id'], $_POST['status'])) {
        $slot_id = $_POST['slot_id'];
        $status = $_POST['status'];

        // ステータスが有効な値か確認
        if (!in_array($status, ['confirmed', 'rejected'])) {
            echo json_encode(['success' => false, 'message' => "無効なステータスです。"]);
            exit;
        }

        try {
            // トランザクション開始
            $pdo->beginTransaction();

            // booking_request_slots テーブルの is_confirmed を更新
            $stmt = $pdo->prepare("UPDATE booking_request_slots SET is_confirmed = ? WHERE id = ?");
            $is_confirmed = ($status === 'confirmed') ? 1 : -1; // -1 は rejected
            $stmt->execute([$is_confirmed, $slot_id]);

            // リクエストに関連する全てのスロットが処理済みかチェック
            $stmt = $pdo->prepare("
                SELECT br.id, 
                       (SELECT COUNT(*) FROM booking_request_slots 
                        WHERE booking_request_id = br.id AND is_confirmed = 0) as pending_count
                FROM booking_requests br
                JOIN booking_request_slots brs ON br.id = brs.booking_request_id
                WHERE brs.id = ?
            ");
            $stmt->execute([$slot_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // すべてのスロットが処理されたか確認
            $allProcessed = ($result['pending_count'] == 0);

            // コミットして処理を完了
            $pdo->commit();

            // ステータスの結果を返す
            echo json_encode([
                'success' => true, 
                'message' => "スロット ID: " . $slot_id . " のステータスが " . ($status === 'confirmed' ? 'OK' : 'NG') . " に更新されました。",
                'allProcessed' => $allProcessed,
                'requestId' => $result['id']
            ]);
        } catch (PDOException $e) {
            // エラー時にロールバック
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => "更新に失敗しました: " . $e->getMessage()]);
        }
        exit;
    }

    // 返信処理
    if (isset($_POST['reply_message'], $_POST['request_id'])) {
        $reply_message = trim($_POST['reply_message']);  // 返信メッセージを取得
        $request_id = intval($_POST['request_id']);      // リクエストIDを取得

        try {
            // booking_requests テーブルの admin_reply カラムを更新
            $stmt = $pdo->prepare("UPDATE booking_requests SET admin_reply = ? WHERE id = ?");
            $stmt->execute([$reply_message, $request_id]);

            echo json_encode(['success' => true, 'message' => '返信が送信されました。']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => '返信の送信に失敗しました: ' . $e->getMessage()]);
        }
        exit;
    }
}

// 未処理のスロットを含む予約リクエストを取得
try {
    $stmt = $pdo->query("
        SELECT 
            br.id AS request_id,
            h.name AS user_name,
            f.name AS frontier_name,
            brs.id AS slot_id,
            brs.date,
            brs.start_time,
            brs.end_time,
            brs.is_confirmed,
            br.user_message,
            br.admin_reply
        FROM booking_requests br
        JOIN holder_table h ON br.user_id = h.id
        JOIN gs_chiiki_frontier f ON br.frontier_id = f.id
        JOIN booking_request_slots brs ON br.id = brs.booking_request_id
        WHERE EXISTS (
            SELECT 1
            FROM booking_request_slots brs2
            WHERE brs2.booking_request_id = br.id AND brs2.is_confirmed = 0
        )
        ORDER BY br.created_at DESC, brs.date, brs.start_time
    ");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "データ取得エラー: " . $e->getMessage();
    $requests = [];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>予約リクエスト管理</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>
        .navbar-custom {
            background-color: #0c344e;
        }
        .navbar-custom .nav-link, .navbar-custom .navbar-brand {
            color: white;
        }
        .status-button {
            width: 60px;
        }
        .user-message {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 10px;
            margin-bottom: 10px;
        }
        .admin-reply {
            background-color: #e9ecef;
            border-left: 4px solid #28a745;
            padding: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-custom">
    <a class="navbar-brand" href="#">
        <img src="./img/ZOUUU.png" alt="ZOUUU Logo" class="d-inline-block align-top" height="30">
        ZOUUU Platform
    </a>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="cms.php">Home</a>
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
        <li class="breadcrumb-item active" aria-current="page">予約リクエスト管理</li>
    </ol>
</nav>

<div class="container mt-5">
    <h1>予約リクエスト管理</h1>

    <div id="message-container"></div>

    <?php if (empty($requests)): ?>
        <div class="alert alert-info" role="alert">
            処理が必要な予約リクエストはありません。
        </div>
    <?php else: ?>
        <?php
        $current_request_id = null;
        foreach ($requests as $request):
            if ($current_request_id !== $request['request_id']):
                if ($current_request_id !== null):
                    echo '</div></div>';  // 前のカードを閉じる
                endif;
                $current_request_id = $request['request_id'];
        ?>
            <div class="card mb-4" id="request-card-<?= $request['request_id'] ?>">
                <div class="card-body">
                    <h5 class="card-title">ユーザー名: <?= htmlspecialchars($request['user_name']) ?></h5>
                    <p class="card-text">フロンティア名: <?= htmlspecialchars($request['frontier_name']) ?></p>
                    
                    <!-- ユーザーメッセージ表示 -->
                    <?php if (!empty($request['user_message'])): ?>
                        <div class="user-message">
                            <strong>ユーザーメッセージ:</strong>
                            <p><?= nl2br(htmlspecialchars($request['user_message'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- 管理者返信表示 -->
                    <?php if (!empty($request['admin_reply'])): ?>
                        <div class="admin-reply">
                            <strong>管理者返信:</strong>
                            <p><?= nl2br(htmlspecialchars($request['admin_reply'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- 返信フォーム -->
                    <div class="reply-form mt-3">
                        <textarea class="form-control" rows="3" placeholder="返信メッセージを入力"></textarea>
                        <button class="btn btn-primary mt-2 send-reply" data-request-id="<?= $request['request_id'] ?>">返信する</button>
                    </div>
        <?php
            endif;
        ?>
            <div class="d-flex justify-content-between align-items-center mb-2" id="slot-<?= $request['slot_id'] ?>">
                <span>リクエスト日時: <?= htmlspecialchars($request['date'] . ' ' . $request['start_time'] . ' - ' . $request['end_time']) ?></span>
                <div>
                    <?php if ($request['is_confirmed'] == 0): ?>
                        <button class="btn btn-success btn-sm status-button" data-slot-id="<?= $request['slot_id'] ?>" data-status="confirmed">OK</button>
                        <button class="btn btn-danger btn-sm status-button" data-slot-id="<?= $request['slot_id'] ?>" data-status="rejected">NG</button>
                    <?php elseif ($request['is_confirmed'] == 1): ?>
                        <span class="badge badge-success">確認済み</span>
                    <?php elseif ($request['is_confirmed'] == -1): ?>
                        <span class="badge badge-danger">拒否済み</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php
        endforeach;
        if ($current_request_id !== null):
            echo '</div></div>';  // 最後のカードを閉じる
        endif;
        ?>
    <?php endif; ?>
</div>

<footer class="footer bg-light text-center py-3 mt-4">
    <div class="container">
        <span class="text-muted">Copyright &copy; 2024 <a href="#">ZOUUU</a>. All rights reserved.</span>
    </div>
</footer>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns" crossorigin="anonymous"></script>
<script>
$(document).ready(function() {
    $('.status-button').on('click', function() {
        var button = $(this);
        var slotId = button.data('slot-id');
        var status = button.data('status');
        
        $.ajax({
            url: 'booking_status_update.php',
            type: 'POST',
            data: {
                slot_id: slotId,
                status: status
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                    var statusBadge = $('<span>').addClass('badge').text(status === 'confirmed' ? '確認済み' : '拒否済み');
                    if (status === 'confirmed') {
                        statusBadge.addClass('badge-success');
                    } else {
                        statusBadge.addClass('badge-danger');
                    }
                    $('#slot-' + slotId + ' .status-button').remove();
                    $('#slot-' + slotId + ' div').append(statusBadge);
                    
                    if (response.allProcessed) {
                        $('#request-card-' + response.requestId).fadeOut(500, function() { 
                            $(this).remove();
                            if ($('.card').length === 0) {
                                $('.container').append('<div class="alert alert-info" role="alert">処理が必要な予約リクエストはありません。</div>');
                            }
                        });
                    }
                } else {
                    $('#message-container').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function() {
                $('#message-container').html('<div class="alert alert-danger">エラーが発生しました。</div>');
            }
        });
    });

    // 返信送信処理
    $('.send-reply').on('click', function() {
        var button = $(this);
        var requestId = button.data('request-id');
        var replyMessage = button.siblings('textarea').val().trim();

        if (replyMessage === '') {
            alert('返信メッセージを入力してください。');
            return;
        }

        $.ajax({
            url: 'booking_status_update.php',
            type: 'POST',
            data: {
                request_id: requestId,
                reply_message: replyMessage
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#message-container').html('<div class="alert alert-success">' + response.message + '</div>');
                    // 返信を画面に表示
                    var replyHtml = '<div class="admin-reply"><strong>管理者返信:</strong><p>' + replyMessage.replace(/\n/g, '<br>') + '</p></div>';
                    button.closest('.card-body').find('.reply-form').before(replyHtml);
                    button.siblings('textarea').val(''); // テキストエリアをクリア
                } else {
                    $('#message-container').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function() {
                $('#message-container').html('<div class="alert alert-danger">返信の送信中にエラーが発生しました。</div>');
            }
        });
    });
});
</script>

</body>
</html>