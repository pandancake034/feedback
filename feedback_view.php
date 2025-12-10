<?php
/**
 * FEEDBACK_VIEW.PHP
 * Leesweergave van een dossier + Afspraken/Notities systeem.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// Check login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$form_id = $_GET['id'] ?? null;
if (!$form_id) { header("Location: dashboard.php"); exit; }

// --- 1. NOTITIE TOEVOEGEN (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['note_content'])) {
    try {
        $driver_id = $_POST['driver_id'];
        $stmt = $pdo->prepare("INSERT INTO notes (driver_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$driver_id, $_SESSION['user_id'], $_POST['note_content']]);
        header("Location: feedback_view.php?id=" . $form_id . "&msg=note_added");
        exit;
    } catch (PDOException $e) {
        $error = "Kon notitie niet opslaan.";
    }
}

// --- 2. DATA OPHALEN ---
try {
    // A. Formulier Data
    $stmt = $pdo->prepare("SELECT f.*, d.name as driver_name, d.employee_id, u.email as creator_email 
                           FROM feedback_forms f 
                           JOIN drivers d ON f.driver_id = d.id 
                           JOIN users u ON f.created_by_user_id = u.id
                           WHERE f.id = ?");
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$form) die("Dossier niet gevonden.");

    // B. Notities / Afspraken
    $stmtNotes = $pdo->prepare("SELECT n.*, u.email as author 
                                FROM notes n 
                                JOIN users u ON n.user_id = u.id 
                                WHERE n.driver_id = ? 
                                ORDER BY n.note_date DESC");
    $stmtNotes->execute([$form['driver_id']]);
    $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

// Variabele voor de header
$page_title = "Dossier Inzien";
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
<title><?php echo APP_TITLE; ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- ENTERPRISE VIEW STYLE --- */
        :root { --brand-color: #0176d3; --bg-body: #f3f2f2; --text-main: #181818; --text-light: #706e6b; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* Layout Main */
        .sidebar { width: 240px; background: #1a2233; color: white; flex-shrink: 0; display: flex; flex-direction: column; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        /* HEADER CSS (DIE MISTE) */
        .top-header { 
            height: 60px; 
            background: white; 
            border-bottom: 1px solid var(--border-color); 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 0 24px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
            flex-shrink: 0; /* Belangrijk: zorgt dat header niet verdwijnt */
        }

        /* Content Wrapper voor de 2 kolommen */
        .content-body { padding: 24px; display: flex; gap: 24px; flex-grow: 1; }
        
        /* Kolommen */
        .col-left { flex: 2; } /* Formulier info */
        .col-right { flex: 1; min-width: 300px; } /* Notities */

        /* Cards */
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 2px rgba(0,0,0,0.1); margin-bottom: 24px; }
        .card-header { padding: 12px 16px; background: #f8f9fa; border-bottom: 1px solid var(--border-color); font-weight: 700; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 20px; }

        /* Detail Fields */
        .detail-row { display: flex; border-bottom: 1px solid #eee; padding: 12px 0; }
        .detail-row:last-child { border-bottom: none; }
        .label { width: 140px; color: var(--text-light); font-size: 13px; font-weight: 600; flex-shrink: 0; }
        .value { font-size: 14px; color: var(--text-main); line-height: 1.4; }
        
        /* Scores Visuals - UPDATE: Success variant toegevoegd */
        .score-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; background: #e0e7ff; color: #3730a3; font-weight: 700; font-size: 13px; }
        .score-badge.success { background: #d1fae5; color: #065f46; } /* Groen bij > 96 */
        
        /* Timeline / Notes */
        .timeline { margin-top: 15px; }
        .timeline-item { padding-left: 15px; border-left: 2px solid #e0e0e0; padding-bottom: 20px; position: relative; }
        .timeline-item::before { content: ''; position: absolute; left: -6px; top: 0; width: 10px; height: 10px; background: var(--brand-color); border-radius: 50%; }
        .note-meta { font-size: 11px; color: var(--text-light); margin-bottom: 4px; }
        .note-content { font-size: 13px; background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px solid #eee; }

        /* Input Area */
        .note-input { width: 100%; border: 1px solid var(--border-color); border-radius: 4px; padding: 10px; font-family: inherit; font-size: 13px; resize: vertical; min-height: 80px; }
        .btn-add { background: var(--brand-color); color: white; border: none; padding: 8px 16px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; margin-top: 8px; float: right; }

        /* Header Actions */
        .page-header-actions { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .btn-edit { text-decoration: none; border: 1px solid var(--border-color); background: white; color: var(--brand-color); padding: 8px 16px; border-radius: 4px; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-edit:hover { background-color: #f3f2f2; }

        /* NIEUWE STIJL VOOR DE TITEL */
        .driver-title { margin: 0; font-size: 26px; font-weight: 300; color: var(--text-light); }
        .driver-name { font-weight: 700; color: var(--text-main); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div style="padding: 20px; font-weight: 700; background: rgba(0,0,0,0.2);">LogistiekApp</div>
        <div style="padding: 20px;">
            <a href="dashboard.php" style="color: #b0b6c3; text-decoration: none; display: flex; align-items: center; gap: 10px;">
                <span class="material-icons-outlined">arrow_back</span> Terug
            </a>
        </div>
    </aside>

    <main class="main-content">
        
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="content-body">
            <div class="col-left">
                
                <div class="page-header-actions">
                    <div>
                        <h1 class="driver-title">
                            Driver Feedback: <span class="driver-name"><?php echo htmlspecialchars($form['driver_name']); ?></span>
                        </h1>
                        <span style="font-size: 13px; color: var(--text-light); display: block; margin-top: 4px;">
                            Gesprek van <?php echo date('d-m-Y', strtotime($form['form_date'])); ?> • ID: <?php echo htmlspecialchars($form['employee_id']); ?>
                        </span>
                    </div>
                    <a href="feedback_form.php?id=<?php echo $form_id; ?>" class="btn-edit">
                        <span class="material-icons-outlined" style="font-size: 16px;">edit</span> Bewerken
                    </a>
                </div>

                <div class="card">
                    <div class="card-header">1. Prestaties & Scores</div>
                    <div class="card-body">
                        <div class="detail-row">
                            <div class="label">OTD Score</div>
                            <div class="value">
                                <?php 
                                    // Bepaal kleur op basis van waarde > 96
                                    $otdVal = floatval($form['otd_score']);
                                    $otdClass = ($otdVal > 96) ? 'score-badge success' : 'score-badge';
                                ?>
                                <span class="<?php echo $otdClass; ?>"><?php echo htmlspecialchars($form['otd_score'] ?: '-'); ?></span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="label">FTR Score</div>
                            <div class="value">
                                <?php 
                                    // Bepaal kleur op basis van waarde > 96
                                    $ftrVal = floatval($form['ftr_score']);
                                    $ftrClass = ($ftrVal > 96) ? 'score-badge success' : 'score-badge';
                                ?>
                                <span class="<?php echo $ftrClass; ?>"><?php echo htmlspecialchars($form['ftr_score'] ?: '-'); ?></span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="label">KW Score</div>
                            <div class="value"><?php echo htmlspecialchars($form['kw_score'] ?: '-'); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Routes</div>
                            <div class="value"><?php echo htmlspecialchars($form['routes_count']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Fouten / Errors</div>
                            <div class="value"><?php echo nl2br(htmlspecialchars($form['errors_text'] ?: 'Geen bijzonderheden')); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">2. Gedrag & Beoordeling</div>
                    <div class="card-body">
                        <div class="detail-row">
                            <div class="label">Rijgedrag</div>
                            <div class="value"><?php echo nl2br(htmlspecialchars($form['driving_behavior'] ?: '-')); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Waarschuwingen</div>
                            <div class="value" style="color: #c53030;"><?php echo nl2br(htmlspecialchars($form['warnings'] ?: 'Geen')); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Complimenten</div>
                            <div class="value" style="color: #047857;"><?php echo nl2br(htmlspecialchars($form['client_compliment'] ?: '-')); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Beoordeling</div>
                            <div class="value">
                                Vaardigheden: <strong><?php echo $form['skills_rating']; ?>/5</strong> • 
                                Vakbekwaamheid: <strong><?php echo $form['proficiency_rating']; ?>/5</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-right">
                <div class="card">
                    <div class="card-header">
                        <span style="display:flex; align-items:center; gap:8px;">
                            <span class="material-icons-outlined">history_edu</span> Afspraken & Notities
                        </span>
                    </div>
                    <div class="card-body" style="background-color: #fcfcfc;">
                        
                        <form method="POST" style="margin-bottom: 20px;">
                            <input type="hidden" name="driver_id" value="<?php echo $form['driver_id']; ?>">
                            <textarea name="note_content" class="note-input" placeholder="Nieuwe afspraak of notitie toevoegen..." required></textarea>
                            <button type="submit" class="btn-add">Toevoegen</button>
                            <div style="clear:both;"></div>
                        </form>

                        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

                        <div class="timeline">
                            <?php if(empty($notes)): ?>
                                <div style="font-size: 13px; color: #999; text-align: center;">Nog geen afspraken genoteerd.</div>
                            <?php else: ?>
                                <?php foreach($notes as $note): ?>
                                <div class="timeline-item">
                                    <div class="note-meta">
                                        <strong><?php echo htmlspecialchars($note['author']); ?></strong> • 
                                        <?php echo date('d-m-Y H:i', strtotime($note['note_date'])); ?>
                                    </div>
                                    <div class="note-content">
                                        <?php echo nl2br(htmlspecialchars($note['content'])); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/includes/footer.php'; ?>

    </main>

</body>
</html>