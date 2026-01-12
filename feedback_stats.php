<?php
/**
 * FEEDBACK_STATS.PHP
 * Statistieken overzicht met Apache Echarts.
 * Focus: Gemiddelde OTD & FTR scores voor '8 ritten' gesprekken.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

// 1. BEVEILIGING
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$page_title = 'Statistieken';

// 2. DATA OPHALEN
// We halen de scores op voor gesprekken met review_moment = '8 ritten'
// We filteren lege scores eruit.
$avgOTD = 0;
$avgFTR = 0;
$count = 0;

try {
    // SQL om percentages schoon te maken en gemiddelde te berekenen
    // We gebruiken REPLACE om het '%' teken weg te halen en casten naar DECIMAL
    $sql = "SELECT 
                COUNT(*) as total_rows,
                AVG(CAST(REPLACE(otd_score, '%', '') AS DECIMAL(10,2))) as avg_otd,
                AVG(CAST(REPLACE(ftr_score, '%', '') AS DECIMAL(10,2))) as avg_ftr
            FROM feedback_forms 
            WHERE review_moment = '8 ritten' 
            AND otd_score != '' 
            AND ftr_score != ''";
            
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $count = $result['total_rows'];
        // Afronden op 1 decimaal
        $avgOTD = round((float)$result['avg_otd'], 1);
        $avgFTR = round((float)$result['avg_ftr'], 1);
    }

} catch (PDOException $e) {
    // Foutopsporing (in productie loggen, nu stilhouden of tonen)
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> - <?php echo defined('APP_TITLE') ? APP_TITLE : 'App'; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- THEME STYLING (Consistent met Dashboard) --- */
        :root { --brand-color: #0176d3; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* Sidebar */
        .sidebar { width: 240px; background: #1a2233; color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { height: 60px; padding: 0 20px; display: flex; align-items: center; background: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { max-height: 40px; }
        .nav-list { list-style: none; padding: 20px 0; margin: 0; }
        .nav-item a { display: flex; align-items: center; padding: 12px 20px; color: #b0b6c3; text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item a:hover, .nav-item a.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--brand-color); }
        .nav-item .material-icons-outlined { margin-right: 12px; }

        /* Content */
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; position: sticky; top: 0; z-index: 10; flex-shrink: 0; }
        .page-body { padding: 24px; max-width: 1400px; margin: 0 auto; width: 100%; }

        /* Cards */
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 2px rgba(0,0,0,0.1); margin-bottom: 24px; padding: 20px; }
        .chart-container { width: 100%; height: 400px; }
        
        .stat-summary { display: flex; gap: 40px; margin-bottom: 30px; }
        .stat-box { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; text-align: center; }
        .stat-value { font-size: 32px; font-weight: 300; color: var(--brand-color); }
        .stat-label { font-size: 13px; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-top: 5px; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-body">
            <h1 style="margin-top: 0; font-size: 24px;">Prestatie Statistieken</h1>
            <p style="color: var(--text-secondary); margin-bottom: 24px;">
                Analyse van de <strong>8 ritten</strong> gesprekken.
            </p>

            <div class="stat-summary">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $count; ?></div>
                    <div class="stat-label">Aantal Gesprekken</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $avgOTD; ?>%</div>
                    <div class="stat-label">Gemiddelde OTD</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $avgFTR; ?>%</div>
                    <div class="stat-label">Gemiddelde FTR</div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top: 0; color: #333;">Gemiddelde Scores (8 Ritten)</h3>
                <div id="main-chart" class="chart-container"></div>
            </div>
            
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var chartDom = document.getElementById('main-chart');
            var myChart = echarts.init(chartDom);
            var option;

            // Data vanuit PHP
            const avgOTD = <?php echo $avgOTD; ?>;
            const avgFTR = <?php echo $avgFTR; ?>;

            option = {
                tooltip: {
                    trigger: 'axis',
                    axisPointer: { type: 'shadow' },
                    formatter: '{b}: {c}%'
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: {
                    type: 'category',
                    data: ['OTD Score', 'FTR Score'],
                    axisTick: { alignWithLabel: true }
                },
                yAxis: {
                    type: 'value',
                    max: 100, // Percentage is max 100
                    axisLabel: { formatter: '{value}%' }
                },
                series: [
                    {
                        name: 'Gemiddelde',
                        type: 'bar',
                        barWidth: '40%',
                        data: [
                            {
                                value: avgOTD,
                                itemStyle: { color: '#0176d3' } // Blauw voor OTD
                            },
                            {
                                value: avgFTR,
                                itemStyle: { color: '#10b981' } // Groen voor FTR
                            }
                        ],
                        label: {
                            show: true,
                            position: 'top',
                            formatter: '{c}%',
                            fontWeight: 'bold'
                        }
                    }
                ]
            };

            option && myChart.setOption(option);
            
            // Responsief maken
            window.addEventListener('resize', function() {
                myChart.resize();
            });
        });
    </script>
</body>
</html>