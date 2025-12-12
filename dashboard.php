<?php
/**
 * DASHBOARD.PHP
 * - Versie: 2.1 (Fix voor paginering knoppen die niet reageren)
 * - Feature: AJAX update van tabel & zoekbalk.
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
    // Wis output buffers om JSON fouten te voorkomen
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

// 2. CSV EXPORT LOGICA
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    while (ob_get_level()) { ob_end_clean(); } // Buffer wissen voor schone download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="feedback_export_'.date('Y-m-d').'.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Datum', 'Chauffeur', 'Personeelsnr', 'Review Moment', 'Status', 'Toegewezen Aan', 'Gemaakt Door']);
    
    $sqlExport = "SELECT f.id, f.form_date, d.name, d.employee_id, f.review_moment, f.status, u.email as assigned, c.email as creator
                  FROM feedback_forms f
                  JOIN drivers d ON f.driver_id = d.id
                  LEFT JOIN users u ON f.assigned_to_user_id = u.id
                  LEFT JOIN users c ON f.created_by_user_id = c.id
                  ORDER BY f.form_date DESC LIMIT 1000";
    $stmt = $pdo->query($sqlExport);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

include __DIR__ . '/includes/sidebar.php';

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
$userEmail = $_SESSION['email'];
$currentUserId = $_SESSION['user_id'];
$msg = $_GET['msg'] ?? '';

// KPI Stats
$stats = ['drivers' => 0, 'open_feedback' => 0, 'closed_feedback' => 0];
try {
    $stats['drivers'] = $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
    $stats['open_feedback'] = $pdo->query("SELECT COUNT(*) FROM feedback_forms WHERE status = 'open'")->fetchColumn();
    $stats['closed_feedback'] = $pdo->query("SELECT COUNT(*) FROM feedback_forms WHERE status = 'completed'")->fetchColumn();
} catch (PDOException $e) {}

$teamleads = $pdo->query("SELECT id, email, first_name, last_name FROM users ORDER BY first_name ASC")->fetchAll();

// --- PAGINERING LOGICA (ROBUUSTE METHODE) ---
$limit = 8;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Deel A: De "Body" van de query (zonder SELECT)
$sqlBody = " FROM feedback_forms f
             JOIN drivers d ON f.driver_id = d.id
             JOIN users u_creator ON f.created_by_user_id = u_creator.id
             LEFT JOIN users u_assigned ON f.assigned_to_user_id = u_assigned.id";

// Deel B: Tellen
try {
    $stmtCount = $pdo->query("SELECT COUNT(*) " . $sqlBody);
    $totalRows = $stmtCount->fetchColumn();
} catch (PDOException $e) {
    $totalRows = 0;
}

// Deel C: Pagina berekening
$totalPages = ceil($totalRows / $limit);
if ($page > $totalPages && $totalPages > 0) { $page = $totalPages; }
$offset = ($page - 1) * $limit;

// Deel D: Data ophalen
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


// --- HTML GENEREREN VOOR AJAX EN INITIAL LOAD ---

// A. Tabel Rijen HTML
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
        $isOverdue = ($row['status'] === 'open' && $interval->days > 14);
    ?>
        <tr>
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

// B. Pagination Controls HTML
ob_start();
$qs = $_GET; 
unset($qs['ajax_pagination']); 
?>
    <?php if ($page > 1): 
        $qs['page'] = $page - 1;
    ?>
        <a href="?<?php echo http_build_query($qs); ?>">&laquo; Vorige</a>
    <?php else: ?>
        <span class="disabled">&laquo; Vorige</span>
    <?php endif; ?>

    <span class="current" style="margin: 0 10px; color: #999;">
        Pagina <?php echo $page; ?> van <?php echo max(1, $totalPages); ?>
    </span>

    <?php if ($page < $totalPages): 
        $qs['page'] = $page + 1;
    ?>
        <a href="?<?php echo http_build_query($qs); ?>">Volgende &raquo;</a>
    <?php else: ?>
        <span class="disabled">Volgende &raquo;</span>
    <?php endif; ?>
<?php 
$paginationHtml = ob_get_clean();

// 3. AJAX REQUEST AFHANDELING
if (isset($_GET['ajax_pagination'])) {
    // CRUCIAAL: Buffers wissen om 'white-space injection' uit includes te verwijderen
    while (ob_get_level()) { ob_end_clean(); }
    
    header('Content-Type: application/json');
    echo json_encode([
        'rows' => $rowsHtml,
        'pagination' => $paginationHtml
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?php echo defined('APP_TITLE') ? APP_TITLE : 'Dashboard'; ?></title>
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
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 2px rgba(0,0,0,0.1); }
        .card-body { padding: 16px; }
        .kpi-value { font-size: 32px; font-weight: 300; } .kpi-label { font-size: 13px; color: var(--text-secondary); }
        .alert-toast { background: var(--success-bg); color: var(--success-text); padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .btn-brand { background: var(--brand-color); color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; }

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
        .icon-btn.save { color: var(--success-text); }
        .status-badge { padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .bg-open { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
        .bg-completed { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .inline-select { padding: 4px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
        .text-urgent { color: #c53030; font-weight: 700; }

        /* HEADER SEARCH & OVERLAY */
        .header-search-trigger { background: #f3f2f2; border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 12px; width: 280px; color: var(--text-secondary); font-size: 13px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: background 0.2s; }
        .header-search-trigger:hover { background: #e0e0e0; }
        #search-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.25); z-index: 9999; display: none; justify-content: center; padding-top: 15vh; }
        .spotlight-container { width: 100%; max-width: 400px; background: white; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); overflow: hidden; border: 1px solid #e5e7eb; display: flex; flex-direction: column; max-height: 80vh; }
        .spotlight-input-wrapper { display: flex; align-items: center; padding: 12px 16px; border-bottom: 1px solid #eee; }
        #spotlight-input { border: none; font-size: 16px; width: 100%; outline: none; background: transparent; }
        #spotlight-results { max-height: 100px; overflow-y: auto; }
        .result-item { padding: 10px 16px; border-bottom: 1px solid #f7f7f7; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-size: 13px; }
        .result-item:hover { background: #f0f9ff; border-left: 3px solid var(--brand-color); }
    </style>
</head>
<body>

    <div id="search-overlay">
        <div class="spotlight-container">
            <div class="spotlight-input-wrapper">
                <span class="material-icons-outlined" style="margin-right:12px; color:#999; font-size:20px;">search</span>
                <input type="text" id="spotlight-input" placeholder="Zoek op naam..." autocomplete="off">
                <span class="material-icons-outlined" style="cursor:pointer; color:#ccc; font-size: 20px;" onclick="closeSearch()">close</span>
            </div>
            <div id="spotlight-results"></div>
            <div style="background:#fafafa; padding:8px 16px; font-size:11px; color:#999; border-top:1px solid #eee;">
                ESC om te sluiten
            </div>
        </div>
    </div>

<?php include __DIR__ . '/includes/header.php'; ?>

            <?php if ($msg): ?>
                <div class="alert-toast"><span class="material-icons-outlined">info</span> Update: <?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <div class="grid-row">
                <div class="card"><div class="card-body"><div class="kpi-value"><?php echo $stats['drivers']; ?></div><div class="kpi-label">Aantal dossiers</div></div></div>
                <div class="card"><div class="card-body"><div class="kpi-value"><?php echo $stats['open_feedback']; ?></div><div class="kpi-label">Openstaande dossiers</div></div></div>
                <div class="card"><div class="card-body"><div class="kpi-value"><?php echo $stats['closed_feedback']; ?></div><div class="kpi-label">Gesloten dossiers</div></div></div>
            </div>

            <div class="card">
                <div class="filter-toolbar">
                    <div style="font-weight:700; font-size:14px;">Recente Dossiers</div>
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
        
        // --- PAGINATION AJAX LOGICA MET FALLBACK ---
        document.addEventListener('click', function(e) {
            if (e.target.closest('#pagination-container a')) {
                const link = e.target.closest('a');
                // Alleen intercepteren als het een normale klik is (geen ctrl/cmd klik)
                if (!e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) {
                    e.preventDefault();
                    const url = link.href;
                    
                    const fetchUrl = new URL(url);
                    fetchUrl.searchParams.append('ajax_pagination', '1');

                    const tableBody = document.getElementById('feedback-table-body');
                    tableBody.style.opacity = '0.5';

                    fetch(fetchUrl)
                        .then(res => {
                            if (!res.ok) throw new Error('Network response was not ok');
                            return res.json();
                        })
                        .then(data => {
                            // Succes: update de tabel
                            tableBody.innerHTML = data.rows;
                            document.getElementById('pagination-container').innerHTML = data.pagination;
                            tableBody.style.opacity = '1';
                            window.history.pushState({}, '', url);
                        })
                        .catch(err => {
                            console.warn('AJAX Pagination mislukt, fallback naar normale navigatie.', err);
                            // FALLBACK: Als AJAX faalt (bijv. door JSON error), volg gewoon de link!
                            window.location.href = url;
                        });
                }
            }
        });

        // --- SEARCH OVERLAY ---
        const overlay = document.getElementById('search-overlay');
        const input = document.getElementById('spotlight-input');
        const resultsDiv = document.getElementById('spotlight-results');

        function openSearch() { overlay.style.display = 'flex'; input.focus(); }
        function closeSearch() { overlay.style.display = 'none'; input.value = ''; resultsDiv.innerHTML = ''; }
        
        overlay.addEventListener('click', (e) => { if(e.target===overlay) closeSearch(); });
        document.addEventListener('keydown', (e) => {
            if(e.key === 'Escape') closeSearch();
            if(e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault(); openSearch();
            }
        });

        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            if(input.value.length < 1) { resultsDiv.innerHTML = ''; return; }
            timer = setTimeout(() => {
                fetch(`dashboard.php?ajax_search=${encodeURIComponent(input.value)}`)
                    .then(r => r.json())
                    .then(data => {
                        resultsDiv.innerHTML = '';
                        if(data.length===0) { resultsDiv.innerHTML = '<div style="padding:15px;color:#999;">Geen resultaten.</div>'; }
                        else {
                            data.forEach(item => {
                                const d = document.createElement('div');
                                d.className='result-item';
                                d.innerHTML=`<div><b>${item.name}</b><div style="font-size:12px;color:#999;">${item.employee_id || ''} • ${item.form_date}</div></div>`;
                                d.onclick = () => window.location.href=`feedback_view.php?id=${item.form_id}`;
                                resultsDiv.appendChild(d);
                            });
                        }
                    });
            }, 200);
        });
    </script>
</body>
</html>