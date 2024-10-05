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

// CSRFトークンの生成
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// フロンティアIDの取得（セッションから取得するか、適切な方法で取得してください）
$frontier_id = $_SESSION['frontier_id'] ?? 1; // 仮のデフォルト値として1を使用

// 利用不可能な時間枠を取得
$stmt = $pdo->prepare("SELECT * FROM unavailable_slots WHERE frontier_id = :frontier_id");
$stmt->execute([':frontier_id' => $frontier_id]);
$unavailable_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 追加: 日付と時間のフォーマットを調整
$formatted_slots = array_map(function($slot) {
    return [
        'date' => $slot['date'],
        'time' => substr($slot['start_time'], 0, 5) // HH:MM 形式に変換
    ];
}, $unavailable_slots);

// 修正: フォーマットされたスロットをJSONに変換
$unavailable_slots_json = json_encode($formatted_slots);

// JSONエンコードが失敗した場合のエラーメッセージを表示
if ($unavailable_slots_json === false) {
    error_log("JSONエンコードに失敗しました: " . json_last_error_msg());
    $unavailable_slots_json = '[]'; // エラー時は空の配列を設定
} else {
    // デバッグ用にJSONの内容をログに記録
    error_log("デバッグ: " . $unavailable_slots_json);
}

// POSTリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $slots = $input['slots'] ?? [];

    if (empty($slots)) {
        echo json_encode(['success' => false, 'message' => '保存するスロットがありません。']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 既存のスロットを削除
        $stmt = $pdo->prepare("DELETE FROM unavailable_slots WHERE frontier_id = :frontier_id");
        $stmt->execute([':frontier_id' => $frontier_id]);

        // 新しいスロットを挿入
        $stmt = $pdo->prepare("INSERT INTO unavailable_slots (frontier_id, date, start_time, end_time) VALUES (:frontier_id, :date, :start_time, :end_time)");
        foreach ($slots as $slot) {
            $date = $slot['date'];
            $start_time = $slot['time'];
            $end_time = date('H:i:s', strtotime($start_time) + 3600); // 1時間後
            $stmt->execute([
                ':frontier_id' => $frontier_id,
                ':date' => $date,
                ':start_time' => $start_time,
                ':end_time' => $end_time
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NG日程管理 - ZOUUU Platform</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>
        /* スタイルはユーザー側のものを参考に作成 */
        :root {
            --primary-color: #0c344e;
            --secondary-color: #1a73e8;
            --background-color: #f8f9fa;
            --card-background: #ffffff;
            --border-color: #dadce0;
            --text-color: #3c4043;
            --hover-color: #e8f0fe;
        }
        body {
            font-family: 'Roboto', 'Noto Sans JP', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background-color: var(--card-background);
            color: var(--primary-color);
            padding: 15px 0;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
        }
        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        nav ul {
            list-style-type: none;
            margin: 0;
            padding: 0;
            display: flex;
        }
        nav ul li {
            margin-left: 20px;
        }
        nav ul li a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 5px 10px;
            transition: background-color 0.3s;
            border-radius: 4px;
        }
        nav ul li a:hover {
            background-color: var(--hover-color);
        }

        .navbar-custom {
            background-color: #0c344e;
        }
        .navbar-custom .nav-link, .navbar-custom .navbar-brand {
            color: white;
        }

        main {
            flex: 1;
        }
        .card {
            background-color: var(--card-background);
            border-radius: 8px;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
            padding: 20px;
            margin-bottom: 20px;
        }
        h1, h2, h3 {
            color: var(--primary-color);
        }
        h1 {
            text-align: center;
        }
        .btn-container {
            text-align: center;
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 24px;
            background-color: var(--secondary-color);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            text-transform: uppercase;
        }
        .btn:hover {
            background-color: #1765cc;
        }
        .calendar {
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background-color: var(--secondary-color);
            color: white;
        }
        .calendar-body {
            display: flex;
            flex-wrap: wrap;
        }
        .calendar-day {
            width: calc(100% / 7);
            aspect-ratio: 1 / 1;
            border: 1px solid var(--border-color);
            padding: 5px;
            box-sizing: border-box;
        }
        .calendar-day-header {
            font-size: 0.875rem;
            font-weight: 500;
            text-align: center;
            padding-bottom: 5px;
        }
        .calendar-slots {
            display: flex;
            flex-direction: column;
            height: calc(100% - 25px);
            overflow-y: auto;
        }
        .calendar-slot {
            padding: 2px 4px;
            margin-bottom: 2px;
            font-size: 0.75rem;
            cursor: pointer;
            border-radius: 4px;
        }
        .calendar-slot:hover {
            background-color: var(--hover-color);
        }
        .calendar-slot.selected {
            background-color: #f0f0f0; /* 薄いグレー */
            color: white;
        }
        .calendar-slot.unavailable {
            background-color: #f0f0f0;
            color: #999;
            cursor: not-allowed;
        }

        .calendar-slot.unavailable:hover {
        background-color: #e0e0e0; /* ホバー時はより濃いグレー */
        }


        #selected-slots {
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .calendar-day {
                width: calc(100% / 3);
            }
        }
        @media (max-width: 480px) {
            .calendar-day {
                width: 100%;
            }
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
            <li class="breadcrumb-item active" aria-current="page">NG日程管理</li>
        </ol>
    </nav>

    <div class="container mt-5">
        <h1 class="mb-4">NG日程管理</h1>
        <div class="card">
            <div class="card-body">
                <div class="calendar">
                    <div class="calendar-header">
                        <button id="prev-month">&lt;</button>
                        <span id="current-month"></span>
                        <button id="next-month">&gt;</button>
                    </div>
                    <div class="calendar-body">
                        <!-- 日付と時間枠はJavaScriptで生成 -->
                    </div>
                </div>
                <div id="selected-slots" class="mt-4">
                    <h3>選択されたNG日時:</h3>
                    <ul id="selected-slots-list"></ul>
                </div>
                
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-primary" id="save-unavailable-slots">保存する</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer bg-light text-center py-3 mt-4">
        <div class="container">
            <span class="text-muted">Copyright &copy; 2024 <a href="#">ZOUUU</a>. All rights reserved.</span>
        </div>
    </footer>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // 利用不可能な時間枠のデータを PHP から受け取る
    const unavailableSlots = <?php echo $unavailable_slots_json; ?>;

    // デバッグ用にunavailableSlotsの内容をコンソールに出力
    console.log('デバッグ: unavailableSlots', unavailableSlots);

    document.addEventListener('DOMContentLoaded', function() {
        const calendarBody = document.querySelector('.calendar-body');
        const selectedSlotsList = document.getElementById('selected-slots-list');
        const currentMonthElement = document.getElementById('current-month');
        const prevMonthButton = document.getElementById('prev-month');
        const nextMonthButton = document.getElementById('next-month');
        let selectedSlots = [...unavailableSlots]; // 初期値として既存のスロットを設定
        let currentDate = new Date();

        function generateCalendar(date) {
            calendarBody.innerHTML = '';
            currentMonthElement.textContent = date.toLocaleString('ja-JP', { year: 'numeric', month: 'long' });

            const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
            const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);

            for (let i = 0; i < firstDay.getDay(); i++) {
                calendarBody.appendChild(createEmptyDay());
            }

            for (let i = 1; i <= lastDay.getDate(); i++) {
                calendarBody.appendChild(createDay(new Date(date.getFullYear(), date.getMonth(), i)));
            }

            updateCalendarDisplay();
        }

        function createEmptyDay() {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            return dayElement;
        }

        function createDay(date) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            dayElement.innerHTML = `
                <div class="calendar-day-header">${date.getDate()}</div>
                <div class="calendar-slots">
                    ${generateTimeSlots(date)}
                </div>
            `;
            return dayElement;
        }

        function generateTimeSlots(date) {
            let slots = '';
            for (let hour = 9; hour < 18; hour++) {
                const time = `${hour.toString().padStart(2, '0')}:00`;
                slots += `<div class="calendar-slot" data-date="${date.toISOString().split('T')[0]}" data-time="${time}">${time}</div>`;
            }
            return slots;
        }

        function isSlotUnavailable(date, time) {
            return selectedSlots.some(slot => slot.date === date && slot.time === time);
        }

        function updateCalendarDisplay() {
            document.querySelectorAll('.calendar-slot').forEach(slotElement => {
                const date = slotElement.dataset.date;
                const time = slotElement.dataset.time;
                if (isSlotUnavailable(date, time)) {
                    slotElement.classList.add('unavailable');
                } else {
                    slotElement.classList.remove('unavailable');
                }
            });
            updateSelectedSlotsList();
        }

        function updateSelectedSlotsList() {
            selectedSlotsList.innerHTML = selectedSlots.map(slot => `<li>${slot.date} ${slot.time}</li>`).join('');
        }

        calendarBody.addEventListener('click', function(e) {
            if (e.target.classList.contains('calendar-slot')) {
                const slotElement = e.target;
                const date = slotElement.dataset.date;
                const time = slotElement.dataset.time;
                const dateTime = { date, time };

                const index = selectedSlots.findIndex(slot => slot.date === date && slot.time === time);
                if (index !== -1) {
                    selectedSlots.splice(index, 1);
                } else {
                    selectedSlots.push(dateTime);
                }

                updateCalendarDisplay();
            }
        });

        prevMonthButton.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            generateCalendar(currentDate);
        });

        nextMonthButton.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            generateCalendar(currentDate);
        });

        document.getElementById('save-unavailable-slots').addEventListener('click', function() {
            if (selectedSlots.length === 0) {
                alert('少なくとも1つのNG日程を選択してください。');
                return;
            }

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                },
                body: JSON.stringify({ slots: selectedSlots }),
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('NG日程が保存されました。');
                    location.reload();
                } else {
                    alert('NG日程の保存に失敗しました: ' + data.message);
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                alert('NG日程の保存中にエラーが発生しました。');
            });
        });

        generateCalendar(currentDate);
    });
</script>
</body>
</html>
