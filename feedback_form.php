<?php
/**
 * FEEDBACK_FORM.PHP
 * Versie: Split-Screen & Dynamische velden (Algemeen vs KPI)
 * Gebruikt de bestaande logica en voegt nieuwe functies toe.
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
$notes_history = []; // Voor rechter zijbalk

// Standaard waarden instellen
$data = [
    'driver_id'   => '', 
    'driver_name' => '', 
    'employee_id' => '', 
    'agency' => '', 
    'form_date' => date('Y-m-d'), 
    'start_date' => date('Y-m-d', strtotime('-1 week')),
    'review_moment' => '',
    
    // KPI Velden (Huidige code)
    'otd_score' => '', 
    'ftr_score' => '', 
    'kw_score' => '', 
    'routes_count' => 0,
    'errors_text' => '', 
    'nokd_text' => '', // Veld uit jouw DB schema
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

        // C. Haal notities op voor zijbalk (NIEUW)
        if (!empty($data['driver_id'])) {
            $stmtNotes = $pdo->prepare("SELECT n.*, u.first_name, u.last_name, u.email 
                                        FROM notes n 
                                        LEFT JOIN users u ON n.user_id = u.id 
                                        WHERE n.driver_id = ? 
                                        ORDER BY n.note_date DESC LIMIT 10");
            $stmtNotes->execute([$data['driver_id']]);
            $notes_history = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

            // D. Haal eerdere gesprekken op voor referentie
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

        // Alle parameters verzamelen (KPI + NIEUWE VELDEN)
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
            '', // nokd_text (laten we leeg of vul hier iets in als je dat veld gebruikt)
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
            // UPDATE (Let op: created_by_user_id halen we uit de params want die updaten we niet)
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
        /* --- BESTAANDE STYLE (Enterprise Theme) --- */
        :root { --brand-color: #0176d3; --brand-dark: #014486; --sidebar-bg: #1a2233; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }
        
        .sidebar { width: 240px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        /* NIEUWE SPLIT LAYOUT */
        .page-wrapper { display: flex; gap: 24px; padding: 24px; max-width: 1600px; margin: 0 auto; width: 100%; align-items: flex-start; padding-bottom: 100px; }
        .col-form { flex: 2; min-width: 0; }
        .col-notes { flex: 1; min-width: 350px; position: sticky; top: 24px; }

        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 2px rgba(0,0,0,0.1); margin-bottom: 24px; overflow: hidden; }
        .form-section-title { background: #f8f9fa; padding: 12px 20px; font-weight: 700; border-bottom: 1px solid var(--border-color); margin: 0; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .card-body { padding: 20px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--text-secondary); }
        input, select, textarea { width: 100%; padding: 8px 12px; border: 1px solid #dddbda; border-radius: 4px; font-size: 14px; font-family: inherit; }
        input:focus, textarea:focus { border-color: var(--brand-color); outline: none; }
        textarea { resize: vertical; min-height: 80px; }

        /* Notes Styling */
        .notes-list { max-height: 600px; overflow-y: auto; padding-right: 5px; }
        .note-item { background: #fcfcfc; border: 1px solid #eee; border-radius: 6px; padding: 12px; margin-bottom: 10px; font-size: 13px; }
        .note-meta { display: flex; justify-content: space-between; color: #999; font-size: 11px; margin-bottom: 6px; }

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
        <form method="POST">
            <div class="page-wrapper">
                
                <div class="col-form">
                    <h1 style="margin-top: 0; margin-bottom: 20px;">
                        <?php echo $is_new ? 'Nieuw feedbackgesprek' : 'Gesprek Bewerken'; ?>
                    </h1>

                    <?php if ($error): ?>
                        <div style="background: #fde8e8; color: #ea001e; padding: 10px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #fbd5d5;"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <div class="card">
                        <h3 class="form-section-title"><span class="material-icons-outlined">person</span> 1. Chauffeur & Planning</h3>
                        <div class="card-body">
                            
                            <?php if ($is_new): ?>
                                <div class="form-group">
                                    <label style="margin-bottom:8px;">Wie wil je bespreken?</label>
                                    <div class="toggle-wrapper">
                                        <input type="radio" name="driver_mode" id="mode_existing" value="existing" checked onclick="toggleDriverMode('existing')">
                                        <label for="mode_existing" class="toggle-option">Bestaande chauffeur</label>

                                        <input type="radio" name="driver_mode" id="mode_new" value="new" onclick="toggleDriverMode('new')">
                                        <label for="mode_new" class="toggle-option">Nieuwe chauffeur</label>
                                    </div>
                                </div>

                                <div id="block-existing" class="form-group" style="margin-top: 15px;">
                                    <label>Selecteer chauffeur *</label>
                                    <select name="existing_driver_id">
                                        <option value="">-- Kies uit lijst --</option>
                                        <?php foreach ($drivers as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php if(isset($_GET['prefill_driver']) && $_GET['prefill_driver'] == $d['id']) echo 'selected'; ?>><?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['employee_id']); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div id="block-new" class="form-grid hidden" style="margin-top: 15px;">
                                    <div class="form-group"><label>Naam chauffeur *</label><input type="text" name="driver_name" placeholder="Bijv. Jan Jansen"></div>
                                    <div class="form-group"><label>Personeelsnummer *</label><input type="text" name="employee_id" placeholder="Bijv. 12345"></div>
                                </div>
                            <?php else: ?>
                                <div class="form-grid">
                                    <div class="form-group"><label>Naam chauffeur *</label><input type="text" name="driver_name" value="<?php echo htmlspecialchars($data['driver_name']); ?>" required></div>
                                    <div class="form-group"><label>Personeelsnummer *</label><input type="text" name="employee_id" value="<?php echo htmlspecialchars($data['employee_id']); ?>" required></div>
                                </div>
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Uitzendbureau:</label>
                                    <input type="text" name="agency" list="agency_options" value="<?php echo htmlspecialchars($data['agency']); ?>" placeholder="Typ of kies...">
                                    <datalist id="agency_options"><option value="Young Capital"><option value="LevelWorks"><option value="NowJobs"><option value="Timing"></datalist>
                                </div>
                                <div class="form-group"><label>Datum gesprek *</label><input type="date" name="form_date" value="<?php echo htmlspecialchars($data['form_date']); ?>" required></div>
                                <div class="form-group"><label>Startdatum chauffeur *</label><input type="date" name="start_date" value="<?php echo htmlspecialchars($data['start_date']); ?>" required></div>
                                <div class="form-group">
                                    <label style="color:var(--brand-color);">Beoordelingsmoment *</label>
                                    <select name="review_moment" id="reviewSelector" onchange="toggleFormType()" style="border:2px solid var(--brand-color); background:#f0f9ff; font-weight:600;">
                                        <option value="">-- Kies --</option>
                                        <option value="8 ritten" <?php if($data['review_moment'] == '8 ritten' || $data['review_moment'] == '8 weken') echo 'selected'; ?>>8 ritten</option>
                                        <option value="40 ritten" <?php if($data['review_moment'] == '40 ritten') echo 'selected'; ?>>40 ritten</option>
                                        <option value="80 ritten" <?php if($data['review_moment'] == '80 ritten') echo 'selected'; ?>>80 ritten</option>
                                        <option value="Algemene beoordeling" <?php if($data['review_moment'] == 'Algemene beoordeling') echo 'selected'; ?>>Algemene beoordeling (Nieuw)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="kpiForm">
                        <div class="card">
                            <h3 class="form-section-title"><span class="material-icons-outlined">analytics</span> 2. Prestaties</h3>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group"><label>OTD score:</label><input type="text" name="otd_score" id="otd_score" value="<?php echo htmlspecialchars($data['otd_score']); ?>" placeholder="98%"></div>
                                    <div class="form-group"><label>FTR score:</label><input type="text" name="ftr_score" id="ftr_score" value="<?php echo htmlspecialchars($data['ftr_score']); ?>" placeholder="99.5%"></div>
                                    <div class="form-group"><label>KW verbruik E-vito:</label><input type="text" name="kw_score" value="<?php echo htmlspecialchars($data['kw_score']); ?>"></div>
                                    <div class="form-group"><label>Aantal routes:</label><input type="number" name="routes_count" value="<?php echo htmlspecialchars($data['routes_count']); ?>"></div>
                                </div>
                                <div class="form-grid" style="margin-top: 15px;">
                                    <div class="form-group"><label>Fouten (errors):</label><textarea name="errors_text"><?php echo htmlspecialchars($data['errors_text']); ?></textarea></div>
                                    <div class="form-group"><label>Te laat:</label><textarea name="late_text"><?php echo htmlspecialchars($data['late_text']); ?></textarea></div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <h3 class="form-section-title"><span class="material-icons-outlined">psychology</span> 3. Gedrag & Beoordeling</h3>
                            <div class="card-body">
                                <div class="form-group"><label>Rijgedrag & Communicatie</label><textarea name="driving_behavior"><?php echo htmlspecialchars($data['driving_behavior']); ?></textarea></div>
                                <div class="form-group"><label>Waarschuwingen</label><textarea name="warnings" style="border-color: #fca5a5;"><?php echo htmlspecialchars($data['warnings']); ?></textarea></div>
                                <div class="form-group"><label>Complimenten</label><textarea name="client_compliment" style="border-color: #86efac;"><?php echo htmlspecialchars($data['client_compliment']); ?></textarea></div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Skills / Rollen</label>
                                        <select id="skillSelector" onchange="addSkill(this.value)">
                                            <option value="">-- Kies een skill --</option>
                                            <option value="Laden+rit">Laden+rit</option><option value="Laden/Reserve">Laden/Reserve</option><option value="Parkeerwachter">Parkeerwachter</option><option value="Inchecken">Inchecken</option><option value="Laadcoördinator">Laadcoördinator</option>
                                        </select>
                                        <div id="skillsContainer" class="skills-container"></div>
                                        <input type="hidden" name="skills_rating" id="skillsInput" value="<?php echo htmlspecialchars($data['skills_rating']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Proficiency Level (1-14)</label>
                                        <select name="proficiency_rating">
                                            <option value="0">-- Kies --</option>
                                            <?php for($i=1;$i<=14;$i++): ?><option value="<?php echo $i; ?>" <?php if($data['proficiency_rating']==$i) echo 'selected'; ?>><?php echo $i; ?></option><?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="generalForm" class="hidden">
                        <div class="card section-highlight">
                            <h3 class="form-section-title" style="background:#e0e7ff; color:#014486;">
                                <span class="material-icons-outlined">forum</span> 2. Algemeen Gesprek
                            </h3>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Reden gesprek</label>
                                        <input type="text" name="conversation_reason" placeholder="Bijv. Functioneren, Klacht, Verzuim..." value="<?php echo htmlspecialchars($data['conversation_reason']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Vorig gesprek (Referentie)</label>
                                        <select name="linked_form_id">
                                            <option value="">-- Geen --</option>
                                            <?php foreach($prev_forms as $pf): ?>
                                                <option value="<?php echo $pf['id']; ?>" <?php if($data['linked_form_id'] == $pf['id']) echo 'selected'; ?>>
                                                    <?php echo date('d-m-Y', strtotime($pf['form_date'])) . ' - ' . $pf['review_moment']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Opmerkingen & Vragen</label>
                                    <textarea name="general_comments" style="min-height:120px;" placeholder="Wat is er besproken?"><?php echo htmlspecialchars($data['general_comments']); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Gemaakte Afspraken</label>
                                    <textarea name="agreements" style="min-height:100px; border-color:#86efac;" placeholder="Wat spreken we af?"><?php echo htmlspecialchars($data['agreements']); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Overige</label>
                                    <textarea name="misc_comments"><?php echo htmlspecialchars($data['misc_comments']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="col-notes">
                    <div class="card">
                        <h3 class="form-section-title"><span class="material-icons-outlined">history_edu</span> Notitie Historie</h3>
                        <div class="card-body">
                            <p style="font-size:12px; color:#777; margin-top:0;">Laatste 10 notities:</p>
                            <div class="notes-list">
                                <?php if(empty($notes_history)): ?>
                                    <div style="text-align:center; color:#aaa; font-style:italic; padding:10px;">Geen notities.</div>
                                <?php else: ?>
                                    <?php foreach($notes_history as $n): ?>
                                        <div class="note-item">
                                            <div class="note-meta">
                                                <span><?php echo htmlspecialchars($n['first_name'] ?: $n['email']); ?></span>
                                                <span><?php echo date('d-m-Y', strtotime($n['note_date'])); ?></span>
                                            </div>
                                            <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($n['content']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="action-bar">
                <a href="dashboard.php" class="btn btn-cancel">Annuleren</a>
                <button type="submit" name="action" value="save" class="btn btn-save" style="background-color: white; color: var(--brand-color); border: 1px solid var(--brand-color);">Concept Opslaan</button>
                <button type="submit" name="action" value="complete" class="btn btn-save">Opslaan</button>
            </div>
        </form>
    </main>
    
    <script>
        // 1. WISSELEN FORM TYPE
        function toggleFormType() {
            const selector = document.getElementById('reviewSelector');
            const kpiForm = document.getElementById('kpiForm');
            const generalForm = document.getElementById('generalForm');
            
            if(selector && kpiForm && generalForm) {
                if (selector.value === 'Algemene beoordeling') {
                    kpiForm.style.display = 'none';
                    generalForm.style.display = 'block';
                } else {
                    kpiForm.style.display = 'block';
                    generalForm.style.display = 'none';
                }
            }
        }

        // 2. TOGGLE DRIVER MODE
        function toggleDriverMode(mode) {
            const blockExisting = document.getElementById('block-existing');
            const blockNew = document.getElementById('block-new');
            if (mode === 'existing') {
                blockExisting.classList.remove('hidden'); blockNew.classList.add('hidden');
            } else {
                blockExisting.classList.add('hidden'); blockNew.classList.remove('hidden');
            }
        }

        // 3. SKILLS TAGS
        const skillsInput = document.getElementById('skillsInput');
        const skillsContainer = document.getElementById('skillsContainer');
        const skillSelector = document.getElementById('skillSelector');
        let currentSkills = skillsInput && skillsInput.value ? skillsInput.value.split(',').filter(s => s.trim() !== '') : [];

        function renderSkills() {
            if(!skillsContainer) return;
            skillsContainer.innerHTML = ''; 
            currentSkills.forEach((skill, index) => {
                const tag = document.createElement('div');
                tag.className = 'skill-tag';
                tag.innerHTML = `${skill} <span onclick="removeSkill(${index})">&times;</span>`;
                skillsContainer.appendChild(tag);
            });
            if(skillsInput) skillsInput.value = currentSkills.join(',');
        }
        function addSkill(skill) {
            if (skill && !currentSkills.includes(skill)) { currentSkills.push(skill); renderSkills(); }
            if(skillSelector) skillSelector.value = "";
        }
        function removeSkill(index) { currentSkills.splice(index, 1); renderSkills(); }

        // Formatting
        function formatPercentage(input) {
            let val = input.value.trim().replace(/%/g, '');
            if (val !== '') input.value = val + '%';
        }
        const otdInput = document.getElementById('otd_score');
        const ftrInput = document.getElementById('ftr_score');
        if (otdInput) otdInput.addEventListener('blur', function() { formatPercentage(this); });
        if (ftrInput) ftrInput.addEventListener('blur', function() { formatPercentage(this); });

        // Init
        window.addEventListener('DOMContentLoaded', () => {
            toggleFormType();
            renderSkills();
        });
    </script>
</body>
</html>