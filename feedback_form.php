<?php
/**
 * FEEDBACK_FORM.PHP
 * Versie: Gecentreerd formulier & Dynamische velden (Algemeen vs KPI)
 * Update: Notitieblok rechts verwijderd, formulier weer gecentreerd.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

// Check login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// --- 1. INITIALISATIE ---
$form_id = $_GET['id'] ?? null;
$is_new  = empty($form_id);

$error   = "";
$drivers = []; 
$prev_forms = []; // Voor dropdown bij algemene beoordeling

// Standaard waarden instellen
$data = [
    'driver_id'   => '', 
    'driver_name' => '', 
    'employee_id' => '', 
    'agency' => '', 
    'form_date' => date('Y-m-d'), 
    'start_date' => date('Y-m-d', strtotime('-1 week')),
    'review_moment' => '',
    
    // KPI Velden
    'otd_score' => '', 
    'ftr_score' => '', 
    'kw_score' => '', 
    'routes_count' => 0,
    'errors_text' => '', 
    'nokd_text' => '', 
    'late_text' => '', 
    'driving_behavior' => '',
    'warnings' => '', 
    'client_compliment' => '',
    'skills_rating' => '', 
    'proficiency_rating' => 0,
    
    // NIEUWE VELDEN (Algemeen gesprek)
    'conversation_reason' => '',
    'general_comments' => '',
    'agreements' => '',
    'misc_comments' => '',
    'linked_form_id' => '',

    'status' => 'open'
];

// --- 2. DATA OPHALEN ---

// A. Drivers lijst voor dropdown
try {
    $stmtD = $pdo->query("SELECT id, name, employee_id FROM drivers ORDER BY name ASC");
    $drivers = $stmtD->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* Negeer */ }

// B. Bestaand dossier ophalen (bij bewerken)
if (!$is_new) {
    try {
        $stmt = $pdo->prepare("SELECT f.*, d.name as driver_name, d.employee_id 
                               FROM feedback_forms f 
                               JOIN drivers d ON f.driver_id = d.id 
                               WHERE f.id = ?");
        $stmt->execute([$form_id]);
        $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fetched) {
            $data = array_merge($data, $fetched);
        } else {
            die("Formulier niet gevonden.");
        }

        // C. Haal eerdere gesprekken op voor referentie
        if (!empty($data['driver_id'])) {
            $stmtPrev = $pdo->prepare("SELECT id, form_date, review_moment FROM feedback_forms WHERE driver_id = ? AND id != ? ORDER BY form_date DESC");
            $stmtPrev->execute([$data['driver_id'], $form_id]);
            $prev_forms = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        die("Fout: " . $e->getMessage());
    }
}

// --- 3. OPSLAAN (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    try {
        $pdo->beginTransaction();

        $driver_id = null;
        
        // 3A. LOGICA: BEPALEN WELKE CHAUFFEUR
        if ($is_new && isset($_POST['driver_mode']) && $_POST['driver_mode'] === 'existing') {
            if (empty($_POST['existing_driver_id'])) {
                throw new Exception("Selecteer een chauffeur uit de lijst.");
            }
            $driver_id = $_POST['existing_driver_id'];
        
        } else {
            // Nieuwe chauffeur of naam update
            $driver_name = trim($_POST['driver_name'] ?? '');
            $employee_id = trim($_POST['employee_id'] ?? '');

            if (!empty($driver_name) && !empty($employee_id)) {
                $stmt = $pdo->prepare("SELECT id FROM drivers WHERE employee_id = ?");
                $stmt->execute([$employee_id]);
                $driverRow = $stmt->fetch();

                if ($driverRow) {
                    $driver_id = $driverRow['id'];
                    $pdo->prepare("UPDATE drivers SET name = ? WHERE id = ?")->execute([$driver_name, $driver_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO drivers (name, employee_id) VALUES (?, ?)");
                    $stmt->execute([$driver_name, $employee_id]);
                    $driver_id = $pdo->lastInsertId();
                }
            } elseif (!$is_new) {
                $driver_id = $data['driver_id']; 
            } else {
                 throw new Exception("Vul naam en personeelsnummer in.");
            }
        }

        // 3B. Formulier Opslaan
        $new_status = (isset($_POST['action']) && $_POST['action'] === 'complete') ? 'completed' : 'open';

        // Scores formatteren
        $otd_score = str_replace('%', '', $_POST['otd_score'] ?? '') . '%'; if($otd_score === '%') $otd_score = '';
        $ftr_score = str_replace('%', '', $_POST['ftr_score'] ?? '') . '%'; if($ftr_score === '%') $ftr_score = '';

        // Alle parameters verzamelen
        $params = [
            $driver_id,
            $_SESSION['user_id'], // created_by
            $_POST['form_date'],
            $_POST['start_date'],
            $_POST['review_moment'],
            $_POST['agency'],
            $_POST['routes_count'] ?? 0,
            $otd_score,
            $ftr_score,
            $_POST['errors_text'] ?? '',
            '', 
            $_POST['late_text'] ?? '',
            $_POST['driving_behavior'] ?? '',
            $_POST['warnings'] ?? '',
            $_POST['kw_score'] ?? '',
            $_POST['skills_rating'] ?? '',
            $_POST['proficiency_rating'] ?? 0,
            $_POST['client_compliment'] ?? '',
            
            // NIEUWE VELDEN
            $_POST['conversation_reason'] ?? '',
            $_POST['general_comments'] ?? '',
            $_POST['agreements'] ?? '',
            $_POST['misc_comments'] ?? '',
            !empty($_POST['linked_form_id']) ? $_POST['linked_form_id'] : null,
            
            $new_status
        ];

        if ($is_new) {
            // INSERT
            $sql = "INSERT INTO feedback_forms (
                        driver_id, created_by_user_id, form_date, start_date, review_moment, agency,
                        routes_count, otd_score, ftr_score, errors_text, nokd_text, late_text,
                        driving_behavior, warnings, kw_score, skills_rating, proficiency_rating, client_compliment,
                        conversation_reason, general_comments, agreements, misc_comments, linked_form_id,
                        status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
        } else {
            // UPDATE
            array_splice($params, 1, 1); 
            $params[] = $form_id;

            $sql = "UPDATE feedback_forms SET 
                        driver_id = ?, form_date = ?, start_date = ?, review_moment = ?, agency = ?,
                        routes_count = ?, otd_score = ?, ftr_score = ?, errors_text = ?, nokd_text = ?, late_text = ?,
                        driving_behavior = ?, warnings = ?, kw_score = ?, skills_rating = ?, proficiency_rating = ?, client_compliment = ?,
                        conversation_reason = ?, general_comments = ?, agreements = ?, misc_comments = ?, linked_form_id = ?,
                        status = ?, updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        $pdo->commit();
        header("Location: dashboard.php?msg=" . ($new_status === 'completed' ? 'completed' : 'saved'));
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
    <title><?php echo APP_TITLE; ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- CORE THEME --- */
        :root { --brand-color: #0176d3; --brand-dark: #014486; --sidebar-bg: #1a2233; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }
        
        .sidebar { width: 240px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        /* Gecentreerde pagina */
        .page-body { padding: 24px; max-width: 1000px; margin: 0 auto; width: 100%; padding-bottom: 100px; }

        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 2px rgba(0,0,0,0.1); margin-bottom: 24px; overflow: hidden; }
        .form-section-title { background: #f8f9fa; padding: 12px 20px; font-weight: 700; border-bottom: 1px solid var(--border-color); margin: 0; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .card-body { padding: 20px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--text-secondary); }
        input, select, textarea { width: 100%; padding: 8px 12px; border: 1px solid #dddbda; border-radius: 4px; font-size: 14px; font-family: inherit; }
        input:focus, textarea:focus { border-color: var(--brand-color); outline: none; }
        textarea { resize: vertical; min-height: 80px; }

        /* Toggle & Skills */
        .toggle-wrapper { display: inline-flex; background: #f3f2f2; padding: 4px; border-radius: 6px; border: 1px solid var(--border-color); }
        .toggle-wrapper input[type="radio"] { display: none; }
        .toggle-option { padding: 8px 16px; font-size: 13px; font-weight: 600; color: var(--text-secondary); cursor: pointer; border-radius: 4px; transition: all 0.2s ease; user-select: none; }
        .toggle-wrapper input[type="radio"]:checked + .toggle-option { background-color: white; color: var(--brand-color); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        
        .hidden { display: none; }
        
        .skills-container { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; min-height: 38px; }
        .skill-tag { background-color: #e0e7ff; color: var(--brand-dark); border: 1px solid rgba(1, 118, 211, 0.3); padding: 4px 12px; border-radius: 16px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .skill-tag span { cursor: pointer; font-weight: bold; }

        .action-bar { position: fixed; bottom: 0; left: 240px; right: 0; background: white; border-top: 1px solid var(--border-color); padding: 15px 24px; display: flex; justify-content: flex-end; gap: 10px; z-index: 100; }
        .btn { padding: 9px 16px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; text-decoration: none; }
        .btn-cancel { background: white; border-color: var(--border-color); color: var(--text-main); }
        .btn-save { background: var(--brand-color); color: white; }
        
        .section-highlight { border-left: 4px solid var(--brand-color); background: #fdfdfd; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div style="height: 60px; display: flex; align-items: center; padding: 0 20px; font-weight: 700; background: rgba(0,0,0,0.2);">FeedbackFlow</div>
        <div style="padding: 20px;">
            <a href="dashboard.php" style="color: #b0b6c3; text-decoration: none; display: flex; align-items: center; gap: 10px;">
                <span class="material-icons-outlined">dashboard</span> Terug naar Dashboard
            </a>
        </div>
    </aside>

    <main class="main-content">