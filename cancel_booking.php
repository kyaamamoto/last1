<?php
session_start();
require_once 'session_config.php';
require_once 'security_headers.php';
require_once 'funcs.php';

// Content-Typeヘッダーを設定
header('Content-Type: application/json');

// ログインチェック
loginCheck();

// POSTデータの取得
$postData = json_decode(file_get_contents('php://input'), true);
$booking_id = isset($postData['booking_id']) ? intval($postData['booking_id']) : 0;

error_log("Received booking_id: " . $booking_id);
error_log("User ID: " . $_SESSION['user_id']);

if ($booking_id === 0) {
    error_log('予約IDが指定されていません。');
    echo json_encode(['success' => false, 'message' => '予約IDが指定されていません。']);
    exit;
}

// データベース接続
$pdo = db_conn();
error_log("Database connection established: " . ($pdo ? 'Yes' : 'No'));

try {
    // トランザクション開始
    $pdo->beginTransaction();

    // 予約の存在確認
    $stmt = $pdo->prepare("SELECT id, status FROM booking_requests WHERE id = :booking_id AND user_id = :user_id");
    $stmt->execute([':booking_id' => $booking_id, ':user_id' => $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        error_log("Booking not found. Booking ID: {$booking_id}, User ID: {$_SESSION['user_id']}");
        throw new Exception('該当する予約が見つかりません。');
    }

    if ($booking['status'] === 'cancelled') {
        error_log("Booking already cancelled. Booking ID: {$booking_id}");
        throw new Exception('この予約は既にキャンセルされています。');
    }

    error_log("Booking found. Current status: " . $booking['status']);

    // 予約のステータスをキャンセル済みに更新
    $stmt = $pdo->prepare("UPDATE booking_requests SET status = 'cancelled', updated_at = NOW() WHERE id = :booking_id AND user_id = :user_id");
    $result = $stmt->execute([':booking_id' => $booking_id, ':user_id' => $_SESSION['user_id']]);

    error_log("Cancel booking - SQL executed. Result: " . ($result ? "Success" : "Failure"));
    error_log("Cancel booking - Affected rows: " . $stmt->rowCount());

    if ($stmt->rowCount() === 0) {
        throw new Exception('予約のキャンセルに失敗しました。');
    }

    // 更新後の状態を確認
    $checkStmt = $pdo->prepare("SELECT status FROM booking_requests WHERE id = :booking_id");
    $checkStmt->execute([':booking_id' => $booking_id]);
    $updatedStatus = $checkStmt->fetchColumn();
    error_log("Updated booking status: " . $updatedStatus);

    // トランザクションをコミット
    $pdo->commit();

    $success = true;
    $message = '予約がキャンセルされました。';
} catch (Exception $e) {
    // トランザクションをロールバック
    $pdo->rollBack();
    $success = false;
    $message = $e->getMessage();
} finally {
    // デバッグ情報をログに記録
    error_log("Booking cancellation result: " . json_encode(['success' => $success, 'message' => $message, 'booking_id' => $booking_id]));
    
    // 結果をJSONで返す
    echo json_encode(['success' => $success, 'message' => $message]);
}