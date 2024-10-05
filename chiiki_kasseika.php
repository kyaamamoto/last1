<?php
session_start(); // セッションの開始

require_once 'session_config.php';
require_once 'security_headers.php';
require_once 'funcs.php';

// ログイン状態の確認
$is_logged_in = isset($_SESSION['user_id']);

// ユーザー情報の取得（ログイン時のみ）
if ($is_logged_in) {
    $user = getUserInfo($_SESSION['user_id']);
}

// データベース接続
$pdo = db_conn();

// フロンティア情報の取得（未ログイン時も表示可能）
$category = '地域活性化';
$stmt = $pdo->prepare("SELECT * FROM gs_chiiki_frontier WHERE category = :category ORDER BY created_at DESC");
$stmt->bindValue(':category', $category, PDO::PARAM_STR);
$status = $stmt->execute();

$frontiers = [];
if ($status == false) {
    // SQLエラーがあれば表示
    $error = $stmt->errorInfo();
    exit("ErrorQuery:" . $error[2]);
} else {
    // データを配列に格納
    $frontiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// XSS対策のためのエスケープ関数
if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

// カードを生成する関数
function generateFrontierCard($frontier, $is_logged_in) {
    $html = '<div class="card">';
    $html .= '<img src="' . h($frontier['image_url']) . '" alt="' . h($frontier['name']) . '">';
    $html .= '<div class="card-content">';
    $html .= '<h3>' . h($frontier['name']) . '</h3>';
    $html .= '<p>' . nl2br(h($frontier['description'])) . '</p>';
    $html .= '<div class="tags">';
    $tags = explode(',', $frontier['tags']);
    foreach ($tags as $tag) {
        $html .= '<span class="tag">' . h(trim($tag)) . '</span>';
    }
    $html .= '</div>';

    // ログイン時のみ「学習する」ボタンと「体験申込」ボタンを表示
    if ($is_logged_in) {
        $html .= '<div class="button-container mt-3">';
        $html .= '<a href="user_learning.php?frontier_id=' . h($frontier['id']) . '" class="btn btn-success learn-button">学習する</a>';
        $html .= '<a href="experience-application-form.php?frontier_id=' . h($frontier['id']) . '" class="btn btn-primary apply-button">体験申込</a>';
        $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>地域活性化フロンティア - ZOUUU</title>
    <link rel="icon" type="image/png" href="./img/favicon.ico">
    <!-- フォントやスタイルシートの読み込み -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./css/education_card.css">

    <style>
        nav ul {
            list-style-type: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        nav ul li {
            margin-left: 30px;
        }
        nav ul li a {
            text-decoration: none;
            color: #333;
            font-weight: 700;
            transition: color 0.3s ease;
        }
        nav ul li a:hover {
            color: #0c344e;
        }
        .btn-success {
            background-color: #28a745;
            color: #fff;
            padding: 8px 16px;
            text-align: center;
            border-radius: 5px;
            display: inline-block;
            transition: background-color 0.3s ease;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-success:hover {
            background-color: #218838;
            text-decoration: none;
        }
        .button-container {
            margin-top: 15px;
        }

        .button-container .btn {
            margin-right: 10px; /* ボタン間のスペースを確保 */
        }

        .button-container .btn:last-child {
            margin-right: 0; /* 最後のボタンにはマージンを追加しない */
        }

        .btn-primary {
            background-color: #007bff;
            color: #fff;
            padding: 8px 16px;
            text-align: center;
            border-radius: 5px;
            display: inline-block;
            transition: background-color 0.3s ease;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <!-- ヘッダー部分 -->
    <?php if ($is_logged_in): ?>
        <header>
        <div class="container">
            <nav>
                <div class="logo">ZOUUU</div>
                <div class="welcome">
                    <h1>ようこそ、<?= h($user['name']) ?>さん</h1>
                </div>
                <ul>
                    <li><a href="education.php">コース一覧</a></li>
                    <li><a href="mypage.php">マイページ</a></li>
                    <li><a href="logoutmypage.php">ログアウト</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <?php else: ?>
        <header>
        <div class="container">
            <nav>
                <div class="logo">ZOUUU</div>
                <ul>
                    <li><a href="education.html">Home</a></li>
                    <li><a href="education.html#about">初めての方へ</a></li>
                    <li><a href="education.html#contact">お問い合わせ</a></li>
                    <li><a href="login_holder.php">ログイン</a></li>
                    <li><a href="mypage_entry.php" class="btn-register">会員登録</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <?php endif; ?>

    <!-- パンくずリスト -->
    <nav aria-label="breadcrumb" class="mt-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="education.html">ホーム</a></li>
            <li class="breadcrumb-item active" aria-current="page">地域活性化</li>
        </ol>
    </nav>

    <section id="frontiers" class="section">
        <div class="container">
            <h2>地域フロンティア<br>- 地域活性化に取り組む実践者たち -</h2>

            <div class="card-container">
                <?php
                // フロンティア情報を表示
                if (!empty($frontiers)) {
                    foreach ($frontiers as $frontier) {
                        echo generateFrontierCard($frontier, $is_logged_in);
                    }
                } else {
                    echo '<p>現在、このカテゴリにはフロンティアが登録されていません。</p>';
                }
                ?>
            </div>

        </div>
    </section>

    <footer>
        <div class="container">
            <p class="footer-logo">ZOUUU</p>
            <small>&copy; 2024 ZOUUU. All rights reserved.</small>
        </div>
    </footer>

    <!-- 必要なスクリプトの読み込み -->
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            const cards = document.querySelectorAll('.card');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeInUp 1s ease forwards';
                    }
                });
            }, { threshold: 0.1 });

            cards.forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</body>
</html>