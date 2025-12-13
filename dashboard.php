<?php
/**
 * DASHBOARD.PHP
 * - Features: Live Search Popup, ECharts Grafiek, Inline Editing
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// 1. BEVEILIGING
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- AJAX HANDLER VOOR LIVE SUGGESTIES (ZOEKBALK) ---
if (isset($_GET['ajax_search'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $term = trim($_GET['ajax_search']);
    if (strlen($term) < 1) { echo json_encode([]); exit; }
    try {
        $sql = "SELECT d.id as driver_id, d.name, d.employee_id, f.id as form_id, f.form_date, f.status 
                FROM feedback_forms f
                JOIN drivers d ON f.driver_id = d.id
                WHERE d.name LIKE ? OR d.employee_id LIKE ? 
                ORDER BY f.created_at DESC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $like = "%$term%";
        $stmt->execute([$like, $like]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

// 2. DATA VOOR GRAFIEK (PER WEEK)
// We halen de data op van de laatste 12 weken
$chartWeeks = [];
$chartCounts = [];
try {
    $sqlChart = "SELECT YEARWEEK(form_date, 1) as week_nr, COUNT(*) as total 
                 FROM feedback_forms 
                 GROUP BY week_nr 
                 ORDER BY week_nr ASC 
                 LIMIT 12";
    $stmtChart = $pdo->query($sqlChart);
    while($row = $stmtChart->fetch(PDO::FETCH_ASSOC)) {
        // Weeknummer formatteren naar leesbaar (bv. Week 42)
        $w = substr($row['week_nr'], -2);
        $chartWeeks[] = "Week " . $w;
        $chartCounts[] = $row['total'];
    }
} catch (PDOException $e) {
    // Fallback als er iets misgaat
}

// 3. OPSLAAN LOGICA (POST - INLINE EDITS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_user_id'], $_POST['form_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE feedback_forms SET assigned_to_user_id = ? WHERE id = ?");
            $val = !empty($_POST['assign_user_id']) ? $_POST['assign_user_id'] : null;
            $stmt->execute([$val, $_POST['form_id']]);
            header("Location: dashboard.php?msg=Succesvol toegewezen"); exit;
        } catch (PDOException $e) {}
    }
    if (isset($_POST['update_status'], $_POST['form_id'], $_POST['new_status'])) {
        try {
            $stmt = $pdo->prepare("UPDATE feedback_forms SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['new_status'], $_POST['form_id']]);
            header("Location: dashboard.php?msg=Status succesvol geupdated"); exit;
        } catch (PDOException $e) {}
    }
}

// 4. DATA & STATISTIEKEN
$stats = ['drivers' => 0, 'open_feedback' => 0, 'closed_feedback' => 0];
try {
    $stats['drivers'] = $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
    $stats['open_feedback'] = $pdo->query("SELECT COUNT(*) FROM feedback_forms WHERE status = 'open'")->fetchColumn();
    $stats['closed_feedback'] = $pdo->query("SELECT COUNT(*) FROM feedback_forms WHERE status = 'completed'")->fetchColumn();
} catch (PDOException $e) {}

$teamleads = $pdo->query("SELECT id, email, first_name, last_name FROM users ORDER BY first_name ASC")->fetchAll();
$msg = $_GET['msg'] ?? '';

// --- PAGINERING LOGICA ---
$limit = 8;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$sqlBody = " FROM feedback_forms f
             JOIN drivers d ON f.driver_id = d.id
             JOIN users u_creator ON f.created_by_user_id = u_creator.id
             LEFT JOIN users u_assigned ON f.assigned_to_user_id = u_assigned.id";

try {
    $stmtCount = $pdo->query("SELECT COUNT(*) " . $sqlBody);
    $totalRows = $stmtCount->fetchColumn();
} catch (PDOException $e) { $totalRows = 0; }

$totalPages = ceil($totalRows / $limit);
if ($page > $totalPages && $totalPages > 0) { $page = $totalPages; }
$offset = ($page - 1) * $limit;

$sqlFields = "SELECT 
            f.id, f.form_date, f.review_moment, f.status, f.assigned_to_user_id, f.created_at,
            d.name as driver_name, d.employee_id,
            u_creator.email as creator_email, 
            u_assigned.email as assigned_email,
            u_assigned.first_name as assigned_first,
            u_assigned.last_name as assigned_last";

$sqlFinal = $sqlFields . $sqlBody . " ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sqlFinal);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$recentActivities = $stmt->fetchAll();


// --- HTML GENEREREN VOOR TABEL (HERGEBRUIK) ---
ob_start();
if (empty($recentActivities)): ?>
    <tr>
        <td colspan="7" style="text-align: center; padding: 20px; color: #999;">Nog geen dossiers aangemaakt.</td>
    </tr>
<?php else: 
    foreach ($recentActivities as $row): 
        $dateCreated = new DateTime($row['created_at']);
        $now = new DateTime();
        $interval = $now->diff($dateCreated);
        
        // Dit is voor het uitroepteken icoontje (als het dossier lang open staat na aanmaak)
        $isOverdue = ($row['status'] === 'open' && $interval->days > 14);

        // --- AANPASSING HIER ---
        // Check of de planningsdatum (form_date) in het verleden ligt
        $isDateExpired = ($row['form_date'] < date('Y-m-d'));

        // Pas knipperen als status 'open' is EN de datum verlopen is
        $rowClass = ($row['status'] === 'open' && $isDateExpired) ? 'blink-row' : '';
    ?>
        <tr class="<?php echo $rowClass; ?>">
            <td class="<?php echo $isOverdue ? 'text-urgent' : ''; ?>">
                <?php echo htmlspecialchars($row['form_date']); ?>
                <?php if($isOverdue): ?>
                    <span class="material-icons-outlined" style="font-size:14px; vertical-align:middle;" title="Reeds <?php echo $interval->days; ?> dagen open">warning</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="feedback_view.php?id=<?php echo $row['id']; ?>" style="color:var(--brand-color); text-decoration:none; font-weight:700;">
                    <?php echo htmlspecialchars($row['driver_name']); ?>
                </a>
                <div style="font-size:11px; color:#999;"><?php echo htmlspecialchars($row['employee_id'] ?? ''); ?></div>
            </td>
            <td><?php echo htmlspecialchars($row['review_moment'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($row['creator_email']); ?></td>
            <td>
                <div id="view-status-<?php echo $row['id']; ?>" class="view-mode">
                    <span class="status-badge <?php echo ($row['status'] === 'open') ? 'bg-open' : 'bg-completed'; ?>">
                        <?php echo ucfirst($row['status']); ?>
                    </span>
                    <button class="icon-btn" onclick="toggleEdit('status', <?php echo $row['id']; ?>)"><span class="material-icons-outlined" style="font-size:14px;">edit</span></button>
                </div>
                <form method="POST" id="edit-status-<?php echo $row['id']; ?>" class="edit-mode">
                    <input type="hidden" name="form_id" value="<?php echo $row['id']; ?>">
                    <input type="hidden" name="update_status" value="1">
                    <select name="new_status" class="inline-select">
                        <option value="open">Open</option>
                        <option value="completed">Completed</option>
                    </select>
                    <button type="submit" class="icon-btn save"><span class="material-icons-outlined">check</span></button>
                    <button type="button" class="icon-btn" onclick="toggleEdit('status', <?php echo $row['id']; ?>)"><span class="material-icons-outlined">close</span></button>
                </form>
            </td>
            <td>
                <div id="view-assign-<?php echo $row['id']; ?>" class="view-mode">
                    <?php 
                        if (!empty($row['assigned_to_user_id'])) {
                            $displayName = (!empty($row['assigned_first'])) 
                                ? trim($row['assigned_first'] . ' ' . $row['assigned_last']) 
                                : $row['assigned_email'];
                        } else {
                            $displayName = '<span style="color:#bbb;">--</span>';
                        }
                        echo $displayName;
                    ?>
                    <button class="icon-btn" onclick="toggleEdit('assign', <?php echo $row['id']; ?>)"><span class="material-icons-outlined" style="font-size:14px;">edit</span></button>
                </div>
                <form method="POST" id="edit-assign-<?php echo $row['id']; ?>" class="edit-mode">
                    <input type="hidden" name="form_id" value="<?php echo $row['id']; ?>">
                    <select name="assign_user_id" class="inline-select">
                        <option value="">-- Geen --</option>
                        <?php foreach ($teamleads as $lead): 
                            $optName = (!empty($lead['first_name'])) ? trim($lead['first_name'].' '.$lead['last_name']) : $lead['email'];
                        ?>
                            <option value="<?php echo $lead['id']; ?>" <?php if($row['assigned_to_user_id'] == $lead['id']) echo 'selected'; ?>><?php echo htmlspecialchars($optName); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="icon-btn save"><span class="material-icons-outlined">check</span></button>
                    <button type="button" class="icon-btn" onclick="toggleEdit('assign', <?php echo $row['id']; ?>)"><span class="material-icons-outlined">close</span></button>
                </form>
            </td>
            <td style="text-align:right;">
                <a href="feedback_view.php?id=<?php echo $row['id']; ?>" style="color:#999;"><span class="material-icons-outlined">visibility</span></a>
            </td>
        </tr>
    <?php endforeach; 
endif;
$rowsHtml = ob_get_clean();

// B. Pagination HTML
ob_start();
$qs = $_GET; unset($qs['ajax_pagination']); 
?>
    <?php if ($page > 1): $qs['page'] = $page - 1; ?>
        <a href="?<?php echo http_build_query($qs); ?>">&laquo; Vorige</a>
    <?php else: ?>
        <span class="disabled">&laquo; Vorige</span>
    <?php endif; ?>
    <span class="current" style="margin: 0 10px; color: #999;">Pagina <?php echo $page; ?> van <?php echo max(1, $totalPages); ?></span>
    <?php if ($page < $totalPages): $qs['page'] = $page + 1; ?>
        <a href="?<?php echo http_build_query($qs); ?>">Volgende &raquo;</a>
    <?php else: ?>
        <span class="disabled">Volgende &raquo;</span>
    <?php endif; ?>
<?php 
$paginationHtml = ob_get_clean();

// AJAX RESPONSE als dit een fetch request is
if (isset($_GET['ajax_pagination'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    echo json_encode(['rows' => $rowsHtml, 'pagination' => $paginationHtml]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?php echo defined('APP_TITLE') ? APP_TITLE : 'Dashboard'; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- THEME --- */
        :root { --brand-color: #0176d3; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; --border-color: #dddbda; --success-bg: #d1fae5; --success-text: #065f46; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* Layout */
        .sidebar { width: 240px; background: #1a2233; color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { height: 60px; padding: 0 20px; display: flex; align-items: center; background: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { max-height: 40px; }
        .nav-list { list-style: none; padding: 20px 0; margin: 0; }
        .nav-item a { display: flex; align-items: center; padding: 12px 20px; color: #b0b6c3; text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item a:hover, .nav-item a.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--brand-color); }
        .nav-item .material-icons-outlined { margin-right: 12px; }

        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; position: sticky; top: 0; z-index: 10; flex-shrink: 0; }
        .page-body { padding: 24px; max-width: 1400px; margin: 0 auto; width: 100%; flex-grow: 1; }

        /* Cards & Grid */
        .grid-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 2px rgba(0,0,0,0.1); margin-bottom: 24px; }
        .card-body { padding: 16px; }
        .kpi-value { font-size: 32px; font-weight: 300; } .kpi-label { font-size: 13px; color: var(--text-secondary); }
        .alert-toast { background: var(--success-bg); color: var(--success-text); padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        
        /* Filters Toolbar */
        .filter-toolbar { padding: 12px 16px; background: #fcfcfc; border-bottom: 1px solid var(--border-color); display:flex; align-items:center; }

        /* Table */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size: 11px; }
        td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        /* Pagination */
        .pagination { padding: 15px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; align-items: center; gap: 10px; font-size: 13px; }
        .pagination a { text-decoration: none; padding: 6px 12px; border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-main); transition: 0.2s; }
        .pagination a:hover { background: #f3f2f2; }
        .pagination .current { color: var(--text-secondary); font-weight: 600; }
        .pagination .disabled { opacity: 0.5; pointer-events: none; color: #999; padding: 6px 12px; border: 1px solid #eee; border-radius: 4px; }

        /* Inline Edit */
        .view-mode { display: flex; align-items: center; gap: 8px; }
        .edit-mode { display: none; align-items: center; gap: 4px; }
        .icon-btn { cursor: pointer; color: #999; font-size: 16px; transition: color 0.2s; background: none; border: none; padding: 2px; }
        .icon-btn:hover { color: var(--brand-color); }
        .status-badge { padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .bg-open { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
        .bg-completed { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .inline-select { padding: 4px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
        .text-urgent { color: #c53030; font-weight: 700; }

        /* SEARCH OVERLAY (POPUP) */
        #search-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 9999; display: none; justify-content: center; padding-top: 15vh; backdrop-filter: blur(2px); }
        .spotlight-container { width: 100%; max-width: 600px; background: white; border-radius: 8px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; display: flex; flex-direction: column; max-height: 60vh; animation: slideDown 0.2s ease-out; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        
        .spotlight-input-wrapper { display: flex; align-items: center; padding: 16px 20px; border-bottom: 1px solid #eee; }
        #spotlight-input { border: none; font-size: 18px; width: 100%; outline: none; background: transparent; color: var(--text-main); }
        #spotlight-results { overflow-y: auto; }
        .result-item { padding: 12px 20px; border-bottom: 1px solid #f7f7f7; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-size: 14px; transition: background 0.1s; }
        .result-item:hover { background: #f0f9ff; border-left: 4px solid var(--brand-color); padding-left: 16px; }
        .no-results { padding: 20px; color: #999; text-align: center; font-style: italic; }
        .key-hint { background: #eee; padding: 2px 6px; border-radius: 4px; font-size: 11px; color: #666; border: 1px solid #ddd; margin-left: 8px; }

        /* Chart Container */
        #chart-container { width: 100%; height: 350px; }


        /* --- ANIMATIE VOOR OPEN DOSSIERS --- */
@keyframes softBlinkRed {
    0% { background-color: #ffffff; }
    50% { background-color: #ffe5e5; } /* Zacht rood */
    100% { background-color: #ffffff; }
}

.blink-row {
    animation: softBlinkRed 3s infinite ease-in-out;
}
    </style>
</head>
<body>

    <div id="search-overlay">
        <div class="spotlight-container">
            <div class="spotlight-input-wrapper">
                <span class="material-icons-outlined" style="margin-right:12px; color:var(--brand-color); font-size:24px;">search</span>
                <input type="text" id="spotlight-input" placeholder="Zoek op naam of personeelsnummer..." autocomplete="off">
                <span class="material-icons-outlined" style="cursor:pointer; color:#ccc; font-size: 20px;" onclick="closeSearch()">close</span>
            </div>
            <div id="spotlight-results"></div>
            <div style="background:#fafafa; padding:8px 20px; font-size:11px; color:#999; border-top:1px solid #eee; display:flex; justify-content:space-between;">
                <span>Gebruik pijltjestoetsen om te navigeren</span>
                <span>ESC om te sluiten</span>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-body">

            <?php if ($msg): ?>
                <div class="alert-toast"><span class="material-icons-outlined">info</span> Update: <?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <<div class="grid-row">
    <div class="card">
        <div class="card-body">
            <div class="kpi-value"><?php echo $stats['drivers']; ?></div>
            <div class="kpi-label" style="background-color: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 12px; display: inline-block; font-weight: 600;">
                Aantal dossiers
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="kpi-value"><?php echo $stats['open_feedback']; ?></div>
            <div class="kpi-label" style="background-color: #fffbeb; color: #b45309; padding: 2px 8px; border-radius: 12px; display: inline-block; font-weight: 600;">
                Openstaande dossiers
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="kpi-value"><?php echo $stats['closed_feedback']; ?></div>
            <div class="kpi-label" style="background-color: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 12px; display: inline-block; font-weight: 600;">
                Gesloten dossiers
            </div>
        </div>
    </div>
</div>

            <div class="card">
                <div class="filter-toolbar" style="justify-content: space-between; align-items: center;">
    <h3 style="margin: 0; font-size: 18px; color: var(--brand-dark);">Recente dossiers</h3>
    
    <a href="feedback_form.php" style="background-color: var(--brand-color); color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px;">
        <span class="material-icons-outlined" style="font-size: 18px;">add</span>
        Nieuw gesprek
    </a>
</div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Chauffeur</th>
                                <th>Moment</th>
                                <th>Gemaakt Door</th>
                                <th>Status</th>
                                <th>Toegewezen Aan</th>
                                <th style="text-align:right;">Actie</th>
                            </tr>
                        </thead>
                        <tbody id="feedback-table-body">
                            <?php echo $rowsHtml; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination" id="pagination-container">
                    <?php echo $paginationHtml; ?>
                </div>
            </div>

            <div class="card">
                <div class="filter-toolbar">
                    <div style="font-weight:700; font-size:14px;">Aantal dossiers per week</div>
                </div>
                <div class="card-body">
                    <div id="chart-container"></div>
                </div>
            </div>

        </div>

        <?php include __DIR__ . '/includes/footer.php'; ?>

    </main>

    <script>
        function toggleEdit(type, id) {
            const viewEl = document.getElementById(`view-${type}-${id}`);
            const editEl = document.getElementById(`edit-${type}-${id}`);
            if (viewEl.style.display === 'none') {
                viewEl.style.display = 'flex'; editEl.style.display = 'none';
            } else {
                viewEl.style.display = 'none'; editEl.style.display = 'flex';
            }
        }
        
        // --- ECHARTS GRAFIEK CONFIGURATIE ---
        document.addEventListener('DOMContentLoaded', function() {
            var chartDom = document.getElementById('chart-container');
            var myChart = echarts.init(chartDom);
            var option;

            option = {
                tooltip: { trigger: 'axis' },
                grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
                xAxis: {
                    type: 'category',
                    boundaryGap: false,
                    data: <?php echo json_encode($chartWeeks); ?> // PHP Data
                },
                yAxis: { type: 'value' },
                series: [
                    {
                        name: 'Dossiers',
                        type: 'line',
                        smooth: true,
                        itemStyle: { color: '#0176d3' },
                        areaStyle: {
                            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                { offset: 0, color: 'rgba(1, 118, 211, 0.5)' },
                                { offset: 1, color: 'rgba(1, 118, 211, 0.05)' }
                            ])
                        },
                        data: <?php echo json_encode($chartCounts); ?> // PHP Data
                    }
                ]
            };
            option && myChart.setOption(option);
            
            // Resize chart bij window resize
            window.addEventListener('resize', function() { myChart.resize(); });
        });

        // --- SEARCH OVERLAY LOGICA ---
        const overlay = document.getElementById('search-overlay');
        const input = document.getElementById('spotlight-input');
        const resultsDiv = document.getElementById('spotlight-results');

        function openSearch() { 
            overlay.style.display = 'flex'; 
            input.focus(); 
        }
        function closeSearch() { 
            overlay.style.display = 'none'; 
            input.value = ''; 
            resultsDiv.innerHTML = ''; 
        }
        
        overlay.addEventListener('click', (e) => { if(e.target===overlay) closeSearch(); });
        document.addEventListener('keydown', (e) => {
            // Sluiten met ESC
            if(e.key === 'Escape') closeSearch();
            
            // Openen met '/' (alleen als we niet al in een input zitten)
            if(e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault(); 
                openSearch();
            }
        });

        // Live Search AJAX
        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            if(input.value.length < 1) { resultsDiv.innerHTML = ''; return; }
            
            timer = setTimeout(() => {
                fetch(`dashboard.php?ajax_search=${encodeURIComponent(input.value)}`)
                    .then(r => r.json())
                    .then(data => {
                        resultsDiv.innerHTML = '';
                        if(data.length === 0) { 
                            resultsDiv.innerHTML = '<div class="no-results">Geen dossiers gevonden.</div>'; 
                        } else {
                            data.forEach(item => {
                                const d = document.createElement('div');
                                d.className = 'result-item';
                                d.innerHTML = `
                                    <div>
                                        <div style="font-weight:600; color:#0176d3;">${item.name}</div>
                                        <div style="font-size:12px; color:#706e6b;">${item.employee_id || 'Geen ID'} • ${item.form_date}</div>
                                    </div>
                                    <span class="material-icons-outlined" style="font-size:16px; color:#ccc;">arrow_forward</span>
                                `;
                                d.onclick = () => window.location.href = `feedback_view.php?id=${item.form_id}`;
                                resultsDiv.appendChild(d);
                            });
                        }
                    });
            }, 250); // Wacht 250ms na typen
        });
        
        // --- PAGINATION AJAX FALLBACK ---
        // (Dezelfde code als voorheen behouden voor tabel paginering)
        document.addEventListener('click', function(e) {
            if (e.target.closest('#pagination-container a')) {
                const link = e.target.closest('a');
                if (!e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) {
                    e.preventDefault();
                    const url = link.href;
                    const fetchUrl = new URL(url);
                    fetchUrl.searchParams.append('ajax_pagination', '1');
                    const tableBody = document.getElementById('feedback-table-body');
                    tableBody.style.opacity = '0.5';
                    fetch(fetchUrl).then(res => res.json()).then(data => {
                        tableBody.innerHTML = data.rows;
                        document.getElementById('pagination-container').innerHTML = data.pagination;
                        tableBody.style.opacity = '1';
                        window.history.pushState({}, '', url);
                    });
                }
            }
        });
    </script>
</body>
</html>