<?php
/**
 * DASHBOARD.PHP - Enterprise Edition
 * Inclusief werkende links naar Create & Edit modules.
 */

// 1. CONFIGURATIE & DATABASE
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// 2. BEVEILIGING
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Data integriteit check
if (!isset($_SESSION['role']) || !isset($_SESSION['email'])) {
    session_destroy();
    header("Location: login.php?error=session_expired");
    exit;
}

// 3. VARIABELEN
$userEmail = $_SESSION['email'];
$userRole  = ucfirst($_SESSION['role']);

// Meldingen afvangen (bijv. na opslaan)
$msg = $_GET['msg'] ?? '';

// 4. DATA OPHALEN
$stats = ['drivers' => 0, 'open_feedback' => 0];
$recentActivities = [];

try {
    // Statistieken tellen
    $stats['drivers'] = $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
    $stats['open_feedback'] = $pdo->query("SELECT COUNT(*) FROM feedback_forms WHERE status = 'open'")->fetchColumn();

    // Recente activiteiten ophalen
    $sqlRecent = "SELECT f.id, f.form_date, d.name as driver_name, u.email as creator_email, f.status 
                  FROM feedback_forms f
                  JOIN drivers d ON f.driver_id = d.id
                  JOIN users u ON f.created_by_user_id = u.id
                  ORDER BY f.updated_at DESC, f.created_at DESC 
                  LIMIT 5";
    $recentActivities = $pdo->query($sqlRecent)->fetchAll();

} catch (PDOException $e) {
    // In productie loggen we dit stil
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Chauffeurs Dossier</title>
    
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- ENTERPRISE THEME (Consistent met andere pagina's) --- */
        :root {
            --brand-color: #0176d3;       /* Salesforce Blue */
            --brand-dark: #014486;
            --sidebar-bg: #1a2233;        /* Oracle Dark */
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
        
        /* HEADER */
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 10; }
        .search-bar input { padding: 8px 12px 8px 35px; border: 1px solid var(--border-color); border-radius: 4px; width: 300px; background-color: #fff; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .avatar-circle { width: 32px; height: 32px; background-color: var(--brand-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; }

        /* PAGINA INHOUD */
        .page-body { padding: 24px; max-width: 1400px; margin: 0 auto; width: 100%; }
        .page-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title h1 { margin: 0; font-size: 24px; color: var(--text-main); }
        .page-subtitle { color: var(--text-secondary); font-size: 13px; margin-top: 4px; }

        /* BUTTONS */
        .btn { padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid transparent; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-neutral { background: white; border: 1px solid var(--border-color); color: var(--brand-color); }
        .btn-brand { background: var(--brand-color); color: white; }
        .btn:hover { opacity: 0.9; }

        /* CARDS & GRID */
        .grid-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: var(--card-shadow); }
        .card-header { padding: 12px 16px; border-bottom: 1px solid var(--border-color); background-color: #fcfcfc; display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px; }
        .card-body { padding: 16px; }

        /* KPI */
        .kpi-value { font-size: 32px; font-weight: 300; color: var(--text-main); margin-bottom: 4px; }
        .kpi-label { font-size: 13px; color: var(--text-secondary); }

        /* TABLES */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; color: var(--text-secondary); padding: 8px; border-bottom: 1px solid var(--border-color); font-weight: 600; font-size: 12px; text-transform: uppercase; }
        td { padding: 12px 8px; border-bottom: 1px solid #eee; color: var(--text-main); }
        tr:last-child td { border-bottom: none; }
        
        .status-badge { padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; }
        .status-open { background: #fff0b5; color: #744f05; }
        .status-completed { background: #c1f0d3; color: #0c4d26; }
        
        /* ALERTS */
        .alert-toast { background: var(--success-bg); color: var(--success-text); padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 10px; }

    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <span>LogistiekApp</span>
        </div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="dashboard.php" class="active">
                    <span class="material-icons-outlined">dashboard</span>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="#">
                    <span class="material-icons-outlined">people</span>
                    Chauffeurs
                </a>
            </li>
            <li class="nav-item">
                <a href="feedback_create.php">
                    <span class="material-icons-outlined">assignment</span>
                    Feedback
                </a>
            </li>
            <li class="nav-item">
                <a href="#">
                    <span class="material-icons-outlined">bar_chart</span>
                    Rapportages
                </a>
            </li>
            <?php if($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">
                <a href="#">
                    <span class="material-icons-outlined">settings</span>
                    Beheer
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </aside>

    <main class="main-content">
        
        <header class="top-header">
            <div class="search-bar">
                <input type="text" placeholder="Zoek dossier, chauffeur of ID...">
            </div>
            <div class="user-profile">
                <span class="material-icons-outlined" style="color:#706e6b; cursor:pointer;">notifications</span>
                <span style="font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($userEmail); ?></span>
                <div class="avatar-circle">
                    <?php echo strtoupper(substr($userEmail, 0, 1)); ?>
                </div>
                <a href="logout.php" class="material-icons-outlined" style="color: var(--text-secondary); text-decoration:none; margin-left: 10px;" title="Uitloggen">logout</a>
            </div>
        </header>

        <div class="page-body">
            
            <?php if ($msg === 'created'): ?>
                <div class="alert-toast">
                    <span class="material-icons-outlined">check_circle</span> 
                    Nieuw gesprek succesvol aangemaakt!
                </div>
            <?php elseif ($msg === 'completed'): ?>
                <div class="alert-toast">
                    <span class="material-icons-outlined">check_circle</span> 
                    Dossier succesvol afgerond en opgeslagen.
                </div>
            <?php endif; ?>

            <div class="page-title-row">
                <div class="page-title">
                    <h1>Dashboard</h1>
                    <div class="page-subtitle">Overzicht van prestaties en taken • Vandaag</div>
                </div>
                <div class="page-actions">
                    <a href="#" class="btn btn-neutral">Rapport Downloaden</a>
                    
                    <a href="feedback_create.php" class="btn btn-brand">
                        <span class="material-icons-outlined" style="font-size: 16px;">add</span>
                        Nieuw Gesprek
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
                <div class="card">
                    <div class="card-body">
                        <div class="kpi-value">4</div>
                        <div class="kpi-label">Mijn Taken</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Recente Feedback Dossiers</h2>
                    <a href="#" style="font-size: 12px; color: var(--brand-color); text-decoration: none;">Alles bekijken</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($recentActivities)): ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-secondary);">
                            Geen recente activiteiten gevonden. Start een nieuw gesprek!
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Chauffeur</th>
                                    <th>Aangemaakt Door</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Actie</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivities as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['form_date']); ?></td>
                                    <td>
                                        <a href="feedback_form.php?id=<?php echo $row['id']; ?>" style="color: var(--brand-color); text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($row['driver_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['creator_email']); ?></td>
                                    <td>
                                        <?php $sClass = ($row['status'] == 'open') ? 'status-open' : 'status-completed'; ?>
                                        <span class="status-badge <?php echo $sClass; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="feedback_form.php?id=<?php echo $row['id']; ?>" title="Bewerken" style="text-decoration: none;">
                                            <span class="material-icons-outlined" style="font-size: 18px; color: var(--text-secondary); cursor: pointer;">edit</span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="card-footer" style="padding: 10px; border-top: 1px solid var(--border-color); text-align: center;">
                    <a href="#" style="font-size: 12px; color: var(--brand-color); text-decoration: none;">Toon meer</a>
                </div>
            </div>

        </div>
    </main>

</body>
</html>