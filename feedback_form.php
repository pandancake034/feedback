<?php
/**
 * FEEDBACK_FORM.PHP
 * Het invulformulier voor een specifiek feedback gesprek.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// 1. BEVEILIGING & CONTROLES
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check of er een ID is meegegeven
if (!isset($_GET['id'])) {
    header("Location: dashboard.php"); // Geen ID? Terug naar dashboard.
    exit;
}

$form_id = $_GET['id'];
$error   = "";
$success = "";

// 2. DATA OPHALEN
try {
    // Haal formulier data op + naam van de chauffeur
    $sql = "SELECT f.*, d.name as driver_name, d.employee_id 
            FROM feedback_forms f 
            JOIN drivers d ON f.driver_id = d.id 
            WHERE f.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$form_id]);
    $form = $stmt->fetch();

    if (!$form) {
        die("Formulier niet gevonden.");
    }

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

// 3. OPSLAAN (POST VERWERKING)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Bepaal de status: Als er op "Afronden" is geklikt, wordt status 'completed'
        $new_status = (isset($_POST['action']) && $_POST['action'] === 'complete') ? 'completed' : 'open';

        $sql = "UPDATE feedback_forms SET 
                    routes_count = ?,
                    otd_score = ?,
                    ftr_score = ?,
                    errors_text = ?,
                    nokd_text = ?,
                    late_text = ?,
                    driving_behavior = ?,
                    warnings = ?,
                    kw_score = ?,
                    skills_rating = ?,
                    proficiency_rating = ?,
                    client_compliment = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['routes_count'] ?? 0,
            $_POST['otd_score'],
            $_POST['ftr_score'],
            $_POST['errors_text'],
            $_POST['nokd_text'],
            $_POST['late_text'],
            $_POST['driving_behavior'],
            $_POST['warnings'],
            $_POST['kw_score'],
            $_POST['skills_rating'],
            $_POST['proficiency_rating'],
            $_POST['client_compliment'],
            $new_status,
            $form_id
        ]);

        // Herlaad de pagina om de nieuwe waarden te tonen
        if ($new_status === 'completed') {
            header("Location: dashboard.php?msg=completed");
            exit;
        } else {
            $success = "Wijzigingen succesvol opgeslagen.";
            // Ververs data
            $stmt = $pdo->prepare("SELECT f.*, d.name as driver_name, d.employee_id FROM feedback_forms f JOIN drivers d ON f.driver_id = d.id WHERE f.id = ?");
            $stmt->execute([$form_id]);
            $form = $stmt->fetch();
        }

    } catch (PDOException $e) {
        $error = "Fout bij opslaan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Invullen | <?php echo htmlspecialchars($form['driver_name']); ?></title>
    
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- CSS (Zelfde Enterprise Stijl) --- */
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

        /* Sidebar & Layout */
        .sidebar { width: 240px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { height: 60px; display: flex; align-items: center; padding: 0 20px; font-size: 18px; font-weight: 700; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); }
        .nav-list { list-style: none; padding: 20px 0; margin: 0; flex-grow: 1; }
        .nav-item a { display: flex; align-items: center; padding: 12px 20px; color: #b0b6c3; text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item a:hover { background-color: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--brand-color); }
        .nav-item .material-icons-outlined { margin-right: 12px; font-size: 20px; }
        
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); position: sticky; top: 0; z-index: 10; }
        
        .page-body { padding: 24px; max-width: 1000px; margin: 0 auto; width: 100%; padding-bottom: 100px; }

        /* Header Info Card */
        .info-card {
            background: white; border: 1px solid var(--border-color); border-radius: 4px; padding: 16px 24px;
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;
            box-shadow: var(--card-shadow);
        }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 12px; color: var(--text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 4px; }
        .info-value { font-size: 16px; font-weight: 600; color: var(--text-main); }

        /* Form Sections */
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: var(--card-shadow); margin-bottom: 24px; overflow: hidden; }
        .form-section-title { background-color: #f8f9fa; padding: 12px 20px; font-size: 14px; font-weight: 700; color: var(--text-main); border-bottom: 1px solid var(--border-color); margin: 0; }
        .card-body { padding: 20px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
        .form-group { margin-bottom: 16px; }
        
        label { display: block; margin-bottom: 6px; font-size: 13px; color: var(--text-secondary); font-weight: 600; }
        input[type="text"], input[type="number"], textarea, select {
            width: 100%; padding: 8px 12px; border: 1px solid #dddbda; border-radius: 4px;
            font-size: 14px; color: var(--text-main); font-family: inherit;
        }
        input:focus, textarea:focus { border-color: var(--brand-color); outline: none; box-shadow: 0 0 0 1px var(--brand-color); }
        textarea { resize: vertical; min-height: 80px; }

        /* Sticky Footer Action Bar */
        .action-bar {
            position: fixed; bottom: 0; left: 240px; right: 0; 
            background: white; border-top: 1px solid var(--border-color);
            padding: 16px 24px; display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        .btn { padding: 9px 16px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; text-decoration: none; }
        .btn-cancel { background: white; border-color: var(--border-color); color: var(--text-main); }
        .btn-save { background: white; border-color: var(--brand-color); color: var(--brand-color); margin-right: 10px; }
        .btn-primary { background: var(--brand-color); color: white; }
        .btn-primary:hover { background: var(--brand-dark); }
        
        .alert-success { background: var(--success-bg); color: var(--success-text); padding: 10px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #a7f3d0; font-size: 14px; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">LogistiekApp</div>
        <ul class="nav-list">
            <li class="nav-item"><a href="dashboard.php"><span class="material-icons-outlined">dashboard</span>Dashboard</a></li>
            <li class="nav-item"><a href="#"><span class="material-icons-outlined">people</span>Chauffeurs</a></li>
            <li class="nav-item"><a href="#" class="active"><span class="material-icons-outlined">edit_note</span>Feedback Invoer</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div style="font-weight: 600; color: var(--text-secondary);">Dossier Bewerken</div>
            <div style="font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
        </header>

        <div class="page-body">
            
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                <a href="dashboard.php" style="color: var(--brand-color); text-decoration: none; display: flex; align-items: center; font-size: 13px;">
                    <span class="material-icons-outlined" style="font-size: 16px;">arrow_back</span> Terug
                </a>
                <h1 style="margin: 0; font-size: 24px;">Feedback Gesprek</h1>
            </div>

            <?php if ($success): ?>
                <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                
                <div class="info-card">
                    <div class="info-item">
                        <span class="info-label">Chauffeur</span>
                        <span class="info-value"><?php echo htmlspecialchars($form['driver_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">ID Nummer</span>
                        <span class="info-value"><?php echo htmlspecialchars($form['employee_id'] ?? '-'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Datum Gesprek</span>
                        <span class="info-value"><?php echo htmlspecialchars($form['form_date']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="info-value" style="color: <?php echo $form['status'] == 'open' ? '#d97706' : '#059669'; ?>;">
                            <?php echo ucfirst($form['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="card">
                    <h3 class="form-section-title">1. Prestaties & Statistieken</h3>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>OTD Score (On Time Delivery)</label>
                                <input type="text" name="otd_score" placeholder="Bijv: 98%" value="<?php echo htmlspecialchars($form['otd_score'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>FTR Score (First Time Right)</label>
                                <input type="text" name="ftr_score" placeholder="Bijv: 99.5%" value="<?php echo htmlspecialchars($form['ftr_score'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>KW Score</label>
                                <input type="text" name="kw_score" value="<?php echo htmlspecialchars($form['kw_score'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Aantal Routes</label>
                                <input type="number" name="routes_count" value="<?php echo htmlspecialchars($form['routes_count'] ?? '0'); ?>">
                            </div>
                        </div>
                        
                        <div class="form-grid" style="margin-top: 15px;">
                            <div class="form-group">
                                <label>Fouten / Errors (Tekst)</label>
                                <textarea name="errors_text" placeholder="- 2 fouten vorige week..."><?php echo htmlspecialchars($form['errors_text'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Te Laat / Late (Tekst)</label>
                                <textarea name="late_text" placeholder="3x te laat inclusief..."><?php echo htmlspecialchars($form['late_text'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3 class="form-section-title">2. Gedrag & Houding</h3>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Rijgedrag & Communicatie</label>
                            <textarea name="driving_behavior" placeholder="Opmerkingen over rijgedrag, boetes of communicatie..."><?php echo htmlspecialchars($form['driving_behavior'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Waarschuwingen / Officiële Waarschuwing (OW)</label>
                            <textarea name="warnings" style="border-color: #fca5a5;"><?php echo htmlspecialchars($form['warnings'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Compliment van Klant / Opmerkingen</label>
                            <textarea name="client_compliment" style="border-color: #86efac;"><?php echo htmlspecialchars($form['client_compliment'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3 class="form-section-title">3. Eindbeoordeling</h3>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Algemene Vaardigheden (1-5)</label>
                                <select name="skills_rating">
                                    <option value="">-- Kies --</option>
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php if(($form['skills_rating'] ?? 0) == $i) echo 'selected'; ?>>
                                            <?php echo $i; ?> Ster<?php echo $i>1?'ren':''; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Proficiency / Vakbekwaamheid (1-5)</label>
                                <select name="proficiency_rating">
                                    <option value="">-- Kies --</option>
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php if(($form['proficiency_rating'] ?? 0) == $i) echo 'selected'; ?>>
                                            <?php echo $i; ?> Ster<?php echo $i>1?'ren':''; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="action-bar">
                    <a href="dashboard.php" class="btn btn-cancel">Annuleren</a>
                    <div>
                        <button type="submit" name="action" value="save" class="btn btn-save">Concept Opslaan</button>
                        
                        <button type="submit" name="action" value="complete" class="btn btn-primary" onclick="return confirm('Weet je zeker dat je dit dossier wilt afronden?');">
                            Gesprek Afronden
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </main>

</body>
</html>