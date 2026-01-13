<?php
/**
 * FEEDBACK_STATS.PHP
 * Statistieken overzicht met Apache Echarts.
 * Focus: Gemiddelde OTD & FTR scores voor 8, 40 en 80 ritten.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

// 1. BEVEILIGING
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$page_title = APP_TITLE;

// 2. DATA OPHALEN
// We initialiseren de array voor de 3 meetmomenten
$stats = [
    '8 ritten'  => ['count' => 0, 'otd' => 0, 'ftr' => 0],
    '40 ritten' => ['count' => 0, 'otd' => 0, 'ftr' => 0],
    '80 ritten' => ['count' => 0, 'otd' => 0, 'ftr' => 0],
];

try {
    // We halen alles in één keer op met een GROUP BY
    $sql = "SELECT 
                review_moment,
                COUNT(*) as total_rows,
                AVG(CAST(REPLACE(otd_score, '%', '') AS DECIMAL(10,2))) as avg_otd,
                AVG(CAST(REPLACE(ftr_score, '%', '') AS DECIMAL(10,2))) as avg_ftr
            FROM feedback_forms 
            WHERE review_moment IN ('8 ritten', '40 ritten', '80 ritten') 
            AND otd_score != '' 
            AND ftr_score != ''
            GROUP BY review_moment";
            
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Resultaten mappen naar onze stats array
    foreach ($results as $row) {
        $moment = strtolower($row['review_moment']); // Zekerheidje voor matching
        
        // Match op de sleutels in $stats (die zijn lowercase '8 ritten' etc in keys hierboven als we willen, 
        // maar in DB staat waarschijnlijk '8 ritten'. We matchen exact of via switch)
        
        // Kleine normalize slag voor de zekerheid
        if (strpos($row['review_moment'], '8 ') === 0) $key = '8 ritten';
        elseif (strpos($row['review_moment'], '40') === 0) $key = '40 ritten';
        elseif (strpos($row['review_moment'], '80') === 0) $key = '80 ritten';
        else $key = null;

        if ($key) {
            $stats[$key]['count'] = $row['total_rows'];
            $stats[$key]['otd']   = round((float)$row['avg_otd'], 1);
            $stats[$key]['ftr']   = round((float)$row['avg_ftr'], 1);
        }
    }

} catch (PDOException $e) {
    // Foutopsporing
}

// Data voorbereiden voor JavaScript
$chartDataOTD = [
    $stats['8 ritten']['otd'], 
    $stats['40 ritten']['otd'], 
    $stats['80 ritten']['otd']
];
$chartDataFTR = [
    $stats['8 ritten']['ftr'], 
    $stats['40 ritten']['ftr'], 
    $stats['80 ritten']['ftr']
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- THEME STYLING --- */
        :root { --brand-color: #0176d3; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* Sidebar & Layout */
        .sidebar { width: 240px; background: #1a2233; color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { height: 60px; padding: 0 20px; display: flex; align-items: center; background: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { max-height: 40px; }
        .nav-list { list-style: none; padding: 20px 0; margin: 0; }
        .nav-item a { display: flex; align-items: center; padding: 12px 20px; color: #b0b6c3; text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item a:hover, .nav-item a.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--brand-color); }
        .nav-item .material-icons-outlined { margin-right: 12px; }

        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; position: sticky; top: 0; z-index: 10; flex-shrink: 0; }
        .page-body { padding: 24px; max-width: 1400px; margin: 0 auto; width: 100%; }

        /* Cards */
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 2px rgba(0,0,0,0.1); margin-bottom: 24px; padding: 20px; }
        .chart-container { width: 100%; height: 450px; }
        
        /* Stats Grid */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: white; border: 1px solid var(--border-color); border-radius: 4px; padding: 20px; text-align: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .stat-value { font-size: 28px; font-weight: 300; color: var(--brand-color); }
        .stat-label { font-size: 12px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-top: 5px; letter-spacing: 0.5px; }

        h1, h3 { color: var(--text-main); font-weight: 600; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-body">
            <h1 style="margin-top: 0; font-size: 24px;">Prestatie statistieken</h1>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">
                Analyse van de gemiddelde scores per meetmoment.
            </p>

            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $stats['8 ritten']['count']; ?></div>
                    <div class="stat-label">Gesprekken 8 ritten</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $stats['40 ritten']['count']; ?></div>
                    <div class="stat-label">Gesprekken 40 ritten</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $stats['80 ritten']['count']; ?></div>
                    <div class="stat-label">Gesprekken 80 ritten</div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top: 0; margin-bottom: 20px;">Gemiddelde scores per type</h3>
                <div id="comparison-chart" class="chart-container"></div>
            </div>
            
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var chartDom = document.getElementById('comparison-chart');
            var myChart = echarts.init(chartDom);
            var option;

            option = {
                tooltip: {
                    trigger: 'axis',
                    axisPointer: { type: 'shadow' }
                },
                legend: {
                    data: ['OTD score', 'FTR score'],
                    bottom: 0
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '10%',
                    containLabel: true
                },
                xAxis: {
                    type: 'category',
                    data: ['8 ritten', '40 ritten', '80 ritten'],
                    axisTick: { alignWithLabel: true }
                },
                yAxis: {
                    type: 'value',
                    max: 100,
                    axisLabel: { formatter: '{value}%' }
                },
                series: [
                    {
                        name: 'OTD score',
                        type: 'bar',
                        data: <?php echo json_encode($chartDataOTD); ?>,
                        itemStyle: { color: '#0176d3' },
                        label: { show: true, position: 'top', formatter: '{c}%' }
                    },
                    {
                        name: 'FTR score',
                        type: 'bar',
                        data: <?php echo json_encode($chartDataFTR); ?>,
                        itemStyle: { color: '#10b981' },
                        label: { show: true, position: 'top', formatter: '{c}%' }
                    }
                ]
            };

            option && myChart.setOption(option);
            
            window.addEventListener('resize', function() {
                myChart.resize();
            });
        });
    </script>
</body>
</html>