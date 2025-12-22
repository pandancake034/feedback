<?php
/**
 * FEEDBACK_CREATE.PHP
 * Module voor het inplannen van nieuwe feedback gesprekken.
 * Nu met optie om direct een NIEUWE chauffeur aan te maken.
 */

// 1. CONFIGURATIE & BEVEILIGING
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// Beveiliging: Alleen ingelogde gebruikers
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. DATA OPHALEN
$drivers = [];
$users   = []; 
$error   = "";
$prefill_driver_id = $_GET['prefill_driver'] ?? null;

try {
    // Haal chauffeurs op
    $stmt = $pdo->query("SELECT id, name, employee_id FROM drivers ORDER BY name ASC");
    $drivers = $stmt->fetchAll();

    // Haal teamleads op
    $stmtUsers = $pdo->query("SELECT id, email FROM users ORDER BY email ASC");
    $users = $stmtUsers->fetchAll();

} catch (PDOException $e) {
    $error = "Kon gegevens niet laden: " . $e->getMessage();
}

// 3. FORMULIER VERWERKING
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check of we een nieuwe chauffeur maken of een bestaande kiezen
    $is_new_driver = isset($_POST['driver_mode']) && $_POST['driver_mode'] === 'new';
    $driver_id = null;

    try {
        $pdo->beginTransaction(); // Start transactie voor veiligheid

        // STAP A: Bepaal Driver ID
        if ($is_new_driver) {
            // Validatie voor nieuwe chauffeur
            if (empty($_POST['new_driver_name']) || empty($_POST['new_employee_id'])) {
                throw new Exception("Vul naam en personeelsnummer in voor de nieuwe chauffeur.");
            }
            
            // Maak nieuwe chauffeur aan
            $stmtNew = $pdo->prepare("INSERT INTO drivers (name, employee_id) VALUES (?, ?)");
            $stmtNew->execute([trim($_POST['new_driver_name']), trim($_POST['new_employee_id'])]);
            $driver_id = $pdo->lastInsertId();

        } else {
            // Bestaande chauffeur
            if (empty($_POST['driver_id'])) {
                throw new Exception("Kies een chauffeur uit de lijst.");
            }
            $driver_id = $_POST['driver_id'];
        }

        // STAP B: Maak het formulier aan
        $sql = "INSERT INTO feedback_forms (
                    driver_id, created_by_user_id, assigned_to_user_id, 
                    form_date, start_date, agency, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'open')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $driver_id,
            $_SESSION['user_id'],
            $_POST['assigned_to'] ?: NULL,
            $_POST['form_date'],
            $_POST['start_date'],
            $_POST['agency']
        ]);

        $pdo->commit(); // Alles opslaan
        header("Location: dashboard.php?msg=created");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Fout: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Nieuw Gesprek</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- STYLING (Hetzelfde als voorheen) --- */
        :root { --brand-color: #0176d3; --brand-dark: #014486; --sidebar-bg: #1a2233; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }
        .sidebar { width: 240px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .page-body { padding: 24px; max-width: 800px; margin: 0 auto; width: 100%; }
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; overflow: hidden; margin-bottom: 20px; }
        .form-section-title { background: #f3f2f2; padding: 10px 20px; font-weight: 700; border-bottom: 1px solid var(--border-color); margin: 0; }
        .card-body { padding: 20px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--text-secondary); }
        input, select { width: 100%; padding: 8px 12px; border: 1px solid #dddbda; border-radius: 4px; font-size: 14px; }
        .btn { padding: 9px 16px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; border: none; }
        .btn-save { background: var(--brand-color); color: white; float: right; }
        .alert-error { background: #fde8e8; color: #ea001e; padding: 10px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #fbd5d5; }
        
        /* Toggle Switch Stijl */
        .toggle-container { display: flex; gap: 15px; margin-bottom: 15px; }
        .radio-label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 14px; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-body">
            <h1 style="margin-bottom: 20px;">Nieuw Feedback Gesprek</h1>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="card">
                    <h3 class="form-section-title">1. Kies Chauffeur</h3>
                    <div class="card-body">
                        
                        <div class="form-group">
                            <label>Wie wil je bespreken?</label>
                            <div class="toggle-container">
                                <label class="radio-label">
                                    <input type="radio" name="driver_mode" value="existing" checked onclick="toggleMode('existing')">
                                    Bestaande chauffeur
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="driver_mode" value="new" onclick="toggleMode('new')">
                                    + Nieuwe chauffeur toevoegen
                                </label>
                            </div>
                        </div>

                        <div id="block-existing">
                            <div class="form-group">
                                <label>Selecteer uit lijst</label>
                                <select name="driver_id">
                                    <option value="">-- Zoek chauffeur --</option>
                                    <?php foreach ($drivers as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php if($prefill_driver_id == $d['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['employee_id'] ?? ''); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="block-new" style="display: none;">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Volledige Naam</label>
                                    <input type="text" name="new_driver_name" placeholder="Bijv. Piet Jansen">
                                </div>
                                <div class="form-group">
                                    <label>Personeelsnummer</label>
                                    <input type="text" name="new_employee_id" placeholder="Bijv. 12345">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Uitzendbureau</label>
                            <input type="text" name="agency" value="YoungCapital">
                        </div>

                    </div>

                    <h3 class="form-section-title">2. Planning</h3>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Datum Gesprek</label>
                                <input type="date" name="form_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Startdatum Periode</label>
                                <input type="date" name="start_date" required value="<?php echo date('Y-m-d', strtotime('-1 week')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Toewijzen aan</label>
                                <select name="assigned_to">
                                    <option value="">-- Mijzelf --</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['email']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="text-align: right; padding-top: 10px; border-top: 1px solid #eee;">
                            <a href="dashboard.php" style="color: #666; text-decoration: none; margin-right: 15px; font-size: 13px;">Annuleren</a>
                            <button type="submit" class="btn btn-save">Gesprek Starten</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script>
        function toggleMode(mode) {
            const blockExisting = document.getElementById('block-existing');
            const blockNew = document.getElementById('block-new');
            
            if (mode === 'new') {
                blockExisting.style.display = 'none';
                blockNew.style.display = 'block';
                // Velden verplicht maken als ze zichtbaar zijn helpt browser validatie
                document.querySelector('[name="new_driver_name"]').required = true;
                document.querySelector('[name="new_employee_id"]').required = true;
                document.querySelector('[name="driver_id"]').required = false;
            } else {
                blockExisting.style.display = 'block';
                blockNew.style.display = 'none';
                document.querySelector('[name="new_driver_name"]').required = false;
                document.querySelector('[name="new_employee_id"]').required = false;
                document.querySelector('[name="driver_id"]').required = true;
            }
        }
    </script>

</body>
</html>