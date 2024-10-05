<?php
require_once('funcs.php');

// 本番環境データベース
$prod_db = "zouuu_zouuu_db";

// 本番環境ホスト
$prod_host = "mysql635.db.sakura.ne.jp";

// 本番環境ID
$prod_id = "zouuu";

// 本番環境PW
$prod_pw = "12345678qju";

// 2. DB接続します
try {
    // ID:'root', Password: xamppは 空白 ''
    $pdo = new PDO('mysql:dbname=' . $prod_db . ';charset=utf8;host=' . $prod_host, $prod_id, $prod_pw);
} catch (PDOException $e) {
    exit('DBConnectError:' . $e->getMessage());
}

// 2. データ取得SQL作成
$stmt = $pdo->prepare("SELECT kanshin1, kanshin2, kanshin3, jusho1, jusho3 FROM member_table");
$status = $stmt->execute();

// データの収集
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
<title>会員情報分析</title>
<!-- CSSファイルのパスを確認してください -->
<link rel="stylesheet" href="http://localhost/gs_code/zouuu/css/range.css">
<link href="http://localhost/gs_code/zouuu/css/bootstrap.min.css" rel="stylesheet">
<link href="http://localhost/gs_code/zouuu/css/style2.css" rel="stylesheet">
<style>
    div { padding: 10px; font-size: 16px; }
    .img {
    display: block;
    margin: 20px auto;
    max-width: 300px;
    height: auto;
    }
    #btnGroup {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 20px;
        margin: 20px 0;
    }
    button {
        width: 200px;
        height: 50px;
        background-color: #0c344e;
        border-radius: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 16px;
        border: none;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    button a {
        color: white;
        text-decoration: none;
    }
    button:hover {
        background-color: #0c344eb7;
    }
    .chart-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-around;
    }
    .chart-box {
        width: 45%;
        min-width: 300px;
        margin: 10px;
    }
    .pie-chart-box {
        width: 30%;
        min-width: 250px;
        margin: 10px;
    }
    .canvas-container {
        width: 100%;
        aspect-ratio: 2 / 1;
    }
    canvas {
        width: 100% !important;
        height: 100% !important;
    }

    @media (max-width: 1600px) {
        .chart-box, .pie-chart-box {
            width: 45%;
        }
    }

    @media (max-width: 1200px) {
        .chart-box, .pie-chart-box {
            width: 48%;
        }
    }

    @media (max-width: 1024px) {
        .chart-box, .pie-chart-box {
            width: 100%;
        }
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.3.2/html2canvas.min.js"></script>
</head>
<body id="main">
<!-- Head[Start] -->
<header id="header-content">
<img src="./img/ZOUUUbanner.jpg" alt="zouuu" class="img">
<h1>会員情報分析</h1>
</header>
<!-- Head[End] -->

<!-- Main[Start] -->
<div id="btnGroup">
    <button><a class="btn" href="select.php">戻る</a></button>
    <button><a class="btn" href="#">会員情報分析</a></button>
    <button onclick="downloadPDF()"><a class="btn" href="#">ダウンロード</a></button>
</div>

<div class="chart-container">
    <div class="chart-box canvas-container"><canvas id="chartJusho1"></canvas></div>
    <div class="chart-box canvas-container"><canvas id="chartJusho3"></canvas></div>
    <div class="pie-chart-box canvas-container"><canvas id="chartKanshin1"></canvas></div>
    <div class="pie-chart-box canvas-container"><canvas id="chartKanshin2"></canvas></div>
    <div class="pie-chart-box canvas-container"><canvas id="chartKanshin3"></canvas></div>
</div>

<script>
    // PHPから取得したデータをJavaScriptで使えるようにする
    const data = <?php echo $jsonData; ?>;
    
    // データを集計する関数
    function aggregateData(data, key) {
        return data.reduce((acc, curr) => {
            acc[curr[key]] = (acc[curr[key]] || 0) + 1;
            return acc;
        }, {});
    }

    const kanshin1Data = aggregateData(data, 'kanshin1');
    const kanshin2Data = aggregateData(data, 'kanshin2');
    const kanshin3Data = aggregateData(data, 'kanshin3');
    const jusho1Data = aggregateData(data, 'jusho1');
    const jusho3Data = aggregateData(data, 'jusho3');

    function createPieChart(ctx, data, title) {
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    label: title,
                    data: Object.values(data),
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: title
                    }
                }
            }
        });
    }

    function createBarChart(ctx, data, title) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    label: title,
                    data: Object.values(data),
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0 // 小数点を表示しない
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: title
                    }
                }
            }
        });
    }

    createBarChart(document.getElementById('chartJusho1').getContext('2d'), jusho1Data, '現住所');
    createBarChart(document.getElementById('chartJusho3').getContext('2d'), jusho3Data, '出身地');
    createPieChart(document.getElementById('chartKanshin1').getContext('2d'), kanshin1Data, '現住所への関心度');
    createPieChart(document.getElementById('chartKanshin2').getContext('2d'), kanshin2Data, '出身地への関心度');
    createPieChart(document.getElementById('chartKanshin3').getContext('2d'), kanshin3Data, '（当市・当区・当町・当村）への関心度');

    async function downloadPDF() {
    const { jsPDF } = window.jspdf;

    // Create a new jsPDF instance
    const pdf = new jsPDF('p', 'pt', 'a4');
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();

    // Get the header content
    const header = document.getElementById('header-content');
    const headerData = await html2canvas(header).then(canvas => canvas.toDataURL('image/png'));
    const headerProps = pdf.getImageProperties(headerData);
    const headerHeight = (headerProps.height * pdfWidth) / headerProps.width;

    // Function to add header to PDF
    function addHeader() {
        pdf.addImage(headerData, 'PNG', 0, 0, pdfWidth, headerHeight);
    }

    // Initial header
    addHeader();

    // Get the chart containers
    const chartContainers = document.querySelectorAll('.canvas-container');

    let yOffset = headerHeight + 10; // Adjust yOffset to include header height and some margin

    for (let i = 0; i < chartContainers.length; i++) {
        const container = chartContainers[i];
        const canvas = container.querySelector('canvas');

        // Use html2canvas to capture the canvas as an image
        const imgData = await html2canvas(canvas).then(canvas => canvas.toDataURL('image/png'));

        // Calculate image dimensions to fit within the PDF page
        const imgProps = pdf.getImageProperties(imgData);
        const imgHeight = (imgProps.height * pdfWidth) / imgProps.width;

        // If image height exceeds remaining space on page, add new page
        if (yOffset + imgHeight > pdfHeight) {
            pdf.addPage();
            addHeader(); // Add header on new page
            yOffset = headerHeight + 10; // Reset yOffset to account for header height and margin
        }

        // Add the image to the PDF
        pdf.addImage(imgData, 'PNG', 0, yOffset, pdfWidth, imgHeight);
        yOffset += imgHeight + 10; // Add some margin between images
    }

    // Save the PDF
    pdf.save('charts.pdf');
}
</script>
</body>
</html>
```
