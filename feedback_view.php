<?php
/**
 * FEEDBACK_VIEW.PHP
 * Leesweergave van een dossier + Afspraken/Notities systeem.
 * UPDATE: 'Te Laat' veld toegevoegd onder Prestaties.
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

    // B. Notities / Afspraken (QUERY AANGEPAST: voornaam en achternaam ophalen)
    $stmtNotes = $pdo->prepare("SELECT n.*, u.email, u.first_name, u.last_name 
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
        
        /* Header CSS */
        .top-header { 
            height: 60px; 
            background: white; 
            border-bottom: 1px solid var(--border-color); 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 0 24px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
            flex-shrink: 0;
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
        
        /* Scores Visuals */
        .score-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; background: #e0e7ff; color: #3730a3; font-weight: 700; font-size: 13px; }
        .score-badge.success { background: #d1fae5; color: #065f46; } 
        
        /* Timeline / Notes */
        .timeline { margin-top: 15px; }
        .timeline-item { padding-left: 15px; border-left: 2px solid #e0e0e0; padding-bottom: 20px; position: relative; }
        .timeline-item::before { content: ''; position: absolute; left: -6px; top: 0; width: 10px; height: 10px; background: var(--brand-color); border-radius: 50%; }
        .note-meta { font-size: 11px; color: var(--text-light); margin-bottom: 4px; }
        .note-content { font-size: 13px; background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px solid #eee; }

        /* Input Area */
        .note-input { width: 100%; border: 1px solid var(--border-color); border-radius: 4px; padding: 10px; font-family: inherit; font-size: 13px; resize: vertical; min-height: 80px; }
        .btn-add { background: var(--brand-color); color: white; border: none; padding: 8px 16px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; margin-top: 8px; float: right; }

        /* Header Actions & Buttons */
        .page-header-actions { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        
        /* Button Styles */
        .btn-action { text-decoration: none; border: 1px solid var(--border-color); background: white; color: var(--text-main); padding: 8px 16px; border-radius: 4px; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; cursor: pointer; }
        .btn-action:hover { background-color: #f3f2f2; }
        .btn-primary { background: white; color: var(--brand-color); border-color: var(--border-color); }
        .btn-primary:hover { background: #f0f8ff; border-color: var(--brand-color); }

        /* NIEUWE STIJL VOOR DE TITEL */
        .driver-title { margin: 0; font-size: 26px; font-weight: 300; color: var(--text-light); }
        .driver-name { font-weight: 700; color: var(--text-main); }

        /* PRINT LOGO STANDAARD VERBERGEN */
        .print-only-logo { display: none; }

        /* --- PRINT STYLES (PDF EXPORT) --- */
        @media print {
            /* Verberg elementen die niet op papier horen */
            .sidebar, .top-header, .btn-action, .btn-add, .note-input, .app-footer, .no-print {
                display: none !important;
            }

            /* LOGO ZICHTBAAR MAKEN BIJ PRINTEN */
            .print-only-logo {
                display: block !important;
                max-width: 180px; 
                margin-bottom: 20px;
            }

            /* Reset layout voor papier */
            body, .main-content, .content-body {
                display: block !important;
                height: auto !important;
                overflow: visible !important;
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .content-body { padding: 0 !important; }

            /* Zorg dat de kolommen onder elkaar komen */
            .col-left, .col-right {
                width: 100% !important;
                flex: none !important;
                margin-bottom: 20px;
            }

            /* Maak kaarten strakker voor print */
            .card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                break-inside: avoid; /* Voorkom dat kaarten middenin worden doorgeknipt */
                margin-bottom: 15px !important;
            }

            .card-header {
                background-color: #f0f0f0 !important;
                border-bottom: 2px solid #ccc !important;
                color: black !important;
            }
            
            /* Lettertypes iets verkleinen/optimaliseren */
            body { font-size: 12px; }
            .driver-title { font-size: 20px; color: black !important; }
            
            /* Notities achtergrond wit maken */
            .card-body[style*="background-color"] { background-color: white !important; }
            .note-content { background: white !important; border: 1px solid #ddd; }
        }
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
        
        <img src="https://i.imgur.com/qGySlgO.png" class="print-only-logo" alt="Logo">
        
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
                    
                    <div class="no-print" style="display:flex; gap:10px;">
                        <button onclick="window.print()" class="btn-action">
                            <span class="material-icons-outlined" style="font-size: 18px;">print</span> Export PDF
                        </button>
                        
                        <a href="feedback_form.php?id=<?php echo $form_id; ?>" class="btn-action btn-primary">
                            <span class="material-icons-outlined" style="font-size: 16px;">edit</span> Bewerken
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">1. Prestaties & Scores</div>
                    <div class="card-body">
                        <div class="detail-row">
                            <div class="label">OTD Score</div>
                            <div class="value">
                                <?php 
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
                                    $ftrVal = floatval($form['ftr_score']);
                                    $ftrClass = ($ftrVal > 96) ? 'score-badge success' : 'score-badge';
                                ?>
                                <span class="<?php echo $ftrClass; ?>"><?php echo htmlspecialchars($form['ftr_score'] ?: '-'); ?></span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="label">KW verbruik E-vito</div>
                            <div class="value"><?php echo htmlspecialchars($form['kw_score'] ?: '-'); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Aantal routes:</div>
                            <div class="value"><?php echo htmlspecialchars($form['routes_count']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Fouten (errors):</div>
                            <div class="value"><?php echo nl2br(htmlspecialchars($form['errors_text'] ?: 'Geen bijzonderheden')); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Te laat:/div>
                            <div class="value"><?php echo nl2br(htmlspecialchars($form['late_text'] ?: 'Geen bijzonderheden')); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">2. Gedrag & beoordeling:</div>
                    <div class="card-body">
                        <div class="detail-row">
                            <div class="label">Rijgedrag:</div>
                            <div class="value"><?php echo nl2br(htmlspecialchars($form['driving_behavior'] ?: '-')); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Waarschuwingen:</div>
                            <div class="value" style="color: #c53030;"><?php echo nl2br(htmlspecialchars($form['warnings'] ?: 'Geen')); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Complimenten:</div>
                            <div class="value" style="color: #047857;"><?php echo nl2br(htmlspecialchars($form['client_compliment'] ?: '-')); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="label">Beoordeling</div>
                            <div class="value">
                                Skills: <strong><?php echo $form['skills_rating']; ?>/5</strong> • 
                                Proficiency level: <strong><?php echo $form['proficiency_rating']; ?>/5</strong>
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
                        
                        <form method="POST" style="margin-bottom: 20px;" class="no-print">
                            <input type="hidden" name="driver_id" value="<?php echo $form['driver_id']; ?>">
                            <textarea name="note_content" class="note-input" placeholder="Nieuwe afspraak of notitie toevoegen..." required></textarea>
                            <button type="submit" class="btn-add">Toevoegen</button>
                            <div style="clear:both;"></div>
                        </form>

                        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;" class="no-print">

                        <div class="timeline">
                            <?php if(empty($notes)): ?>
                                <div style="font-size: 13px; color: #999; text-align: center;">Nog geen afspraken genoteerd.</div>
                            <?php else: ?>
                                <?php foreach($notes as $note): ?>
                                <div class="timeline-item">
                                    <div class="note-meta">
                                        <strong>
                                            <?php 
                                                // Als voornaam en achternaam bestaan, gebruik die. Anders fallback naar email.
                                                $authorName = (!empty($note['first_name']) || !empty($note['last_name'])) 
                                                    ? trim($note['first_name'] . ' ' . $note['last_name']) 
                                                    : $note['email'];
                                                echo htmlspecialchars($authorName); 
                                            ?>
                                        </strong> • 
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