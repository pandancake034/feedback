<?php
/**
 * FEEDBACK_CREATE.PHP
 * Module voor het inplannen van nieuwe feedback gesprekken.
 * Stijl: Enterprise (Oracle/Salesforce)
 */

// 1. CONFIGURATIE & BEVEILIGING
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// Beveiliging: Alleen ingelogde gebruikers
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. DATA OPHALEN VOOR DROPDOWNS
$drivers = [];
$users   = []; // Voor 'toewijzen aan'
$error   = "";
$success = "";

try {
    // Haal alle chauffeurs op (gesorteerd op naam)
    $stmt = $pdo->query("SELECT id, name, employee_id FROM drivers ORDER BY name ASC");
    $drivers = $stmt->fetchAll();

    // Haal mogelijke teamleads/managers op voor toewijzing
    $stmtUsers = $pdo->query("SELECT id, email FROM users ORDER BY email ASC");
    $users = $stmtUsers->fetchAll();

} catch (PDOException $e) {
    $error = "Kon gegevens niet laden: " . $e->getMessage();
}

// 3. FORMULIER VERWERKING (POST REQUEST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validatie
    if (empty($_POST['driver_id']) || empty($_POST['form_date'])) {
        $error = "Vul in elk geval de chauffeur en de gespreksdatum in.";
    } else {
        try {
            // Query voorbereiden
            $sql = "INSERT INTO feedback_forms (
                        driver_id, 
                        created_by_user_id, 
                        assigned_to_user_id, 
                        form_date, 
                        start_date, 
                        agency, 
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, 'open')";
            
            $stmt = $pdo->prepare($sql);
            
            // Uitvoeren
            $stmt->execute([
                $_POST['driver_id'],
                $_SESSION['user_id'],              // Gemaakt door (Huidige gebruiker)
                $_POST['assigned_to'] ?: NULL,     // Toegewezen aan (of NULL)
                $_POST['form_date'],
                $_POST['start_date'],
                $_POST['agency']
            ]);

            // Succes! Stuur door naar dashboard of toon melding
            header("Location: dashboard.php?msg=created");
            exit;

        } catch (PDOException $e) {
            $error = "Fout bij opslaan: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nieuw Gesprek | Chauffeurs Dossier</title>
    
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- HERGEBRUIKTE CSS VARIABELEN (Consistentie!) --- */
        :root {
            --brand-color: #0176d3;
            --brand-dark: #014486;
            --sidebar-bg: #1a2233;
            --bg-body: #f3f2f2;
            --text-main: #181818;
            --text-secondary: #706e6b;
            --border-color: #dddbda;
            --card-shadow: 0 2px 2px 0 rgba(0,0,0,0.1);
            --danger: #ea001e;
        }

        /* Basis Reset */
        body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* Sidebar & Main Layout (Exact kopie van Dashboard) */
        .sidebar { width: 240px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { height: 60px; display: flex; align-items: center; padding: 0 20px; font-size: 18px; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); }
        .nav-list { list-style: none; padding: 20px 0; margin: 0; flex-grow: 1; }
        .nav-item a { display: flex; align-items: center; padding: 12px 20px; color: #b0b6c3; text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item a:hover { background-color: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--brand-color); }
        .nav-item .material-icons-outlined { margin-right: 12px; font-size: 20px; }
        
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 10; }
        
        .page-body { padding: 24px; max-width: 800px; margin: 0 auto; width: 100%; } /* Max-width toegevoegd voor formulier leesbaarheid */

        /* --- FORMULIER SPECIFIEKE STIJLEN --- */
        .page-header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { margin: 0; font-size: 24px; color: var(--text-main); }

        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: var(--card-shadow); overflow: hidden; }
        
        /* Section Headers (Zoals in Salesforce formulieren) */
        .form-section-title {
            background-color: #f3f2f2;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            border-bottom: 1px solid var(--border-color);
            margin-top: 0;
        }

        .card-body { padding: 20px; }

        /* Form Grid */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        
        label { display: block; margin-bottom: 6px; font-size: 13px; color: var(--text-secondary); font-weight: 600; }
        
        /* Input Styling */
        input[type="text"], input[type="date"], select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #dddbda;
            border-radius: 4px;
            font-size: 14px;
            color: var(--text-main);
            transition: border 0.2s;
        }
        input:focus, select:focus {
            border-color: var(--brand-color);
            outline: none;
            box-shadow: 0 0 0 1px var(--brand-color);
        }

        /* Knoppen */
        .btn-group { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color); }
        .btn { padding: 9px 16px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid transparent; }
        .btn-cancel { background: white; border-color: var(--border-color); color: var(--text-main); }
        .btn-save { background: var(--brand-color); color: white; }
        .btn-save:hover { background-color: var(--brand-dark); }

        /* Alerts */
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 13px; }
        .alert-error { background-color: #fde8e8; color: var(--danger); border: 1px solid #fbd5d5; }

    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <span>LogistiekApp</span>
        </div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="dashboard.php">
                    <span class="material-icons-outlined">dashboard</span>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="active"> <span class="material-icons-outlined">assignment</span>
                    Nieuw Gesprek
                </a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        
        <header class="top-header">
            <div style="font-weight: 600; font-size: 14px; color: var(--text-secondary);">Feedback Module</div>
            <div style="font-size: 13px; font-weight: 600;">
                <?php echo htmlspecialchars($_SESSION['email']); ?>
            </div>
        </header>

        <div class="page-body">
            
            <div class="page-header">
                <h1>Nieuw Feedback Gesprek</h1>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span class="material-icons-outlined" style="vertical-align: bottom; font-size: 16px;">error</span> 
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="feedback_create.php">
                <div class="card">
                    
                    <h3 class="form-section-title">Informatie</h3>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="driver_id">Chauffeur *</label>
                                <select name="driver_id" id="driver_id" required>
                                    <option value="">-- Selecteer Chauffeur --</option>
                                    <?php foreach ($drivers as $driver): ?>
                                        <option value="<?php echo $driver['id']; ?>">
                                            <?php echo htmlspecialchars($driver['name']); ?> (<?php echo htmlspecialchars($driver['employee_id'] ?? ''); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="agency">Uitzendbureau</label>
                                <input type="text" name="agency" id="agency" value="YoungCapital">
                            </div>
                        </div>
                    </div>

                    <h3 class="form-section-title">Planning & Toewijzing</h3>
                    <div class="card-body">
                        <div class="form-grid">
                            
                            <div class="form-group">
                                <label for="form_date">Datum Gesprek *</label>
                                <input type="date" name="form_date" id="form_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="form-group">
                                <label for="start_date">Startdatum Periode *</label>
                                <input type="date" name="start_date" id="start_date" required value="<?php echo date('Y-m-d', strtotime('-1 week')); ?>">
                            </div>

                            <div class="form-group">
                                <label for="assigned_to">Toewijzen aan (Teamlead/Manager)</label>
                                <select name="assigned_to" id="assigned_to">
                                    <option value="">-- Mijzelf (<?php echo htmlspecialchars($_SESSION['email']); ?>) --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                        </div>

                        <div class="btn-group">
                            <a href="dashboard.php" class="btn btn-cancel">Annuleren</a>
                            <button type="submit" class="btn btn-save">Gesprek Aanmaken</button>
                        </div>
                    </div>

                </div>
            </form>

        </div>
    </main>

</body>
</html>