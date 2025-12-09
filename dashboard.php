<?php
/**
 * DASHBOARD.PHP
 * Inclusief: "Spotlight" search (Centraal, Blur, Auto-suggest)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// 1. BEVEILIGING
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- AJAX HANDLER VOOR LIVE SUGGESTIES ---
// Dit blokje vangt de zoekopdracht af voordat de rest van de pagina laadt
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $term = trim($_GET['ajax_search']);
    
    if (strlen($term) < 1) {
        echo json_encode([]);
        exit;
    }

    try {
        // Zoek op naam, ID of status
        $sql = "SELECT d.id as driver_id, d.name, d.employee_id, f.id as form_id, f.form_date, f.status 
                FROM feedback_forms f
                JOIN drivers d ON f.driver_id = d.id
                WHERE d.name LIKE ? OR d.employee_id LIKE ? 
                ORDER BY f.created_at DESC LIMIT 6";
        
        $stmt = $pdo->prepare($sql);
        $like = "%$term%";
        $stmt->execute([$like, $like]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($results);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit; // Stop hier, anders wordt de HTML van de pagina ook teruggestuurd
}
// ------------------------------------------

// 2a. LOGICA: TOEWIJZING & STATUS (Bestaande code)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_user_id'], $_POST['form_id'])) {
        $stmt = $pdo->prepare("UPDATE feedback_forms SET assigned_to_user_id = ? WHERE id = ?");
        $stmt->execute([$_POST['assign_user_id'], $_POST['form_id']]);
        header("Location: dashboard.php?msg=assigned"); exit;
    }
    if (isset($_POST['update_status'], $_POST['form_id'], $_POST['new_status'])) {
        $stmt = $pdo->prepare("UPDATE feedback_forms SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['new_status'], $_POST['form_id']]);
        header("Location: dashboard.php?msg=status_updated"); exit;
    }
}

// 3. DATA OPHALEN (Standaard Dashboard Data)
$userEmail = $_SESSION['email'];
$msg = $_GET['msg'] ?? '';

// Statistieken
$stats = ['drivers' => 0, 'open_feedback' => 0];
try {
    $stats['drivers'] = $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
    $stats['open_feedback'] = $pdo->query("SELECT COUNT(*) FROM feedback_forms WHERE status = 'open'")->fetchColumn();
} catch (PDOException $e) {}

$teamleads = $pdo->query("SELECT id, email FROM users ORDER BY email ASC")->fetchAll();

// Recente Activiteiten
$recentActivities = $pdo->query("SELECT 
            f.id, f.form_date, f.status, f.assigned_to_user_id,
            d.name as driver_name, d.employee_id,
            u_creator.email as creator_email, u_assigned.email as assigned_email
        FROM feedback_forms f
        JOIN drivers d ON f.driver_id = d.id
        JOIN users u_creator ON f.created_by_user_id = u_creator.id
        LEFT JOIN users u_assigned ON f.assigned_to_user_id = u_assigned.id
        ORDER BY f.created_at DESC LIMIT 10")->fetchAll();

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?php echo defined('APP_TITLE') ? APP_TITLE : 'Dashboard'; ?></title>
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

        /* ... Bestaande Sidebar & Layout Styles ... */
        .sidebar { width: 240px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { height: 60px; display: flex; align-items: center; padding: 0 20px; font-size: 18px; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); }
        .nav-list { list-style: none; padding: 20px 0; margin: 0; flex-grow: 1; }
        .nav-item a { display: flex; align-items: center; padding: 12px 20px; color: #b0b6c3; text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item a:hover, .nav-item a.active { background-color: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--brand-color); }
        .nav-item .material-icons-outlined { margin-right: 12px; font-size: 20px; }

        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 10; }
        .user-profile { display: flex; align-items: center; gap: 12px; font-size: 13px; font-weight: 600; }
        .page-body { padding: 24px; max-width: 1400px; margin: 0 auto; width: 100%; flex-grow: 1; }
        
        /* Alerts & Cards */
        .alert-toast { background: var(--success-bg); color: var(--success-text); padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 10px; }
        .grid-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: var(--card-shadow); }
        .card-body { padding: 16px; }
        .card-header { padding: 12px 16px; border-bottom: 1px solid var(--border-color); background-color: #fcfcfc; display: flex; justify-content: space-between; align-items: center; }
        .kpi-value { font-size: 32px; font-weight: 300; color: var(--text-main); margin-bottom: 4px; }
        .kpi-label { font-size: 13px; color: var(--text-secondary); }
        .btn { padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .btn-brand { background: var(--brand-color); color: white; }

        /* Table Styles */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size: 11px; }
        td { padding: 10px; border-bottom: 1px solid #eee; color: var(--text-main); vertical-align: middle; }
        
        /* Status Badges & Selects */
        .status-select { appearance: none; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; border: 1px solid transparent; cursor: pointer; text-align: center; }
        .status-open-bg { background: #d07676ff; color: #744f05; }
        .status-completed-bg { background: #c1f0d3; color: #0c4d26; }
        .assign-select { padding: 5px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; max-width: 150px; }
        .btn-icon-save { background: none; border: none; cursor: pointer; color: var(--brand-color); margin-left: 5px; padding: 4px; }

        /* --- NIEUW: SPOTLIGHT SEARCH OVERLAY STIJLEN --- */
        #search-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(255, 255, 255, 0.65); /* Semi-transparant wit */
            backdrop-filter: blur(12px); /* DE BLUR */
            -webkit-backdrop-filter: blur(12px);
            z-index: 9999;
            display: none; /* Standaard verborgen */
            justify-content: center;
            align-items: flex-start; /* Start iets van boven */
            padding-top: 15vh;
            transition: opacity 0.2s ease;
        }

        .spotlight-container {
            width: 100%;
            max-width: 600px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
            animation: slideDown 0.2s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .spotlight-input-wrapper {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
        }

        .spotlight-icon {
            font-size: 24px;
            color: #999;
            margin-right: 15px;
        }

        #spotlight-input {
            border: none;
            font-size: 20px;
            width: 100%;
            outline: none;
            color: var(--text-main);
            font-family: inherit;
            background: transparent;
        }

        #spotlight-results {
            max-height: 400px;
            overflow-y: auto;
        }

        .result-item {
            padding: 12px 20px;
            border-bottom: 1px solid #f7f7f7;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.1s;
        }

        .result-item:hover, .result-item.active {
            background-color: #f0f4f8; /* Lichte brand color */
        }

        .result-main { font-weight: 600; font-size: 15px; color: var(--text-main); }
        .result-sub { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .result-meta { font-size: 12px; padding: 3px 8px; border-radius: 4px; background: #eee; color: #555; }
        .no-results { padding: 20px; text-align: center; color: #999; font-size: 14px; }
        
        /* Hint text onderaan zoekbalk */
        .spotlight-footer {
            padding: 8px 20px;
            background: #fafafa;
            border-top: 1px solid #eee;
            font-size: 11px;
            color: #999;
            display: flex;
            justify-content: space-between;
        }

        /* Fake search bar in header (trigger) */
        .header-search-trigger {
            background: #f3f2f2;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 8px 12px;
            width: 250px;
            color: var(--text-secondary);
            font-size: 13px;
            display: flex; align-items: center; gap: 8px;
            cursor: text;
        }
        .header-search-trigger:hover { background: #e9e9e9; }
        .kbd-shortcut {
            background: white; border: 1px solid #ccc; border-radius: 4px; padding: 0 5px; font-size: 10px; margin-left: auto;
        }

    </style>
</head>
<body>

    <div id="search-overlay">
        <div class="spotlight-container">
            <div class="spotlight-input-wrapper">
                <span class="material-icons-outlined spotlight-icon">search</span>
                <input type="text" id="spotlight-input" placeholder="Zoek dossier, chauffeur of ID..." autocomplete="off">
            </div>
            <div id="spotlight-results">
                </div>
            <div class="spotlight-footer">
                <span>Gebruik ⬆⬇ om te navigeren, <strong>Enter</strong> om te openen.</span>
                <span>ESC om te sluiten</span>
            </div>
        </div>
    </div>
    <aside class="sidebar">
        <div class="sidebar-header">LogistiekApp</div>
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php" class="active"><span class="material-icons-outlined">dashboard</span> Dashboard</a></li>
            <li class="nav-item"><a href="feedback_create.php"><span class="material-icons-outlined">add_circle</span> Nieuw Gesprek</a></li>
            <li class="nav-item"><a href="#"><span class="material-icons-outlined">people</span> Chauffeurs</a></li>
        </ul>
    </aside>

    <main class="main-content">
        
        <header class="top-header">
            <div class="header-search-trigger" onclick="openSearch()">
                <span class="material-icons-outlined" style="font-size: 18px;">search</span>
                <span>Zoeken...</span>
                <span class="kbd-shortcut">/</span>
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
            
            <?php if ($msg): ?>
                <div class="alert-toast"><span class="material-icons-outlined">info</span> Melding: <?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <div>
                    <h1 style="margin: 0; font-size: 24px; color: var(--text-main);">Dashboard</h1>
                    <div style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;">Overzicht van prestaties en taken</div>
                </div>
                <div>
                    <a href="feedback_create.php" class="btn btn-brand">
                        <span class="material-icons-outlined" style="font-size: 18px;">add</span> Nieuw Gesprek
                    </a>
                </div>
            </div>

            <div class="grid-row">
                <div class="card"><div class="card-body"><div class="kpi-value"><?php echo $stats['drivers']; ?></div><div class="kpi-label">Actieve Chauffeurs</div></div></div>
                <div class="card"><div class="card-body"><div class="kpi-value"><?php echo $stats['open_feedback']; ?></div><div class="kpi-label">Openstaande Dossiers</div></div></div>
                <div class="card"><div class="card-body"><div class="kpi-value">98%</div><div class="kpi-label">Team OTD Score</div></div></div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Recente Dossiers</h2></div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Datum</th><th>Chauffeur</th><th>Gemaakt Door</th><th>Status</th><th>Toegewezen Aan</th><th style="text-align: right;">Actie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['form_date']); ?></td>
                                    <td>
                                        <a href="feedback_view.php?id=<?php echo $row['id']; ?>" style="color: var(--brand-color); text-decoration: none; font-weight: 700;">
                                            <?php echo htmlspecialchars($row['driver_name']); ?>
                                        </a>
                                        <div style="font-size:11px; color:#999;"><?php echo htmlspecialchars($row['employee_id'] ?? ''); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['creator_email']); ?></td>
                                    <td>
                                        <span style="font-size: 11px; padding: 2px 6px; border-radius: 4px; <?php echo ($row['status']=='open' ? 'background:#fee2e2; color:#991b1b;' : 'background:#d1fae5; color:#065f46;'); ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['assigned_to_user_id']): ?>
                                            <span style="font-size:12px;"><?php echo htmlspecialchars($row['assigned_email']); ?></span>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="form_id" value="<?php echo $row['id']; ?>">
                                                <select name="assign_user_id" class="assign-select" onchange="this.form.submit()">
                                                    <option value="">Toewijzen...</option>
                                                    <?php foreach ($teamleads as $lead): ?>
                                                        <option value="<?php echo $lead['id']; ?>"><?php echo htmlspecialchars($lead['email']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="feedback_view.php?id=<?php echo $row['id']; ?>"><span class="material-icons-outlined" style="color:#999;">visibility</span></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const overlay = document.getElementById('search-overlay');
        const input = document.getElementById('spotlight-input');
        const resultsDiv = document.getElementById('spotlight-results');
        let selectedIndex = -1;

        // 1. Openen / Sluiten Functies
        function openSearch() {
            overlay.style.display = 'flex';
            input.focus();
            input.value = ''; // Reset
            resultsDiv.innerHTML = '<div class="no-results">Typ om te zoeken...</div>';
        }

        function closeSearch() {
            overlay.style.display = 'none';
        }

        // 2. Global Key Listener (Om te openen als je typt)
        document.addEventListener('keydown', (e) => {
            // Als overlay dicht is en we typen een letter of /
            if (overlay.style.display === 'none') {
                if (e.key === '/' || (e.key.length === 1 && /[a-zA-Z0-9]/.test(e.key))) {
                    e.preventDefault();
                    openSearch();
                    if(e.key !== '/') input.value = e.key; // Eerste letter invullen
                }
            } else {
                // Als overlay open is: Navigatie
                if (e.key === 'Escape') closeSearch();
                
                const items = document.querySelectorAll('.result-item');
                if (items.length > 0) {
                    if (e.key === 'ArrowDown') {
                        selectedIndex = (selectedIndex + 1) % items.length;
                        updateSelection(items);
                    } else if (e.key === 'ArrowUp') {
                        selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                        updateSelection(items);
                    } else if (e.key === 'Enter' && selectedIndex >= 0) {
                        window.location.href = items[selectedIndex].dataset.url;
                    }
                }
            }
        });

        function updateSelection(items) {
            items.forEach((item, index) => {
                if (index === selectedIndex) item.classList.add('active');
                else item.classList.remove('active');
            });
        }

        // 3. Sluiten als je buiten de box klikt
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeSearch();
        });

        // 4. Live Zoeken (AJAX)
        let debounceTimer;
        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const term = input.value.trim();
            
            if (term.length < 1) {
                resultsDiv.innerHTML = '<div class="no-results">Typ om te zoeken...</div>';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`dashboard.php?ajax_search=${encodeURIComponent(term)}`)
                    .then(response => response.json())
                    .then(data => {
                        resultsDiv.innerHTML = '';
                        selectedIndex = -1; // Reset selectie

                        if (data.length === 0) {
                            resultsDiv.innerHTML = '<div class="no-results">Geen resultaten gevonden.</div>';
                            return;
                        }

                        data.forEach((item, index) => {
                            const div = document.createElement('div');
                            div.className = 'result-item';
                            div.dataset.url = `feedback_view.php?id=${item.form_id}`;
                            div.innerHTML = `
                                <div>
                                    <div class="result-main">${item.name}</div>
                                    <div class="result-sub">ID: ${item.employee_id || '-'} • Datum: ${item.form_date}</div>
                                </div>
                                <div class="result-meta">${item.status}</div>
                            `;
                            // Klikken werkt ook
                            div.addEventListener('click', () => {
                                window.location.href = div.dataset.url;
                            });
                            // Mouseover selectie
                            div.addEventListener('mouseenter', () => {
                                selectedIndex = index;
                                updateSelection(document.querySelectorAll('.result-item'));
                            });
                            resultsDiv.appendChild(div);
                        });
                    })
                    .catch(err => console.error(err));
            }, 200); // Wacht 200ms na typen
        });
    </script>

</body>
</html>