<?php
// dashboard.php
require_once 'config/config.php';
require_once 'config/db.php';

// 1. BEVEILIGING: Check of de gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. DATA INTEGRITEIT CHECK (Dit lost jouw error op!)
// Als 'role' of 'email' mist in de sessie, is de login corrupt.
if (!isset($_SESSION['role']) || !isset($_SESSION['email'])) {
    // Stuur door naar uitloggen om de foutieve sessie te wissen
    header("Location: logout.php");
    exit;
}

// 3. Variabelen instellen voor gebruik in de HTML
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_email = $_SESSION['email'];

// ... hieronder volgt de rest van je PHP logica (zoals statistieken ophalen) ...
try {
    $stmtDrivers = $pdo->query("SELECT COUNT(*) FROM drivers");
    $countDrivers = $stmtDrivers->fetchColumn();
    
    $stmtForms = $pdo->query("SELECT COUNT(*) FROM feedback_forms WHERE status = 'open'");
    $countOpenForms = $stmtForms->fetchColumn();
} catch (PDOException $e) {
    $countDrivers = 0;
    $countOpenForms = 0;
}
?>
<!DOCTYPE html>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Chauffeurs Dossier</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; }
        
        /* Navigatie */
        nav { background-color: #343a40; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        nav h1 { margin: 0; font-size: 20px; font-weight: 500; }
        .nav-links a { color: #ccc; text-decoration: none; margin-left: 20px; }
        .nav-links a:hover { color: white; }
        .logout-btn { background-color: #dc3545; padding: 8px 15px; border-radius: 4px; color: white !important; }

        /* Container */
        .container { padding: 30px; max-width: 1000px; margin: 0 auto; }
        
        /* Welkomstkaart */
        .welcome-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; border-left: 5px solid #007bff; }
        
        /* Grid voor statistieken */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; }
        .stat-number { font-size: 32px; font-weight: bold; color: #007bff; display: block; margin-bottom: 5px; }
        .stat-label { color: #666; font-size: 14px; }

        /* Actieknoppen */
        .actions { margin-top: 30px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>

    <nav>
        <h1>Chauffeurs Dossier</h1>
        <div class="nav-links">
            <span>Ingelogd als: <strong><?php echo htmlspecialchars($user_email); ?></strong> (<?php echo ucfirst($user_role); ?>)</span>
            <a href="logout.php" class="logout-btn">Uitloggen</a>
        </div>
    </nav>

    <div class="container">
        
        <div class="welcome-card">
            <h2>Welkom terug!</h2>
            <p>Je hebt toegang tot het systeem als <strong><?php echo htmlspecialchars($user_role); ?></strong>.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo $countDrivers; ?></span>
                <span class="stat-label">Geregistreerde Chauffeurs</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $countOpenForms; ?></span>
                <span class="stat-label">Openstaande Feedback Gesprekken</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">0</span> <span class="stat-label">Mijn Taken</span>
            </div>
        </div>

        <div class="actions">
            <h3>Snelle Acties</h3>
            <a href="#" class="btn">Nieuwe Chauffeur</a>
            <a href="#" class="btn">Feedback Gesprek Starten</a>
            
            <?php if($user_role === 'admin'): ?>
                <a href="#" class="btn" style="background-color: #6c757d;">Beheer Gebruikers (Admin)</a>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>