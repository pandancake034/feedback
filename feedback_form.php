<?php
/**
 * FEEDBACK_FORM.PHP
 * Versie: Split-Screen & Dynamische velden (Algemeen vs KPI)
 * Database schema: Based on provided 'describe' output + new ALTER fields.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

// Beveiliging
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// --- 1. INITIALISATIE ---
$form_id = $_GET['id'] ?? null;
$is_new  = empty($form_id);

$error   = "";
$drivers = []; 
$prev_forms = []; // Voor dropdown "Referentie naar vorig gesprek"
$notes_history = []; // Voor rechter zijbalk

// Standaard waarden (overeenkomend met jouw tabelstructuur)
$data = [
    // Driver info (uit joins)
    'driver_id'   => '', 
    'driver_name' => '', 
    'employee_id' => '', 
    'agency' => '', 
    
    // Feedback Forms velden
    'form_date' => date('Y-m-d'), 
    'start_date' => date('Y-m-d', strtotime('-1 week')),
    'review_moment' => '',
    'routes_count' => 0,
    'otd_score' => '', 
    'ftr_score' => '', 
    'errors_text' => '', 
    'nokd_text' => '', // Bestaat in jouw DB schema
    'late_text' => '', 
    'driving_behavior' => '',
    'warnings' => '', 
    'kw_score' => '', 
    'skills_rating' => '', 
    'proficiency_rating' => 0,
    'client_compliment' => '',
    'status' => 'open',

    // NIEUWE VELDEN (Na de SQL ALTER)
    'conversation_reason' => '',
    'general_comments' => '',
    'agreements' => '',
    'misc_comments' => '',
    'linked_form_id' => ''
];

// --- 2. DATA OPHALEN ---

// A. Drivers lijst
try {
    $stmtD = $pdo->query("SELECT id, name, employee_id FROM drivers ORDER BY name ASC");
    $drivers = $stmtD->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* Negeer */ }

// B. Bestaand dossier ophalen (Edit modus)
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

        // C. Haal notitie historie op (voor rechterbalk)
        if (!empty($data['driver_id'])) {
            $stmtNotes = $pdo->prepare("SELECT n.content, n.note_date, u.first_name, u.last_name, u.email 
                                        FROM notes n 
                                        LEFT JOIN users u ON n.user_id = u.id 
                                        WHERE n.driver_id = ? 
                                        ORDER BY n.note_date DESC LIMIT 10");
            $stmtNotes->execute([$data['driver_id']]);
            $notes_history = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

            // D. Haal eerdere gesprekken op (voor referentie dropdown)
            $stmtPrev = $pdo->prepare("SELECT id, form_date, review_moment FROM feedback_forms WHERE driver_id = ? AND id != ? ORDER BY form_date DESC");
            $stmtPrev->execute([$data['driver_id'], $form_id]);
            $prev_forms = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        die("Database fout: " . $e->getMessage());
    }
}

// --- 3. OPSLAAN (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    try {
        $pdo->beginTransaction();

        // Stap 1: Chauffeur bepalen of aanmaken
        $driver_id = null;
        
        if ($is_new && isset($_POST['driver_mode']) && $_POST['driver_mode'] === 'existing') {
            if (empty($_POST['existing_driver_id'])) throw new Exception("Kies een chauffeur.");
            $driver_id = $_POST['existing_driver_id'];
        } else {
            // Nieuwe chauffeur of naam update
            $driver_name = trim($_POST['driver_name'] ?? '');
            $employee_id = trim($_POST['employee_id'] ?? '');

            if (!empty($driver_name) && !empty($employee_id)) {
                // Check of ID al bestaat
                $stmt = $pdo->prepare("SELECT id FROM drivers WHERE employee_id = ?");
                $stmt->execute([$employee_id]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $driver_id = $existing['id'];
                    // Update naam als die anders is
                    $pdo->prepare("UPDATE drivers SET name = ? WHERE id = ?")->execute([$driver_name, $driver_id]);
                } else {
                    // Insert in 'drivers' tabel (id, name, employee_id, assigned_teamleader_id, created_at)
                    $stmt = $pdo->prepare("INSERT INTO drivers (name, employee_id) VALUES (?, ?)");
                    $stmt->execute([$driver_name, $employee_id]);
                    $driver_id = $pdo->lastInsertId();
                }
            } elseif (!$is_new && !empty($data['driver_id'])) {
                $driver_id = $data['driver_id']; // Bestaande ID behouden bij edit
            } else {
                throw new Exception("Vul naam en personeelsnummer in.");
            }
        }

        // Stap 2: Data voorbereiden voor feedback_forms
        $status = ($_POST['action'] === 'complete') ? 'completed' : 'open';

        // Formatteer percentages
        $otd = str_replace('%', '', $_POST['otd_score'] ?? '') . '%'; if($otd === '%') $otd = '';
        $ftr = str_replace('%', '', $_POST['ftr_score'] ?? '') . '%'; if($ftr === '%') $ftr = '';

        // Alle velden verzamelen voor de query
        $params = [
            $driver_id,
            $_POST['form_date'],
            $_POST['start_date'],
            $_POST['review_moment'],
            $_POST['agency'],
            $_POST['routes_count'] ?? 0,
            $otd,
            $ftr,
            $_POST['errors_text'] ?? '',
            '', // nokd_text (laten we leeg of vul hier iets in als je dat veld gebruikt)
            $_POST['late_text'] ?? '',
            $_POST['driving_behavior'] ?? '',
            $_POST['warnings'] ?? '',
            $_POST['kw_score'] ?? '',
            $_POST['skills_rating'] ?? '',
            $_POST['proficiency_rating'] ?? 0,
            $_POST['client_compliment'] ?? '',
            
            // Nieuwe velden
            $_POST['conversation_reason'] ?? '',
            $_POST['general_comments'] ?? '',
            $_POST['agreements'] ?? '',
            $_POST['misc_comments'] ?? '',
            !empty($_POST['linked_form_id']) ? $_POST['linked_form_id'] : null,
            
            $status
        ];

        if ($is_new) {
            // INSERT
            $sql = "INSERT INTO feedback_forms (
                driver_id, created_by_user_id, form_date, start_date, review_moment, agency,
                routes_count, otd_score, ftr_score, errors_text, nokd_text, late_text,
                driving_behavior, warnings, kw_score, skills_rating, proficiency_rating, client_compliment,
                conversation_reason, general_comments, agreements, misc_comments, linked_form_id,
                status, created_at, updated_at
            ) VALUES (?, " . $_SESSION['user_id'] . ", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

        } else {
            // UPDATE
            $sql = "UPDATE feedback_forms SET 
                driver_id=?, form_date=?, start_date=?, review_moment=?, agency=?,
                routes_count=?, otd_score=?, ftr_score=?, errors_text=?, nokd_text=?, late_text=?,
                driving_behavior=?, warnings=?, kw_score=?, skills_rating=?, proficiency_rating=?, client_compliment=?,
                conversation_reason=?, general_comments=?, agreements=?, misc_comments=?, linked_form_id=?,
                status=?, updated_at=NOW()
                WHERE id = ?";
            
            $params[] = $form_id; // ID toevoegen aan einde array
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        $pdo->commit();
        header("Location: dashboard.php?msg=" . ($status === 'completed' ? 'completed' : 'saved'));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Opslaan mislukt: " . $e->getMessage();
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
        /* --- CORE STYLING --- */
        :root { --brand-color: #0176d3; --brand-dark: #014486; --bg-body: #f3f2f2; --text-main: #181818; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* Layout */
        .sidebar { width: 240px; background: #1a2233; color: white; flex-shrink: 0; display: flex; flex-direction: column; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        /* Split Screen Layout */
        .page-wrapper { display: flex; gap: 24px; padding: 24px; max-width: 1600px; margin: 0 auto; width: 100%; align-items: flex-start; }
        .col-form { flex: 2; min-width: 0; } /* Formulier (Links) */
        .col-notes { flex: 1; min-width: 350px; position: sticky; top: 24px; } /* Notities (Rechts) */

        /* Cards & Form */
        .card { background: white; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card-header { padding: 12px 20px; background: #f8f9fa; border-bottom: 1px solid var(--border-color); font-weight: 700; color: var(--brand-dark); font-size: 14px; display:flex; align-items:center; gap:8px; }
        .card-body { padding: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #555; }
        input, select, textarea { width: 100%; padding: 9px 12px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; font-size: 14px; }
        input:focus, textarea:focus { border-color: var(--brand-color); outline: none; }
        textarea { resize: vertical; min-height: 80px; }

        /* Notes Styling */
        .notes-list { max-height: 600px; overflow-y: auto; padding-right: 5px; }
        .note-item { background: #fcfcfc; border: 1px solid #eee; border-radius: 6px; padding: 12px; margin-bottom: 10px; font-size: 13px; }
        .note-meta { display: flex; justify-content: space-between; color: #999; font-size: 11px; margin-bottom: 6px; }
        .empty-notes { text-align: center; color: #aaa; font-style: italic; padding: 20px; }

        /* Action Bar */
        .action-bar { position: fixed; bottom: 0; left: 240px; right: 0; background: white; border-top: 1px solid var(--border-color); padding: 15px 24px; display: flex; justify-content: flex-end; gap: 10px; z-index: 99; }
        .btn { padding: 9px 20px; border-radius: 4px; font-weight: 600; cursor: pointer; border: none; font-size: 14px; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
        .btn-cancel { background: white; border: 1px solid #ccc; color: #333; }
        .btn-save { background: var(--brand-color); color: white; }
        .btn-draft { background: #fff; color: var(--brand-color); border: 1px solid var(--brand-color); }

        .hidden { display: none; }
        .skill-tag { background: #e0e7ff; color: #014486; padding: 4px 10px; border-radius: 12px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; margin: 4px 4px 0 0; }
        
        /* Highlight voor Algemene Beoordeling */
        .section-highlight { border-left: 4px solid var(--brand-color); background: #fdfdfd; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div style="padding: 20px; font-weight: 700; background: rgba(0,0,0,0.2);">FeedbackFlow</div>
        <div style="padding: 20px;">
            <a href="dashboard.php" style="color: #b0b6c3; text-decoration: none; display: flex; align-items: center; gap: 10px;">
                <span class="material-icons-outlined">arrow_back</span> Terug naar Dashboard
            </a>
        </div>
    </aside>

    <main class="main-content">
        <form method="POST">
            <div class="page-wrapper">
                
                <div class="col-form">
                    <h1 style="margin-top:0; margin-bottom:24px;">
                        <?php echo $is_new ? 'Nieuw Gesprek' : 'Gesprek Bewerken'; ?>
                        <?php if(!$is_new) echo '<span style="font-size:16px; color:#777; font-weight:400;">(' . htmlspecialchars($data['driver_name']) . ')</span>'; ?>
                    </h1>

                    <?php if ($error): ?>
                        <div style="background:#fde8e8; color:#ea001e; padding:12px; border-radius:4px; margin-bottom:20px; border:1px solid #fbd5d5;">
                            <span class="material-icons-outlined" style="vertical-align:middle; font-size:18px;">error</span> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header"><span class="material-icons-outlined">person</span> 1. Chauffeur & Planning</div>
                        <div class="card-body">
                            
                            <?php if ($is_new): ?>
                                <div class="form-group">
                                    <div style="display:flex; gap:20px; margin-bottom:15px; background:#f9f9f9; padding:10px; border-radius:4px;">
                                        <label style="margin:0; cursor:pointer;"><input type="radio" name="driver_mode" value="existing" checked onclick="toggleDriverMode('existing')"> Bestaande chauffeur</label>
                                        <label style="margin:0; cursor:pointer;"><input type="radio" name="driver_mode" value="new" onclick="toggleDriverMode('new')"> + Nieuwe aanmaken</label>
                                    </div>
                                    
                                    <div id="block-existing">
                                        <label>Selecteer chauffeur:</label>
                                        <select name="existing_driver_id">
                                            <option value="">-- Zoek op naam --</option>
                                            <?php foreach ($drivers as $d): ?>
                                                <option value="<?php echo $d['id']; ?>" <?php if(isset($_GET['prefill_driver']) && $_GET['prefill_driver'] == $d['id']) echo 'selected'; ?>>
                                                    <?php echo htmlspecialchars($d['name']); ?> (<?php echo $d['employee_id']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div id="block-new" class="hidden form-grid">
                                        <div><label>Volledige Naam</label><input type="text" name="driver_name" placeholder="Bijv. Jan Jansen"></div>
                                        <div><label>Personeelsnummer</label><input type="text" name="employee_id" placeholder="Bijv. 12345"></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="form-grid">
                                    <div class="form-group"><label>Naam</label><input type="text" name="driver_name" value="<?php echo htmlspecialchars($data['driver_name']); ?>"></div>
                                    <div class="form-group"><label>Personeelsnummer</label><input type="text" name="employee_id" value="<?php echo htmlspecialchars($data['employee_id']); ?>"></div>
                                </div>
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Uitzendbureau</label>
                                    <input type="text" name="agency" list="agencies" value="<?php echo htmlspecialchars($data['agency']); ?>">
                                    <datalist id="agencies"><option value="Young Capital"><option value="Timing"></datalist>
                                </div>
                                <div class="form-group"><label>Datum gesprek</label><input type="date" name="form_date" value="<?php echo htmlspecialchars($data['form_date']); ?>"></div>
                                <div class="form-group"><label>Startdatum</label><input type="date" name="start_date" value="<?php echo htmlspecialchars($data['start_date']); ?>"></div>
                                
                                <div class="form-group">
                                    <label style="color:var(--brand-color);">Beoordelingsmoment *</label>
                                    <select name="review_moment" id="reviewSelector" onchange="toggleFormType()" style="border:2px solid var(--brand-color); background:#f0f9ff; font-weight:600;">
                                        <option value="">-- Maak een keuze --</option>
                                        <option value="8 ritten" <?php if($data['review_moment']=='8 ritten') echo 'selected'; ?>>8 ritten</option>
                                        <option value="40 ritten" <?php if($data['review_moment']=='40 ritten') echo 'selected'; ?>>40 ritten</option>
                                        <option value="80 ritten" <?php if($data['review_moment']=='80 ritten') echo 'selected'; ?>>80 ritten</option>
                                        <option value="Algemene beoordeling" <?php if($data['review_moment']=='Algemene beoordeling') echo 'selected'; ?>>Algemene beoordeling (Nieuw)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="kpiForm">
                        <div class="card">
                            <div class="card-header"><span class="material-icons-outlined">analytics</span> 2. Prestaties (KPI's)</div>
                            <div class="card-body">
                                <div class="form-grid">
                                    <div class="form-group"><label>OTD Score</label><input type="text" name="otd_score" value="<?php echo htmlspecialchars($data['otd_score']); ?>" placeholder="%"></div>
                                    <div class="form-group"><label>FTR Score</label><input type="text" name="ftr_score" value="<?php echo htmlspecialchars($data['ftr_score']); ?>" placeholder="%"></div>
                                    <div class="form-group"><label>KW Verbruik</label><input type="text" name="kw_score" value="<?php echo htmlspecialchars($data['kw_score']); ?>"></div>
                                    <div class="form-group"><label>Aantal Routes</label><input type="number" name="routes_count" value="<?php echo htmlspecialchars($data['routes_count']); ?>"></div>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group"><label>Errors</label><textarea name="errors_text"><?php echo htmlspecialchars($data['errors_text']); ?></textarea></div>
                                    <div class="form-group"><label>Te Laat</label><textarea name="late_text"><?php echo htmlspecialchars($data['late_text']); ?></textarea></div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><span class="material-icons-outlined">psychology</span> 3. Gedrag & Skills</div>
                            <div class="card-body">
                                <div class="form-group"><label>Rijgedrag</label><textarea name="driving_behavior"><?php echo htmlspecialchars($data['driving_behavior']); ?></textarea></div>
                                <div class="form-group"><label>Waarschuwingen</label><textarea name="warnings" style="border-color:#fca5a5;"><?php echo htmlspecialchars($data['warnings']); ?></textarea></div>
                                <div class="form-group"><label>Complimenten</label><textarea name="client_compliment" style="border-color:#86efac;"><?php echo htmlspecialchars($data['client_compliment']); ?></textarea></div>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Skills</label>
                                        <select id="skillSelector" onchange="addSkill(this.value)">
                                            <option value="">-- Voeg toe --</option>
                                            <option>Laden+rit</option><option>Parkeerwachter</option><option>Laadcoördinator</option><option>Inchecken</option>
                                        </select>
                                        <div id="skillsContainer" style="margin-top:10px; min-height:24px;"></div>
                                        <input type="hidden" name="skills_rating" id="skillsInput" value="<?php echo htmlspecialchars($data['skills_rating']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Proficiency (Cijfer 1-14)</label>
                                        <input type="number" name="proficiency_rating" value="<?php echo $data['proficiency_rating']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="generalForm" class="hidden">
                        <div class="card section-highlight">
                            <div class="card-header" style="background:#e0e7ff; color:#014486;">
                                <span class="material-icons-outlined">forum</span> 2. Algemeen Gesprek
                            </div>
                            <div class="card-body">
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Reden van gesprek</label>
                                        <input type="text" name="conversation_reason" placeholder="Bijv. Functioneringsgesprek, Klacht, Verzuim..." value="<?php echo htmlspecialchars($data['conversation_reason']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Referentie naar vorig gesprek (Optioneel)</label>
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
                                    <label>Overige opmerkingen</label>
                                    <textarea name="misc_comments"><?php echo htmlspecialchars($data['misc_comments']); ?></textarea>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>

                <div class="col-notes">
                    <div class="card">
                        <div class="card-header">
                            <span class="material-icons-outlined">history_edu</span> Notitie Historie
                        </div>
                        <div class="card-body">
                            <p style="font-size:12px; color:#777; margin-top:0;">
                                Laatste 10 notities bij deze chauffeur:
                            </p>
                            
                            <div class="notes-list">
                                <?php if(empty($notes_history)): ?>
                                    <div class="empty-notes">Geen notities gevonden.</div>
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
                            
                            <?php if(!empty($data['driver_id'])): ?>
                                <div style="margin-top:15px; text-align:center;">
                                    <a href="feedback_view.php?id=<?php echo $form_id ?: ''; ?>&driver_id=<?php echo $data['driver_id']; ?>" target="_blank" style="font-size:12px; color:var(--brand-color);">
                                        Bekijk volledig dossier <span class="material-icons-outlined" style="font-size:10px;">open_in_new</span>
                                    </a>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>

            </div>

            <div class="action-bar">
                <a href="dashboard.php" class="btn btn-cancel">Annuleren</a>
                <button type="submit" name="action" value="save" class="btn btn-draft">Concept Opslaan</button>
                <button type="submit" name="action" value="complete" class="btn btn-save">Afronden</button>
            </div>
        </form>
    </main>

    <script>
        // 1. WISSELEN TUSSEN KPI EN ALGEMEEN FORM
        function toggleFormType() {
            const selector = document.getElementById('reviewSelector');
            const kpiForm = document.getElementById('kpiForm');
            const generalForm = document.getElementById('generalForm');

            if (!selector || !kpiForm || !generalForm) return;

            if (selector.value === 'Algemene beoordeling') {
                kpiForm.style.display = 'none';
                generalForm.style.display = 'block';
            } else {
                kpiForm.style.display = 'block';
                generalForm.style.display = 'none';
            }
        }

        // 2. CHAUFFEUR MODE (NIEUW/BESTAAND)
        function toggleDriverMode(mode) {
            const blockExisting = document.getElementById('block-existing');
            const blockNew = document.getElementById('block-new');
            if(mode === 'new'){
                blockExisting.classList.add('hidden'); blockNew.classList.remove('hidden');
            } else {
                blockExisting.classList.remove('hidden'); blockNew.classList.add('hidden');
            }
        }

        // 3. SKILLS LOGICA
        const skillsInput = document.getElementById('skillsInput');
        const skillsContainer = document.getElementById('skillsContainer');
        let currentSkills = skillsInput.value ? skillsInput.value.split(',') : [];

        function renderSkills() {
            skillsContainer.innerHTML = '';
            currentSkills.forEach((skill, idx) => {
                if(skill.trim() === '') return;
                const tag = document.createElement('div');
                tag.className = 'skill-tag';
                tag.innerHTML = `${skill} <span style="cursor:pointer;" onclick="removeSkill(${idx})">&times;</span>`;
                skillsContainer.appendChild(tag);
            });
            skillsInput.value = currentSkills.join(',');
        }
        function addSkill(val) {
            if(val && !currentSkills.includes(val)) { currentSkills.push(val); renderSkills(); }
            document.getElementById('skillSelector').value = '';
        }
        function removeSkill(idx) { currentSkills.splice(idx, 1); renderSkills(); }

        // INIT
        window.addEventListener('DOMContentLoaded', () => {
            toggleFormType(); 
            renderSkills();
        });
    </script>
</body>
</html>