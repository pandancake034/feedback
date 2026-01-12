<?php
/**
 * FEEDBACK_VIEW.PHP
 * Update: Ondersteuning voor 'Algemene Beoordeling' weergave.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

// Check login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$form_id = $_GET['id'] ?? null;
if (!$form_id) { header("Location: dashboard.php"); exit; }

// --- 1. ACTIES VERWERKEN (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // BEVEILIGING: CSRF Check
    verify_csrf();

    // A. NIEUWE NOTITIE
    if (isset($_POST['action']) && $_POST['action'] === 'add_note' && !empty($_POST['note_content'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notes (driver_id, user_id, content, note_date) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$_POST['driver_id'], $_SESSION['user_id'], $_POST['note_content']]);
            header("Location: feedback_view.php?id=" . $form_id . "&msg=saved"); exit;
        } catch (PDOException $e) { $error = "Kon notitie niet opslaan."; }
    }

    // B. NOTITIE BEWERKEN
    if (isset($_POST['action']) && $_POST['action'] === 'edit_note') {
        try {
            // Check eigenaar
            $check = $pdo->prepare("SELECT user_id FROM notes WHERE id = ?");
            $check->execute([$_POST['note_id']]);
            $noteOwner = $check->fetchColumn();

            if ($noteOwner == $_SESSION['user_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
                $stmt = $pdo->prepare("UPDATE notes SET content = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$_POST['note_content'], $_POST['note_id']]);
                header("Location: feedback_view.php?id=" . $form_id . "&msg=updated"); exit;
            }
        } catch (PDOException $e) { $error = "Kon notitie niet wijzigen."; }
    }

    // C. NOTITIE VERWIJDEREN
    if (isset($_POST['action']) && $_POST['action'] === 'delete_note') {
        try {
            $check = $pdo->prepare("SELECT user_id FROM notes WHERE id = ?");
            $check->execute([$_POST['note_id']]);
            $noteOwner = $check->fetchColumn();

            if ($noteOwner == $_SESSION['user_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
                $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
                $stmt->execute([$_POST['note_id']]);
                header("Location: feedback_view.php?id=" . $form_id . "&msg=deleted"); exit;
            }
        } catch (PDOException $e) { $error = "Kon notitie niet verwijderen."; }
    }
}

// --- 2. DATA OPHALEN ---
try {
    // Haal formulier op
    $stmt = $pdo->prepare("SELECT f.*, d.name as driver_name, d.employee_id, u.email as creator_email 
                           FROM feedback_forms f 
                           JOIN drivers d ON f.driver_id = d.id 
                           LEFT JOIN users u ON f.created_by_user_id = u.id
                           WHERE f.id = ?");
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$form) die("Dossier niet gevonden.");

    // Check type gesprek
    $isGeneral = ($form['review_moment'] === 'Algemene beoordeling');

    // Haal notities op
    $stmtNotes = $pdo->prepare("SELECT n.*, u.id as user_id, u.email, u.first_name, u.last_name 
                                FROM notes n 
                                LEFT JOIN users u ON n.user_id = u.id 
                                WHERE n.driver_id = ? 
                                ORDER BY n.note_date DESC"); 
    $stmtNotes->execute([$form['driver_id']]);
    $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

    // Als er een gekoppeld vorig gesprek is, haal datum op voor de link
    $linkedFormDate = '';
    if (!empty($form['linked_form_id'])) {
        $stmtL = $pdo->prepare("SELECT form_date, review_moment FROM feedback_forms WHERE id = ?");
        $stmtL->execute([$form['linked_form_id']]);
        $lf = $stmtL->fetch();
        if($lf) $linkedFormDate = date('d-m-Y', strtotime($lf['form_date'])) . ' (' . $lf['review_moment'] . ')';
    }

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

function getInitials($name) {
    $parts = explode(' ', $name);
    $initials = '';
    foreach($parts as $part) { if(strlen($part)>0) $initials .= strtoupper(substr($part, 0, 1)); }
    return substr($initials, 0, 2);
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
        :root { --brand-color: #0176d3; --brand-dark: #014486; --bg-body: #f3f2f2; --text-main: #181818; --text-light: #706e6b; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* Layout */
        .sidebar { width: 240px; background: #1a2233; color: white; flex-shrink: 0; display: flex; flex-direction: column; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; flex-shrink: 0; }
        .content-body { padding: 24px; display: flex; gap: 24px; flex-grow: 1; max-width: 1600px; margin: 0 auto; width: 100%; }
        
        .col-left { flex: 2; display: flex; flex-direction: column; gap: 24px; min-width: 0; }
        .col-right { flex: 1; min-width: 350px; }

        .card { background: white; border: 1px solid var(--border-color); border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden; }
        .card-header { padding: 16px 20px; background: #fff; border-bottom: 1px solid #f0f0f0; font-weight: 700; font-size: 15px; display: flex; justify-content: space-between; align-items: center; color: var(--brand-color); }
        .card-body { padding: 20px; }

        /* HEADER & AVATAR */
        .profile-header-container { display: flex; justify-content: space-between; align-items: flex-start; }
        .profile-info { display: flex; gap: 16px; align-items: center; }
        .profile-avatar { 
            width: 64px; height: 64px; background: linear-gradient(135deg, var(--brand-color), #014486);
            color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            font-size: 24px; font-weight: 700; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: 3px solid white;
        }
        .profile-link { color: var(--text-main); text-decoration: none; transition: color 0.2s; }
        .profile-link:hover { color: var(--brand-color); text-decoration: underline; }

        /* DETAILS */
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .detail-item { margin-bottom: 8px; }
        .label { font-size: 12px; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; margin-bottom: 4px; }
        .value { font-size: 15px; color: var(--text-main); font-weight: 500; line-height: 1.5; white-space: pre-wrap; }
        
        /* KPI Visuals */
        .progress-wrapper { margin-top: 5px; }
        .progress-bg { height: 6px; background: #eee; border-radius: 3px; overflow: hidden; width: 100%; }
        .progress-fill { height: 100%; background: var(--brand-color); border-radius: 3px; transition: width 0.5s ease; }
        .progress-fill.success { background: #10b981; } .progress-fill.warning { background: #f59e0b; } 
        .progress-text { font-size: 12px; font-weight: 700; float: right; margin-top: -18px; color: var(--text-main); }

        /* STEPPER */
        .stepper { display: flex; align-items: center; margin-bottom: 24px; background: white; padding: 20px; border-radius: 6px; border: 1px solid var(--border-color); }
        .step { flex: 1; position: relative; text-align: center; font-size: 13px; color: var(--text-light); font-weight: 600; }
        .step::after { content: ''; position: absolute; top: 14px; left: 50%; width: 100%; height: 2px; background: #eee; z-index: 1; }
        .step:last-child::after { display: none; }
        .step-icon { width: 30px; height: 30px; background: #eee; color: #999; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; position: relative; z-index: 2; font-size: 16px; transition: 0.3s; }
        .step.active .step-icon { background: var(--brand-color); color: white; box-shadow: 0 0 0 4px rgba(1, 118, 211, 0.2); }
        .step.completed .step-icon { background: #10b981; color: white; }
        .step.completed::after { background: #10b981; }

        /* CHAT STYLES */
        .chat-container { display: flex; flex-direction: column; gap: 15px; padding-bottom: 20px; }
        .chat-row { display: flex; align-items: flex-end; gap: 10px; width: 100%; }
        .chat-row.me { justify-content: flex-end; }
        .chat-row.other { justify-content: flex-start; }
        .chat-avatar { width: 32px; height: 32px; flex-shrink: 0; background: #e0e7ff; color: var(--brand-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; border: 1px solid white; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .chat-row.me .chat-avatar { background: var(--brand-dark); color: white; }
        .chat-bubble { max-width: 80%; padding: 10px 14px; position: relative; font-size: 13px; line-height: 1.4; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .chat-row.me .chat-bubble { background-color: var(--brand-color); color: white; border-radius: 12px 12px 0 12px; }
        .chat-row.other .chat-bubble { background-color: #f3f4f6; color: var(--text-main); border: 1px solid #eee; border-radius: 12px 12px 12px 0; }
        .chat-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; font-size: 11px; gap: 10px; }
        .chat-row.me .chat-meta { color: rgba(255,255,255, 0.8); }
        .chat-row.other .chat-meta { color: #999; }
        .chat-name { font-weight: 700; }
        .chat-actions { display: inline-flex; gap: 5px; opacity: 0; transition: 0.2s; margin-left: 8px; }
        .chat-bubble:hover .chat-actions { opacity: 1; }
        .chat-action-icon { cursor: pointer; font-size: 14px; }
        .chat-row.me .chat-action-icon { color: rgba(255,255,255, 0.9); }
        .chat-row.other .chat-action-icon { color: #999; }

        /* Buttons & Forms */
        .note-input { width: 100%; border: 1px solid var(--border-color); border-radius: 4px; padding: 12px; font-family: inherit; font-size: 13px; resize: vertical; min-height: 80px; transition: 0.2s; }
        .note-input:focus { border-color: var(--brand-color); outline: none; box-shadow: 0 0 0 2px rgba(1, 118, 211, 0.1); }
        .btn-add { background: var(--brand-color); color: white; border: none; padding: 8px 16px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; margin-top: 10px; float: right; }
        .header-actions { display: flex; gap: 10px; }
        .btn-action { text-decoration: none; background: white; border: 1px solid var(--border-color); color: var(--text-main); padding: 8px 16px; border-radius: 4px; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-action:hover { background: #f3f2f2; border-color: #ccc; }
        .btn-primary { background: var(--brand-color); color: white; border: none; }
        .btn-primary:hover { background: #014486; }
        
        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal { background: white; width: 100%; max-width: 500px; border-radius: 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-weight: 700; background: #f8f9fa; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 16px 20px; background: #f8f9fa; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }

        /* Print Style */
        .print-only-logo { display: none; }
        @media print {
            .sidebar, .top-header, .btn-action, .btn-add, .note-input, .no-print, .chat-actions { display: none !important; }
            body, .main-content, .content-body { display: block !important; height: auto !important; background: white !important; padding: 0 !important; margin: 0 !important; }
            .col-left, .col-right { width: 100% !important; flex: none !important; margin-bottom: 20px; }
            .card { box-shadow: none !important; border: 1px solid #ccc !important; break-inside: avoid; }
            .print-only-logo { display: block !important; max-width: 150px; margin-bottom: 20px; }
            .profile-avatar { display: none; } 
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div style="padding: 20px; font-weight: 700; background: rgba(0,0,0,0.2);">FeedbackFlow</div>
        <div style="padding: 20px;">
            <a href="dashboard.php" style="color: #b0b6c3; text-decoration: none; display: flex; align-items: center; gap: 10px;">
                <span class="material-icons-outlined">arrow_back</span> Terug
            </a>
        </div>
    </aside>

    <main class="main-content">
        
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div style="padding: 0 24px;">
            <img src="https://i.imgur.com/qGySlgO.png" class="print-only-logo" alt="Logo">
        </div>

        <div class="content-body">
            <div class="col-left">
                
                <div class="profile-header-container">
                    <div class="profile-info">
                        <div class="profile-avatar no-print"><?php echo getInitials($form['driver_name']); ?></div>
                        <div>
                            <h1 style="margin: 0; font-size: 24px;">
                                <a href="driver_history.php?driver_id=<?php echo $form['driver_id']; ?>" class="profile-link">
                                    <?php echo htmlspecialchars($form['driver_name']); ?>
                                </a>
                            </h1>
                            <span style="font-size: 13px; color: var(--text-light); display: block; margin-top: 4px;">
                                ID: <?php echo htmlspecialchars($form['employee_id']); ?> • 
                                Gesprek: <?php echo date('d-m-Y', strtotime($form['form_date'])); ?>
                                <?php if($isGeneral): ?>
                                    <span style="background:#e0e7ff; color:#014486; padding:1px 6px; border-radius:4px; font-size:11px; margin-left:6px; font-weight:700;">ALGEMEEN</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="header-actions no-print">
                        <button onclick="window.print()" class="btn-action">
                            <span class="material-icons-outlined" style="font-size: 18px;">print</span> PDF
                        </button>
                        <a href="feedback_form.php?id=<?php echo $form_id; ?>" class="btn-action btn-primary">
                            <span class="material-icons-outlined" style="font-size: 18px;">edit</span> Bewerken
                        </a>
                    </div>
                </div>

                <div class="stepper no-print">
                    <?php 
                        $isCompleted = ($form['status'] === 'completed');
                        $openClass = $isCompleted ? 'completed' : 'active';
                        $doneClass = $isCompleted ? 'active' : '';
                    ?>
                    <div class="step <?php echo $openClass; ?>">
                        <div class="step-icon"><span class="material-icons-outlined" style="font-size:16px;">edit_note</span></div>
                        Concept / Open
                    </div>
                    <div class="step <?php echo $openClass; ?>">
                        <div class="step-icon"><span class="material-icons-outlined" style="font-size:16px;">rate_review</span></div>
                        Bespreking
                    </div>
                    <div class="step <?php echo $doneClass; ?>">
                        <div class="step-icon"><span class="material-icons-outlined" style="font-size:16px;">check_circle</span></div>
                        Afgerond
                    </div>
                </div>

                <?php if ($isGeneral): ?>
                    <div class="card" style="border-left: 4px solid var(--brand-color);">
                        <div class="card-header">
                            <span><span class="material-icons-outlined" style="vertical-align:middle; margin-right:8px;">forum</span>Gespreksverslag</span>
                        </div>
                        <div class="card-body">
                            <div class="detail-grid" style="margin-bottom:20px;">
                                <div class="detail-item">
                                    <div class="label">Reden van gesprek</div>
                                    <div class="value" style="font-size:16px; font-weight:600;"><?php echo htmlspecialchars($form['conversation_reason'] ?: 'Geen reden opgegeven'); ?></div>
                                </div>
                                <?php if($linkedFormDate): ?>
                                <div class="detail-item">
                                    <div class="label">Referentie naar vorig gesprek</div>
                                    <div class="value">
                                        <a href="feedback_view.php?id=<?php echo $form['linked_form_id']; ?>" style="color:var(--brand-color); text-decoration:none; display:flex; align-items:center; gap:5px;">
                                            <span class="material-icons-outlined" style="font-size:16px;">link</span>
                                            <?php echo htmlspecialchars($linkedFormDate); ?>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <hr style="border:0; border-top:1px solid #eee; margin:15px 0;">

                            <div style="margin-bottom: 24px;">
                                <div class="label">Besproken punten & Vragen</div>
                                <div class="value" style="background:#f9f9f9; padding:15px; border-radius:4px; border:1px solid #eee; text-align: left;">
                                    <?php echo nl2br(htmlspecialchars($form['general_comments'] ?: 'Geen notities.')); ?>
                                </div>
                            </div>

                            <div style="margin-bottom: 24px;">
                                <div class="label">Gemaakte Afspraken</div>
                                <div class="value" style="background:#f0fdf4; color:#166534; padding:15px; border-radius:4px; border:1px solid #bbf7d0; text-align: left;">
                                    <span class="material-icons-outlined" style="font-size:16px; vertical-align:left; margin-right:5px;">handshake</span>
                                    <?php echo nl2br(htmlspecialchars($form['agreements'] ?: 'Geen afspraken vastgelegd.')); ?>
                                </div>
                            </div>

                            <?php if(!empty($form['misc_comments'])): ?>
                            <div>
                                <div class="label">Overige opmerkingen</div>
                                <div class="value" style="text-align: left;"><?php echo nl2br(htmlspecialchars($form['misc_comments'])); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <span><span class="material-icons-outlined" style="vertical-align:middle; margin-right:8px;">insights</span>Prestaties</span>
                        </div>
                        <div class="card-body">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="label">OTD Score</div>
                                    <?php $otdVal = floatval(str_replace('%', '', $form['otd_score'])); $otdColor = ($otdVal >= 96) ? 'success' : 'warning'; ?>
                                    <div class="progress-wrapper">
                                        <div class="progress-text"><?php echo htmlspecialchars($form['otd_score']); ?></div>
                                        <div class="progress-bg"><div class="progress-fill <?php echo $otdColor; ?>" style="width: <?php echo $otdVal; ?>%;"></div></div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="label">FTR Score</div>
                                    <?php $ftrVal = floatval(str_replace('%', '', $form['ftr_score'])); $ftrColor = ($ftrVal >= 96) ? 'success' : 'warning'; ?>
                                    <div class="progress-wrapper">
                                        <div class="progress-text"><?php echo htmlspecialchars($form['ftr_score']); ?></div>
                                        <div class="progress-bg"><div class="progress-fill <?php echo $ftrColor; ?>" style="width: <?php echo $ftrVal; ?>%;"></div></div>
                                    </div>
                                </div>
                                <div class="detail-item"><div class="label">KW Verbruik</div><div class="value" style="font-size: 18px;"><?php echo htmlspecialchars($form['kw_score'] ?: '-'); ?></div></div>
                                <div class="detail-item"><div class="label">Aantal Routes</div><div class="value" style="font-size: 18px;"><?php echo htmlspecialchars($form['routes_count']); ?></div></div>
                            </div>
                            <hr style="border:0; border-top:1px solid #eee; margin: 20px 0;">
                            <div class="detail-grid">
                                <div class="detail-item"><div class="label">Fouten (Errors)</div><div class="value"><?php echo nl2br(htmlspecialchars($form['errors_text'] ?: 'Geen')); ?></div></div>
                                <div class="detail-item"><div class="label">Te Laat</div><div class="value"><?php echo nl2br(htmlspecialchars($form['late_text'] ?: 'Geen')); ?></div></div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <span><span class="material-icons-outlined" style="vertical-align:middle; margin-right:8px;">psychology</span>Gedrag & Soft Skills</span>
                        </div>
                        <div class="card-body">
                            <div style="margin-bottom: 20px;">
                                <div class="label">Rijgedrag & Communicatie</div>
                                <div class="value" style="background: #f9fafb; padding: 10px; border-radius: 4px; border: 1px solid #eee;">
                                    <?php echo nl2br(htmlspecialchars($form['driving_behavior'] ?: 'Geen opmerkingen.')); ?>
                                </div>
                            </div>
                            <div class="detail-grid" style="margin-bottom: 20px;">
                                <div class="detail-item">
                                    <div class="label">Skills / Rollen</div>
                                    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:4px;">
                                        <?php 
                                        $skillsList = explode(',', $form['skills_rating'] ?? '');
                                        $hasSkills = false;
                                        foreach($skillsList as $skill):
                                            $skill = trim($skill);
                                            if(!empty($skill)): 
                                                $hasSkills = true;
                                        ?>
                                            <span style="background:#e0e7ff; color:#014486; border:1px solid #0176d3; padding:2px 8px; border-radius:12px; font-size:12px; font-weight:600;">
                                                <?php echo htmlspecialchars($skill); ?>
                                            </span>
                                        <?php 
                                            endif; 
                                        endforeach; 
                                        if(!$hasSkills) echo '<span style="color:#999; font-style:italic; font-size:13px;">Geen skills geregistreerd.</span>';
                                        ?>
                                    </div>
                                </div>
                                <div class="detail-item"><div class="label">Proficiency Level</div><div class="value">Niveau <strong><?php echo $form['proficiency_rating']; ?></strong> / 14</div></div>
                            </div>
                            <div class="detail-grid">
                                <div class="detail-item"><div class="label">Complimenten</div><div class="value" style="color: #065f46; background: #d1fae5; padding: 10px; border-radius: 4px; border: 1px solid #6ee7b7;"><?php echo nl2br(htmlspecialchars($form['client_compliment'] ?: 'Geen complimenten.')); ?></div></div>
                                <div class="detail-item"><div class="label">Waarschuwingen</div><div class="value" style="color: #c53030; background: #fff5f5; padding: 10px; border-radius: 4px; border: 1px solid #fed7d7;"><?php echo nl2br(htmlspecialchars($form['warnings'] ?: 'Geen waarschuwingen.')); ?></div></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-right">
                <div class="card">
                    <div class="card-header">
                        <span><span class="material-icons-outlined" style="vertical-align:middle; margin-right:8px;">forum</span>Notities & Chat</span>
                    </div>
                    <div class="card-body" style="background-color: #fcfcfc; max-height: 600px; overflow-y: auto;">
                        
                        <form method="POST" style="margin-bottom: 24px;" class="no-print">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="add_note">
                            <input type="hidden" name="driver_id" value="<?php echo $form['driver_id']; ?>">
                            <textarea name="note_content" class="note-input" placeholder="Schrijf een bericht..." required></textarea>
                            <button type="submit" class="btn-add">Versturen</button>
                            <div style="clear:both;"></div>
                        </form>

                        <div class="chat-container">
                            <?php if(empty($notes)): ?>
                                <div style="text-align: center; color: #999; padding: 20px; font-style: italic;">Nog geen berichten.</div>
                            <?php else: ?>
                                <?php foreach($notes as $note): 
                                    $displayName = (!empty($note['first_name'])) ? $note['first_name'] . ' ' . $note['last_name'] : $note['email'];
                                    $initials = getInitials($displayName);
                                    $isMe = ($note['user_id'] == $_SESSION['user_id']);
                                    $canEdit = ($isMe || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'));
                                    $rowClass = $isMe ? 'me' : 'other';
                                ?>
                                <div class="chat-row <?php echo $rowClass; ?>">
                                    <?php if(!$isMe): ?>
                                        <div class="chat-avatar" title="<?php echo htmlspecialchars($displayName); ?>">
                                            <?php echo $initials; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="chat-bubble">
                                        <div class="chat-meta">
                                            <span class="chat-name"><?php echo htmlspecialchars($isMe ? 'Ik' : $displayName); ?></span>
                                            <span><?php echo date('d-m-Y H:i', strtotime($note['note_date'])); ?></span>
                                            <?php if ($canEdit): ?>
                                            <span class="chat-actions">
                                                <span class="material-icons-outlined chat-action-icon" onclick="openEditNote(<?php echo $note['id']; ?>, '<?php echo addslashes(str_replace(["\r", "\n"], ['','\n'], $note['content'])); ?>')">edit</span>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Verwijderen?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete_note">
                                                    <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                                    <button type="submit" style="background:none; border:none; padding:0; cursor:pointer;">
                                                        <span class="material-icons-outlined chat-action-icon delete">delete</span>
                                                    </button>
                                                </form>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <div><?php echo nl2br(htmlspecialchars($note['content'])); ?></div>
                                    </div>
                                    <?php if($isMe): ?>
                                        <div class="chat-avatar"><?php echo $initials; ?></div>
                                    <?php endif; ?>
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

    <div id="editNoteModal" class="modal-overlay">
        <div class="modal">
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_note">
                <input type="hidden" name="note_id" id="edit_note_id">
                
                <div class="modal-header">
                    <span>Bericht Bewerken</span>
                    <button type="button" onclick="closeEditNote()" style="background:none; border:none; cursor:pointer; font-size:20px;">&times;</button>
                </div>
                <div class="modal-body">
                    <textarea name="note_content" id="edit_note_content" class="note-input" style="min-height:120px;" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action" onclick="closeEditNote()">Annuleren</button>
                    <button type="submit" class="btn-action btn-primary">Opslaan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const editModal = document.getElementById('editNoteModal');
        const editInputId = document.getElementById('edit_note_id');
        const editInputContent = document.getElementById('edit_note_content');

        function openEditNote(id, content) {
            editInputId.value = id;
            editInputContent.value = content; 
            editModal.style.display = 'flex';
        }
        function closeEditNote() { editModal.style.display = 'none'; }
        editModal.addEventListener('click', (e) => { if (e.target === editModal) closeEditNote(); });
    </script>

</body>
</html>