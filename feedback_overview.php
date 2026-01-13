<?php
/**
 * FEEDBACK_OVERVIEW.PHP
 * Overzicht van alle gesprekken met filters, paginering EN inline bewerken.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

// 1. BEVEILIGING
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. OPSLAAN LOGICA (POST - INLINE EDITS)
// Dit blok verwerkt de wijzigingen direct als er op het vinkje wordt geklikt.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Optioneel: CSRF check als je dat overal gebruikt
    // verify_csrf(); 

    // A. Toewijzing wijzigen
    if (isset($_POST['assign_user_id'], $_POST['form_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE feedback_forms SET assigned_to_user_id = ? WHERE id = ?");
            $val = !empty($_POST['assign_user_id']) ? $_POST['assign_user_id'] : null;
            $stmt->execute([$val, $_POST['form_id']]);
            // Redirect om 'Form Resubmission' te voorkomen
            header("Location: feedback_overview.php?" . $_SERVER['QUERY_STRING']); exit;
        } catch (PDOException $e) {}
    }

    // B. Status wijzigen
    if (isset($_POST['update_status'], $_POST['form_id'], $_POST['new_status'])) {
        try {
            $stmt = $pdo->prepare("UPDATE feedback_forms SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['new_status'], $_POST['form_id']]);
            header("Location: feedback_overview.php?" . $_SERVER['QUERY_STRING']); exit;
        } catch (PDOException $e) {}
    }
}

// 3. CONFIGURATIE VOOR HEADER & PAGINERING
$page_title = 'Feedback overzicht'; 
$limit = 15; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filters ophalen
$filter_status = $_GET['status'] ?? '';
$filter_assigned = $_GET['assigned_to'] ?? '';

// 4. DATA OPHALEN: TEAMLEADS (voor dropdowns)
try {
    $stmtUsers = $pdo->query("SELECT id, email, first_name, last_name FROM users ORDER BY first_name ASC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $users = []; }

// 5. QUERY OPBOUWEN
$whereSQL = "WHERE 1=1";
$params = [];

if (!empty($filter_status)) {
    $whereSQL .= " AND f.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_assigned)) {
    $whereSQL .= " AND f.assigned_to_user_id = ?";
    $params[] = $filter_assigned;
}

// 6. DATA OPHALEN
$totalRows = 0;
$feedbacks = [];

try {
    // Totaal tellen
    $countQuery = "SELECT COUNT(*) FROM feedback_forms f $whereSQL";
    $stmtCount = $pdo->prepare($countQuery);
    $stmtCount->execute($params);
    $totalRows = $stmtCount->fetchColumn();
    
    $totalPages = ceil($totalRows / $limit);

    // Data ophalen
    $dataQuery = "SELECT 
                    f.id, f.form_date, f.review_moment, f.status, f.created_at, f.assigned_to_user_id,
                    d.id as driver_id, d.name as driver_name, d.employee_id,
                    u_assign.first_name as assign_first, u_assign.last_name as assign_last, u_assign.email as assign_email,
                    u_create.email as creator_email
                  FROM feedback_forms f
                  JOIN drivers d ON f.driver_id = d.id
                  LEFT JOIN users u_assign ON f.assigned_to_user_id = u_assign.id
                  LEFT JOIN users u_create ON f.created_by_user_id = u_create.id
                  $whereSQL
                  ORDER BY f.form_date DESC
                  LIMIT $limit OFFSET $offset";
    
    $stmtData = $pdo->prepare($dataQuery);
    $stmtData->execute($params);
    $feedbacks = $stmtData->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

function get_page_link($pageNum) {
    $params = $_GET; $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- THEME --- */
        :root { --brand-color: #0176d3; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* --- SIDEBAR & LAYOUT --- */
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
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 2px rgba(0,0,0,0.1); margin-bottom: 24px; }

        /* Filter Bar */
        .filter-bar { padding: 16px; border-bottom: 1px solid var(--border-color); display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; background: #fcfcfc; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 12px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; }
        .filter-select { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; min-width: 150px; font-size: 14px; }
        .btn-filter { background: var(--brand-color); color: white; border: none; padding: 9px 16px; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .btn-reset { background: white; border: 1px solid var(--border-color); color: var(--text-main); padding: 9px 16px; border-radius: 4px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 14px; }
        .btn-reset:hover { background: #f3f2f2; }

        /* Table & Inline Edits */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 12px; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size: 11px; background: #fafafa; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; color: var(--text-main); }
        tr:hover td { background-color: #f9f9f9; }

        .view-mode { display: flex; align-items: center; gap: 8px; }
        .edit-mode { display: none; align-items: center; gap: 4px; }
        
        .icon-btn { cursor: pointer; color: #999; font-size: 16px; transition: color 0.2s; background: none; border: none; padding: 2px; }
        .icon-btn:hover { color: var(--brand-color); }
        .icon-btn.save { color: #10b981; }
        .icon-btn.save:hover { color: #059669; }

        .inline-select { padding: 4px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }

        /* Badges */
        .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-block; }
        .bg-open { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
        .bg-completed { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }

        /* Pagination */
        .pagination { padding: 15px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #eee; }
        .page-link { text-decoration: none; padding: 6px 12px; border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-main); font-size: 13px; background: white; }
        .page-link:hover { background: #f3f2f2; }
        .page-info { color: var(--text-secondary); font-size: 13px; }
        .disabled { opacity: 0.5; pointer-events: none; }
        .btn-icon-link { color: #706e6b; text-decoration: none; padding: 4px; border-radius: 4px; transition: 0.2s; }
        .btn-icon-link:hover { background: #eee; color: var(--brand-color); }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-body">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="margin: 0; font-size: 24px;">Overzicht</h1>
                <a href="feedback_create.php" style="background: var(--brand-color); color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 6px; font-size: 14px;">
                    <span class="material-icons-outlined">add</span> nieuw
                </a>
            </div>

            <div class="card">
                <form method="GET" class="filter-bar">
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" class="filter-select">
                            <option value="">Alle statussen</option>
                            <option value="open" <?php if($filter_status == 'open') echo 'selected'; ?>>Open</option>
                            <option value="completed" <?php if($filter_status == 'completed') echo 'selected'; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Toegewezen aan</label>
                        <select name="assigned_to" class="filter-select">
                            <option value="">Iedereen</option>
                            <?php foreach ($users as $u): 
                                $name = format_user_name($u['first_name'], $u['last_name'], $u['email']);
                            ?>
                                <option value="<?php echo $u['id']; ?>" <?php if($filter_assigned == $u['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-left: auto; display: flex; gap: 10px;">
                        <a href="feedback_overview.php" class="btn-reset">Reset</a>
                        <button type="submit" class="btn-filter">Filteren</button>
                    </div>
                </form>

                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Chauffeur</th>
                                <th>Type / Moment</th>
                                <th>Status</th>
                                <th>Toegewezen aan</th>
                                <th>Gemaakt door</th>
                                <th style="text-align: right;">Actie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($feedbacks)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px; color: #999;">Geen gesprekken gevonden.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($feedbacks as $row): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($row['form_date'])); ?></td>
                                    <td>
                                        <a href="driver_history.php?driver_id=<?php echo $row['driver_id']; ?>" style="font-weight: 700; color: var(--brand-color); text-decoration: none;">
                                            <?php echo htmlspecialchars($row['driver_name']); ?>
                                        </a>
                                        <div style="font-size: 11px; color: #999;"><?php echo htmlspecialchars($row['employee_id']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['review_moment'] ?: 'Standaard'); ?></td>
                                    
                                    <td>
                                        <div id="view-status-<?php echo $row['id']; ?>" class="view-mode">
                                            <?php echo format_status_badge($row['status']); ?>
                                            <button class="icon-btn" onclick="toggleEdit('status', <?php echo $row['id']; ?>)">
                                                <span class="material-icons-outlined" style="font-size:14px;">edit</span>
                                            </button>
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
                                                echo format_user_name($row['assign_first'], $row['assign_last'], $row['assign_email']);
                                            } else {
                                                echo '<span style="color:#bbb;">--</span>';
                                            }
                                            ?>
                                            <button class="icon-btn" onclick="toggleEdit('assign', <?php echo $row['id']; ?>)">
                                                <span class="material-icons-outlined" style="font-size:14px;">edit</span>
                                            </button>
                                        </div>
                                        <form method="POST" id="edit-assign-<?php echo $row['id']; ?>" class="edit-mode">
                                            <input type="hidden" name="form_id" value="<?php echo $row['id']; ?>">
                                            <select name="assign_user_id" class="inline-select">
                                                <option value="">-- Geen --</option>
                                                <?php foreach ($users as $u): 
                                                    $optName = format_user_name($u['first_name'], $u['last_name'], $u['email']);
                                                ?>
                                                    <option value="<?php echo $u['id']; ?>" <?php if($row['assigned_to_user_id'] == $u['id']) echo 'selected'; ?>>
                                                        <?php echo $optName; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="icon-btn save"><span class="material-icons-outlined">check</span></button>
                                            <button type="button" class="icon-btn" onclick="toggleEdit('assign', <?php echo $row['id']; ?>)"><span class="material-icons-outlined">close</span></button>
                                        </form>
                                    </td>

                                    <td style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($row['creator_email']); ?></td>
                                    <td style="text-align: right;">
                                        <a href="feedback_view.php?id=<?php echo $row['id']; ?>" class="btn-icon-link"><span class="material-icons-outlined">visibility</span></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div>
                        <?php if ($page > 1): ?>
                            <a href="<?php echo get_page_link($page - 1); ?>" class="page-link">&laquo; Vorige</a>
                        <?php else: ?>
                            <span class="page-link disabled">&laquo; Vorige</span>
                        <?php endif; ?>
                    </div>
                    <div class="page-info">Pagina <strong><?php echo $page; ?></strong> van <strong><?php echo $totalPages; ?></strong></div>
                    <div>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo get_page_link($page + 1); ?>" class="page-link">Volgende &raquo;</a>
                        <?php else: ?>
                            <span class="page-link disabled">Volgende &raquo;</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Functie om te wisselen tussen tekst weergave en edit modus
        function toggleEdit(type, id) {
            const viewEl = document.getElementById(`view-${type}-${id}`);
            const editEl = document.getElementById(`edit-${type}-${id}`);
            if (viewEl.style.display === 'none') {
                viewEl.style.display = 'flex'; 
                editEl.style.display = 'none';
            } else {
                viewEl.style.display = 'none'; 
                editEl.style.display = 'flex';
            }
        }
    </script>
</body>
</html>