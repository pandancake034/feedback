<?php
/**
 * DASHBOARD.PHP
 * Hoofdpagina van de applicatie.
 * * Best Practices toegepast:
 * 1. Strict Type & Error Handling (Try-Catch)
 * 2. Separation of Concerns (Logica boven, View beneden)
 * 3. Security (htmlspecialchars tegen XSS, sessie validatie)
 */

// 1. Configuratie & Database laden
require_once __DIR__ . '/config/config.php'; // Gebruik __DIR__ voor absoluut pad
require_once __DIR__ . '/config/db.php';     // Database verbinding

// 2. Beveiliging: Sessie controle
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 3. Data Integriteit: Check of cruciale sessie data bestaat
// Dit voorkomt de "Undefined array key" fouten die je eerder had
if (!isset($_SESSION['role']) || !isset($_SESSION['email'])) {
    session_destroy();
    header("Location: login.php?error=session_expired");
    exit;
}

// Variabelen voor de view
$userId    = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];
$userRole  = ucfirst($_SESSION['role']); // Eerste letter hoofdletter (admin -> Admin)

// 4. Data Ophalen (Statistieken)
// We gebruiken try-catch blokken zodat het dashboard blijft werken, zelfs als een query faalt.
$stats = [
    'drivers' => 0,
    'open_feedback' => 0,
    'my_reviews' => 0
];

$recentActivities = [];

try {
    // Totaal aantal chauffeurs
    $stmt = $pdo->query("SELECT COUNT(*) FROM drivers");
    $stats['drivers'] = $stmt->fetchColumn();

    // Openstaande feedback formulieren
    $stmt = $pdo->query("SELECT COUNT(*) FROM feedback_forms WHERE status = 'open'");
    $stats['open_feedback'] = $stmt->fetchColumn();

    // Recente feedback ophalen (met JOIN om namen te tonen i.p.v. ID's)
    // Dit is een 'best practice' query: haal alleen op wat je nodig hebt (LIMIT 5)
    $sqlRecent = "
        SELECT 
            f.id, 
            f.form_date, 
            d.name as driver_name, 
            u.email as creator_email,
            f.status 
        FROM feedback_forms f
        JOIN drivers d ON f.driver_id = d.id
        JOIN users u ON f.created_by_user_id = u.id
        ORDER BY f.created_at DESC 
        LIMIT 5
    ";
    $recentActivities = $pdo->query($sqlRecent)->fetchAll();

} catch (PDOException $e) {
    // In productie log je dit naar een bestand, niet naar het scherm
    $dbError = "Kon statistieken niet laden.";
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Chauffeurs Dossier</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        /* --- CSS VARIABLES (Theming) --- */
        :root {
            --primary: #2563eb;       /* Modern Blauw */
            --primary-dark: #1e40af;
            --secondary: #64748b;     /* Grijs-blauw voor tekst */
            --bg-body: #f1f5f9;       /* Lichte achtergrond */
            --bg-card: #ffffff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* --- RESET & BASIS --- */
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: #1e293b;
            margin: 0;
            padding-bottom: 40px;
        }

        /* --- NAVIGATIE --- */
        .navbar {
            background-color: var(--bg-card);
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-brand { font-weight: 700; font-size: 1.25rem; color: var(--primary); display: flex; align-items: center; gap: 10px;}
        .navbar-user { display: flex; align-items: center; gap: 15px; font-size: 0.9rem; }
        .role-badge { 
            background: #e0e7ff; color: var(--primary); 
            padding: 4px 8px; border-radius: 4px; 
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase; 
        }
        .btn-logout {
            color: var(--secondary); text-decoration: none; border: 1px solid #cbd5e1;
            padding: 6px 12px; border-radius: 6px; transition: all 0.2s;
        }
        .btn-logout:hover { background-color: #f8fafc; color: var(--danger); border-color: var(--danger); }

        /* --- LAYOUT CONTAINER --- */
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }

        /* --- GRID SYSTEEM --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* --- KAARTEN (CARDS) --- */
        .card {
            background: var(--bg-card);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        /* Stat Cards specifiek */
        .stat-card { display: flex; align-items: center; justify-content: space-between; }
        .stat-info h3 { margin: 0; font-size: 0.875rem; color: var(--secondary); font-weight: 500; }
        .stat-info .value { font-size: 2rem; font-weight: 700; color: #0f172a; margin-top: 5px; display: block; }
        .stat-icon { 
            width: 48px; height: 48px; border-radius: 12px; 
            background: #eff6ff; color: var(--primary);
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        }

        /* --- TABELLEN --- */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #e2e8f0; color: var(--secondary); font-weight: 600; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        /* Status Labels */
        .status-badge {
            padding: 4px 8px; border-radius: 99px; font-size: 0.75rem; font-weight: 600;
        }
        .status-open { background-color: #fef3c7; color: #b45309; } /* Geel/Oranje */
        .status-completed { background-color: #d1fae5; color: #065f46; } /* Groen */

        /* --- ACTIE KNOPPEN --- */
        .action-bar { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .btn-primary {
            background-color: var(--primary); color: white; text-decoration: none;
            padding: 10px 20px; border-radius: 8px; font-weight: 500;
            display: inline-flex; align-items: center; gap: 8px;
            transition: background 0.2s; box-shadow: 0 2px 4px rgba(37,99,235,0.2);
        }
        .btn-primary:hover { background-color: var(--primary-dark); }
        
        /* Welkomst Header */
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { margin: 0 0 0.5rem 0; font-size: 1.8rem; }
        .page-header p { margin: 0; color: var(--secondary); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">
            <span>🚛</span> Chauffeurs Dossier
        </div>
        <div class="navbar-user">
            <span>
                <?php echo htmlspecialchars($userEmail); ?> 
                <span class="role-badge"><?php echo htmlspecialchars($userRole); ?></span>
            </span>
            <a href="logout.php" class="btn-logout">Uitloggen</a>
        </div>
    </nav>

    <div class="container">
        
        <div class="page-header">
            <h1>Welkom terug!</h1>
            <p>Hier is een overzicht van de prestaties en openstaande taken.</p>
        </div>

        <div class="action-bar">
            <a href="#" class="btn-primary">
                <span>+</span> Nieuwe Chauffeur
            </a>
            <a href="#" class="btn-primary" style="background-color: #0f172a;">
                <span>📝</span> Feedback Starten
            </a>
        </div>

        <div class="dashboard-grid">
            <div class="card stat-card">
                <div class="stat-info">
                    <h3>Actieve Chauffeurs</h3>
                    <span class="value"><?php echo $stats['drivers']; ?></span>
                </div>
                <div class="stat-icon">👥</div>
            </div>

            <div class="card stat-card">
                <div class="stat-info">
                    <h3>Openstaande Dossiers</h3>
                    <span class="value"><?php echo $stats['open_feedback']; ?></span>
                </div>
                <div class="stat-icon" style="background: #fffbeb; color: #f59e0b;">⚠️</div>
            </div>

            <div class="card stat-card">
                <div class="stat-info">
                    <h3>Mijn Team</h3>
                    <span class="value">-</span>
                </div>
                <div class="stat-icon" style="background: #f0fdf4; color: #10b981;">🛡️</div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0; margin-bottom: 1rem;">Recente Feedback Dossiers</h3>
            
            <?php if (empty($recentActivities)): ?>
                <p style="color: var(--secondary); font-style: italic;">Nog geen feedback formulieren gevonden.</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Chauffeur</th>
                                <th>Gemaakt door</th>
                                <th>Status</th>
                                <th>Actie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivities as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['form_date']); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['driver_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['creator_email']); ?></td>
                                <td>
                                    <?php 
                                        $statusClass = ($row['status'] === 'open') ? 'status-open' : 'status-completed';
                                        // Vertaal status naar NL (hoewel database Engels is)
                                        $displayStatus = ($row['status'] === 'open') ? 'Open' : 'Afgerond';
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $displayStatus; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="#" style="color: var(--primary); text-decoration: none; font-weight: 600;">Bekijk &rarr;</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>