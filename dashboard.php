<?php
/**
 * DASHBOARD.PHP
 * Inclusief: Direct toewijzen van teamleiders aan openstaande gesprekken.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// 1. BEVEILIGING
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['role']) || !isset($_SESSION['email'])) {
    session_destroy();
    header("Location: login.php?error=session_expired");
    exit;
}

// 2. LOGICA: TOEWIJZING VERWERKEN (POST)
// Dit stukje code luistert of er op het 'opslaan' icoontje is geklikt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_user_id']) && isset($_POST['form_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE feedback_forms SET assigned_to_user_id = ? WHERE id = ?");
        $stmt->execute([$_POST['assign_user_id'], $_POST['form_id']]);
        
        // Pagina verversen om de wijziging te tonen
        header("Location: dashboard.php?msg=assigned");
        exit;
    } catch (PDOException $e) {
        // Foutafhandeling (in productie loggen)
    }
}

// 3. DATA OPHALEN
$userEmail = $_SESSION['email'];
$msg = $_GET['msg'] ?? '';

// Statistieken
$stats = ['drivers' => 0, 'open_feedback' => 0];
try {
    $stats['drivers'] = $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
    $stats['open_feedback'] = $pdo->query("SELECT COUNT(*) FROM feedback_forms WHERE status = 'open'")->fetchColumn();
} catch (PDOException $e) {}

// Lijst met Teamleiders (voor de dropdown)
$teamleads = [];
try {
    $teamleads = $pdo->query("SELECT id, email FROM users ORDER BY email ASC")->fetchAll();
} catch (PDOException $e) {}

// Recente Activiteiten (Met JOIN om te zien aan wie het is toegewezen)
$recentActivities = [];
try {
    $sql = "SELECT 
                f.id, 
                f.form_date, 
                f.status, 
                f.assigned_to_user_id,
                d.name as driver_name, 
                u_creator.email as creator_email,
                u_assigned.email as assigned_email
            FROM feedback_forms f
            JOIN drivers d ON f.driver_id = d.id
            JOIN users u_creator ON f.created_by_user_id = u_creator.id
            LEFT JOIN users u_assigned ON f.assigned_to_user_id = u_assigned.id
            ORDER BY f.created_at DESC 
            LIMIT 10";
    $recentActivities = $pdo->query($sql)->fetchAll();
} catch (PDOException $e) {}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Chauffeurs Dossier</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- ENTERPRISE THEME --- */
        :root {
            --brand-color: #0176d3;
            --brand-dark: #014486;
            --sidebar-bg: #1a2233;
            --bg-body: #f3f2f2;
            --text-main: #181818;
            --text-secondary: #706e6b;
            --border-color: #dddbda;
            --card-shadow: 0 2px 2px 0 rgba(0,0,0,0.1);
            --success-bg: #d1fae5;
            --success-text: #065f46;
        }

        body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* SIDEBAR */
        .sidebar { width: 240px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { height: 60px; display: flex; align-items: center; padding: 0 20px; font-size: 18px; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); }
        .nav-list { list-style: none; padding: 20px 0; margin: 0; flex-grow: 1; }
        .nav-item a { display: flex; align-items: center; padding: 12px 20px; color: #b0b6c3; text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item a:hover, .nav-item a.active { background-color: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--brand-color); }
        .nav-item .material-icons-outlined { margin-right: 12px; font-size: 20px; }

        /* MAIN CONTENT */
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 10; }
        .search-bar input { padding: 8px 12px 8px 35px; border: 1px solid var(--border-color); border-radius: 4px; width: 300px; }
        .user-profile { display: flex; align-items: center; gap: 12px; font-size: 13px; font-weight: 600; }

        .page-body { padding: 24px; max-width: 1400px; margin: 0 auto; width: 100%; flex-grow: 1; } /* flex-grow toegevoegd voor footer alignment */
        
        /* ALERTS */
        .alert-toast { background: var(--success-bg); color: var(--success-text); padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 10px; }

        /* BUTTONS */
        .btn { padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-brand { background: var(--brand-color); color: white; }
        .btn-brand:hover { background: var(--brand-dark); }
        .btn-neutral { background: white; border: 1px solid var(--border-color); color: var(--brand-color); }

        /* CARDS & GRID */
        .grid-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: var(--card-shadow); }
        .card-body { padding: 16px; }
        .card-header { padding: 12px 16px; border-bottom: 1px solid var(--border-color); background-color: #fcfcfc; display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-main); text-transform: uppercase; }

        /* KPI */
        .kpi-value { font-size: 32px; font-weight: 300; color: var(--text-main); margin-bottom: 4px; }
        .kpi-label { font-size: 13px; color: var(--text-secondary); }

        /* TABLE */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size: 11px; }
        td { padding: 10px; border-bottom: 1px solid #eee; color: var(--text-main); vertical-align: middle; }
        
        .status-badge { padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .status-open { background: #fff0b5; color: #744f05; }
        .status-completed { background: #c1f0d3; color: #0c4d26; }

        /* ASSIGN FORM STYLES */
        .assign-select { padding: 5px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; max-width: 150px; }
        .btn-icon-save { background: none; border: none; cursor: pointer; color: var(--brand-color); margin-left: 5px; padding: 4px; border-radius: 4px; }
        .btn-icon-save:hover { background-color: #e0e7ff; }

    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">LogistiekApp</div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="dashboard.php" class="active">
                    <span class="material-icons-outlined">dashboard</span> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="feedback_form.php">
                    <span class="material-icons-outlined">add_circle</span> Nieuw Gesprek
                </a>
            </li>
            <li class="nav-item">
                <a href="#"><span class="material-icons-outlined">people</span> Chauffeurs</a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        
        <header class="top-header">
            <div class="search-bar">
                <input type="text" placeholder="Zoek op naam, ID of status...">
            </div>
            <div class="user-profile">
                <span class="material-icons-outlined" style="margin-right: 8px;">account_circle</span>
                <?php echo htmlspecialchars($userEmail); ?>
                <a href="logout.php" title="Uitloggen" style="margin-left: 15px; color: var(--text-secondary); text-decoration: none;">
                    <span class="material-icons-outlined" style="font-size: 20px; vertical-align: middle;">logout</span>
                </a>
            </div>
        </header>

        <div class="page-body">
            
            <?php if ($msg === 'created'): ?>
                <div class="alert-toast"><span class="material-icons-outlined">check_circle</span> Gesprek succesvol aangemaakt!</div>
            <?php elseif ($msg === 'saved'): ?>
                <div class="alert-toast"><span class="material-icons-outlined">save</span> Wijzigingen opgeslagen.</div>
            <?php elseif ($msg === 'assigned'): ?>
                <div class="alert-toast"><span class="material-icons-outlined">person_add</span> Teamleider toegewezen.</div>
            <?php elseif ($msg === 'completed'): ?>
                <div class="alert-toast"><span class="material-icons-outlined">done_all</span> Dossier afgerond!</div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <div>
                    <h1 style="margin: 0; font-size: 24px; color: var(--text-main);">Dashboard</h1>
                    <div style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">Overzicht van prestaties en taken</div>
                </div>
                <div>
                    <a href="feedback_form.php" class="btn btn-brand">
                        <span class="material-icons-outlined" style="font-size: 18px;">add</span> Nieuw Gesprek
                    </a>
                </div>
            </div>

            <div class="grid-row">
                <div class="card">
                    <div class="card-body">
                        <div class="kpi-value"><?php echo $stats['drivers']; ?></div>
                        <div class="kpi-label">Actieve Chauffeurs</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="kpi-value"><?php echo $stats['open_feedback']; ?></div>
                        <div class="kpi-label">Openstaande Dossiers</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="kpi-value">98%</div>
                        <div class="kpi-label">Team OTD Score</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Recente Dossiers & Planning</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Chauffeur</th>
                                <th>Gemaakt Door</th>
                                <th>Status</th>
                                <th style="min-width: 200px;">Toegewezen Aan</th> 
                                <th style="text-align: right;">Actie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentActivities)): ?>
                                <tr><td colspan="6" style="text-align:center; padding: 20px;">Geen gegevens gevonden.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['form_date']); ?></td>
                                    
                                    <td>
                                        <a href="feedback_view.php?id=<?php echo $row['id']; ?>" style="color: var(--brand-color); text-decoration: none; font-weight: 700;">
                                            <?php echo htmlspecialchars($row['driver_name']); ?>
                                        </a>
                                    </td>

                                    <td><?php echo htmlspecialchars($row['creator_email']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo ($row['status'] === 'open') ? 'status-open' : 'status-completed'; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <?php if (!empty($row['assigned_to_user_id'])): ?>
                                            <div style="display: flex; align-items: center; gap: 5px; color: #333;">
                                                <span class="material-icons-outlined" style="font-size: 16px; color: var(--brand-color);">person</span>
                                                <?php echo htmlspecialchars($row['assigned_email']); ?>
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" style="display: flex; align-items: center;">
                                                <input type="hidden" name="form_id" value="<?php echo $row['id']; ?>">
                                                <select name="assign_user_id" class="assign-select" required>
                                                    <option value="">-- Kies --</option>
                                                    <?php foreach ($teamleads as $lead): ?>
                                                        <option value="<?php echo $lead['id']; ?>"><?php echo htmlspecialchars($lead['email']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn-icon-save" title="Opslaan">
                                                    <span class="material-icons-outlined" style="font-size: 18px;">save</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>

                                    <td style="text-align: right;">
                                        <a href="feedback_form.php?id=<?php echo $row['id']; ?>" title="Bewerken" style="color: var(--text-secondary); text-decoration: none; display: inline-flex; align-items: center;">
                                            <span class="material-icons-outlined" style="font-size: 20px;">edit</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <?php include __DIR__ . '/includes/footer.php'; ?>

    </main>
</body>
</html>