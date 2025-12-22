<?php
/**
 * FEEDBACK_FORM.PHP
 * - Create & Edit functionaliteit
 * - Toggle voor Bestaande/Nieuwe chauffeur bij aanmaken
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// Check login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

// --- 1. INITIALISATIE ---
$form_id = $_GET['id'] ?? null;
$is_new  = empty($form_id);

$error   = "";
$success = "";
$drivers = []; // Lijst voor dropdown

// Drivers ophalen voor de dropdown (nodig bij 'Nieuw')
try {
    $stmtD = $pdo->query("SELECT id, name, employee_id FROM drivers ORDER BY name ASC");
    $drivers = $stmtD->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* Negeer */ }


// Standaard waarden instellen
$data = [
    'driver_id'   => '', // Nieuw veld voor ID
    'driver_name' => '', 
    'employee_id' => '', 
    'agency' => '', 
    'form_date' => date('Y-m-d'), 
    'start_date' => date('Y-m-d', strtotime('-1 week')),
    'review_moment' => '',
    'otd_score' => '', 
    'ftr_score' => '', 
    'kw_score' => '', 
    'routes_count' => 0,
    'errors_text' => '', 
    'late_text' => '', 
    'driving_behavior' => '',
    'warnings' => '', 
    'client_compliment' => '',
    'skills_rating' => 0, 
    'proficiency_rating' => 0,
    'status' => 'open'
];

// --- 2. DATA OPHALEN (Alleen bij bewerken) ---
if (!$is_new) {
    try {
        $stmt = $pdo->prepare("SELECT f.*, d.name as driver_name, d.employee_id 
                               FROM feedback_forms f 
                               JOIN drivers d ON f.driver_id = d.id 
                               WHERE f.id = ?");
        $stmt->execute([$form_id]);
        $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fetched) {
            $data = $fetched;
        } else {
            die("Formulier niet gevonden.");
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
        
        // LOGICA: BEPALEN WELKE CHAUFFEUR
        // Check 1: Is er een bestaande chauffeur gekozen via dropdown? (Alleen bij nieuw)
        if ($is_new && isset($_POST['driver_mode']) && $_POST['driver_mode'] === 'existing') {
            if (empty($_POST['existing_driver_id'])) {
                throw new Exception("Selecteer een chauffeur uit de lijst.");
            }
            $driver_id = $_POST['existing_driver_id'];
        
        // Check 2: Nieuwe chauffeur of Bewerken bestaande (Text inputs)
        } else {
            $driver_name = trim($_POST['driver_name'] ?? '');
            $employee_id = trim($_POST['employee_id'] ?? '');

            if (empty($driver_name) || empty($employee_id)) {
                throw new Exception("Naam en Personeelsnummer zijn verplicht.");
            }

            // Zoek of chauffeur al bestaat op basis van personeelsnummer
            $stmt = $pdo->prepare("SELECT id FROM drivers WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            $driverRow = $stmt->fetch();

            if ($driverRow) {
                // Bestaat al -> Update naam en gebruik ID
                $driver_id = $driverRow['id'];
                $pdo->prepare("UPDATE drivers SET name = ? WHERE id = ?")->execute([$driver_name, $driver_id]);
            } else {
                // Bestaat niet -> Nieuwe aanmaken
                $stmt = $pdo->prepare("INSERT INTO drivers (name, employee_id) VALUES (?, ?)");
                $stmt->execute([$driver_name, $employee_id]);
                $driver_id = $pdo->lastInsertId();
            }
        }

        // STAP B: Formulier Opslaan
        $new_status = (isset($_POST['action']) && $_POST['action'] === 'complete') ? 'completed' : 'open';

        // Alle overige velden ophalen
        $form_date       = $_POST['form_date'] ?? date('Y-m-d');
        $start_date      = $_POST['start_date'] ?? date('Y-m-d');
        $review_moment   = $_POST['review_moment'] ?? '';
        $agency          = $_POST['agency'] ?? '';
        $routes_count    = $_POST['routes_count'] ?? 0;
        
        // Scores formatteren
        $otd_score       = $_POST['otd_score'] ?? '';
        $ftr_score       = $_POST['ftr_score'] ?? '';
        $otd_score = str_replace('%', '', $otd_score) . '%';
        $ftr_score = str_replace('%', '', $ftr_score) . '%';
        if($otd_score === '%') $otd_score = ''; 
        if($ftr_score === '%') $ftr_score = ''; 

        $errors_text     = $_POST['errors_text'] ?? '';
        $late_text       = $_POST['late_text'] ?? '';
        $driving_behavior= $_POST['driving_behavior'] ?? '';
        $warnings        = $_POST['warnings'] ?? '';
        $kw_score        = $_POST['kw_score'] ?? '';
        $skills_rating   = $_POST['skills_rating'] ?? 0;
        $proficiency_rating = $_POST['proficiency_rating'] ?? 0;
        $client_compliment  = $_POST['client_compliment'] ?? '';

        if ($is_new) {
            // INSERT
            $sql = "INSERT INTO feedback_forms (
                        driver_id, created_by_user_id, form_date, start_date, review_moment, agency,
                        routes_count, otd_score, ftr_score, errors_text, nokd_text, late_text,
                        driving_behavior, warnings, kw_score, skills_rating, proficiency_rating, client_compliment,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $driver_id, $_SESSION['user_id'], $form_date, $start_date, $review_moment, $agency,
                $routes_count, $otd_score, $ftr_score, $errors_text, $late_text,
                $driving_behavior, $warnings, $kw_score, $skills_rating, $proficiency_rating, $client_compliment,
                $new_status
            ]);
            
        } else {
            // UPDATE
            // Let op: driver_id wordt hier ook geupdate, mocht je van chauffeur gewisseld zijn
            $sql = "UPDATE feedback_forms SET 
                        driver_id = ?, form_date = ?, start_date = ?, review_moment = ?, agency = ?,
                        routes_count = ?, otd_score = ?, ftr_score = ?, errors_text = ?, late_text = ?,
                        driving_behavior = ?, warnings = ?, kw_score = ?, skills_rating = ?, proficiency_rating = ?, client_compliment = ?,
                        status = ?, updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $driver_id, $form_date, $start_date, $review_moment, $agency,
                $routes_count, $otd_score, $ftr_score, $errors_text, $late_text,
                $driving_behavior, $warnings, $kw_score, $skills_rating, $proficiency_rating, $client_compliment,
                $new_status, $form_id
            ]);
        }

        $pdo->commit();

        if ($new_status === 'completed') {
            header("Location: dashboard.php?msg=completed");
        } else {
            header("Location: dashboard.php?msg=saved");
        }
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
        /* Enterprise Style */
        :root { --brand-color: #0176d3; --brand-dark: #014486; --sidebar-bg: #1a2233; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }
        .sidebar { width: 240px; background: var(--sidebar-bg); color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .page-body { padding: 24px; max-width: 1000px; margin: 0 auto; width: 100%; padding-bottom: 100px; }
        
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 2px rgba(0,0,0,0.1); margin-bottom: 24px; overflow: hidden; }
        .form-section-title { background: #f8f9fa; padding: 12px 20px; font-weight: 700; border-bottom: 1px solid var(--border-color); margin: 0; font-size: 14px; }
        .card-body { padding: 20px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--text-secondary); }
        input, select, textarea { width: 100%; padding: 8px 12px; border: 1px solid #dddbda; border-radius: 4px; font-size: 14px; font-family: inherit; }
        input:focus, textarea:focus { border-color: var(--brand-color); outline: none; }
        textarea { resize: vertical; min-height: 80px; }

        /* Toggle Styles */
        .toggle-container { display: flex; gap: 15px; margin-bottom: 15px; }
        .radio-label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 14px; }
        .hidden { display: none; }

        .action-bar { position: fixed; bottom: 0; left: 240px; right: 0; background: white; border-top: 1px solid var(--border-color); padding: 15px 24px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn { padding: 9px 16px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; text-decoration: none; }
        .btn-cancel { background: white; border-color: var(--border-color); color: var(--text-main); }
        .btn-save { background: var(--brand-color); color: white; }
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
        <div class="page-body">
            <h1 style="margin-top: 0; margin-bottom: 20px;"><?php echo $is_new ? 'Nieuw feedbackgesprek' : 'Gesprek Bewerken'; ?></h1>

            <?php if ($error): ?>
                <div style="background: #fde8e8; color: #ea001e; padding: 10px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #fbd5d5;"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                
                <div class="card">
                    <h3 class="form-section-title">1. Chauffeur & Planning</h3>
                    <div class="card-body">
                        
                        <?php if ($is_new): ?>
                            <div class="form-group">
                                <label>Keuze Chauffeur:</label>
                                <div class="toggle-container">
                                    <label class="radio-label">
                                        <input type="radio" name="driver_mode" value="existing" checked onclick="toggleDriverMode('existing')">
                                        Bestaande chauffeur
                                    </label>
                                    <label class="radio-label">
                                        <input type="radio" name="driver_mode" value="new" onclick="toggleDriverMode('new')">
                                        Nieuwe invoeren
                                    </label>
                                </div>
                            </div>

                            <div id="block-existing" class="form-group">
                                <label>Selecteer Chauffeur *</label>
                                <select name="existing_driver_id">
                                    <option value="">-- Kies uit lijst --</option>
                                    <?php foreach ($drivers as $d): ?>
                                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['employee_id']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="block-new" class="form-grid hidden">
                                <div class="form-group">
                                    <label>Naam Chauffeur *</label>
                                    <input type="text" name="driver_name" placeholder="Bijv. Jan Jansen">
                                </div>
                                <div class="form-group">
                                    <label>Personeelsnummer *</label>
                                    <input type="text" name="employee_id" placeholder="Bijv. 12345">
                                </div>
                            </div>

                        <?php else: ?>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Naam Chauffeur *</label>
                                    <input type="text" name="driver_name" value="<?php echo htmlspecialchars($data['driver_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Personeelsnummer *</label>
                                    <input type="text" name="employee_id" value="<?php echo htmlspecialchars($data['employee_id']); ?>" required>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Uitzendbureau:</label>
                                <input type="text" name="agency" list="agency_options" value="<?php echo htmlspecialchars($data['agency']); ?>" placeholder="Typ of kies...">
                                <datalist id="agency_options">
                                    <option value="Young Capital">
                                    <option value="LevelWorks">
                                    <option value="NowJobs">
                                    <option value="Timing">
                                </datalist>
                            </div>
                            
                            <div class="form-group">
                                <label>Datum gesprek *</label>
                                <input type="date" name="form_date" value="<?php echo htmlspecialchars($data['form_date']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Startdatum chauffeur *</label>
                                <input type="date" name="start_date" value="<?php echo htmlspecialchars($data['start_date']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Beoordelingsmoment:</label>
                                <select name="review_moment">
                                    <option value="">-- Kies --</option>
                                    <option value="8 ritten" <?php if($data['review_moment'] == '8 weken' || $data['review_moment'] == '8 ritten') echo 'selected'; ?>>8 ritten</option>
                                    <option value="26 weken" <?php if($data['review_moment'] == '26 weken') echo 'selected'; ?>>26 weken</option>
                                    <option value="52 weken" <?php if($data['review_moment'] == '52 weken') echo 'selected'; ?>>52 weken</option>
                                </select>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3 class="form-section-title">2. Prestaties</h3>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>OTD score:</label>
                                <input type="text" name="otd_score" id="otd_score" value="<?php echo htmlspecialchars($data['otd_score']); ?>" placeholder="98%">
                            </div>
                            <div class="form-group">
                                <label>FTR score:</label>
                                <input type="text" name="ftr_score" id="ftr_score" value="<?php echo htmlspecialchars($data['ftr_score']); ?>" placeholder="99.5%">
                            </div>
                            <div class="form-group">
                                <label>KW verbruik E-vito:</label>
                                <input type="text" name="kw_score" value="<?php echo htmlspecialchars($data['kw_score']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Aantal routes:</label>
                                <input type="number" name="routes_count" value="<?php echo htmlspecialchars($data['routes_count']); ?>">
                            </div>
                        </div>
                        <div class="form-grid" style="margin-top: 15px;">
                            <div class="form-group">
                                <label>Fouten (errors):</label>
                                <textarea name="errors_text"><?php echo htmlspecialchars($data['errors_text']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Te laat:</label>
                                <textarea name="late_text"><?php echo htmlspecialchars($data['late_text']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3 class="form-section-title">3. Gedrag & Beoordeling</h3>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Rijgedrag & Communicatie</label>
                            <textarea name="driving_behavior"><?php echo htmlspecialchars($data['driving_behavior']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Waarschuwingen</label>
                            <textarea name="warnings" style="border-color: #fca5a5;"><?php echo htmlspecialchars($data['warnings']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Complimenten</label>
                            <textarea name="client_compliment" style="border-color: #86efac;"><?php echo htmlspecialchars($data['client_compliment']); ?></textarea>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label> Skills (1-5)</label>
                                <select name="skills_rating">
                                    <option value="0">-- Kies --</option>
                                    <?php for($i=1;$i<=5;$i++): ?>
                                        <option value="<?php echo $i; ?>" <?php if($data['skills_rating']==$i) echo 'selected'; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Proficiency Level:(1-14)</label>
                                <select name="proficiency_rating">
                                    <option value="0">-- Kies --</option>
                                    <?php for($i=1;$i<=14;$i++): ?>
                                        <option value="<?php echo $i; ?>" <?php if($data['proficiency_rating']==$i) echo 'selected'; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
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
        </div>
    </main>
    
    <script>
        // Toggle Logica
        function toggleDriverMode(mode) {
            const blockExisting = document.getElementById('block-existing');
            const blockNew = document.getElementById('block-new');

            if (mode === 'existing') {
                blockExisting.classList.remove('hidden');
                blockNew.classList.add('hidden');
            } else {
                blockExisting.classList.add('hidden');
                blockNew.classList.remove('hidden');
            }
        }

        function formatPercentage(input) {
            let val = input.value.trim();
            if (val === '') return;
            val = val.replace(/%/g, '');
            if (val !== '') {
                input.value = val + '%';
            }
        }
        const otdInput = document.getElementById('otd_score');
        const ftrInput = document.getElementById('ftr_score');

        if (otdInput) {
            otdInput.addEventListener('blur', function() { formatPercentage(this); });
        }
        if (ftrInput) {
            ftrInput.addEventListener('blur', function() { formatPercentage(this); });
        }
    </script>

</body>
</html>