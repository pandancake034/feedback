<?php
/**
 * DRIVER_HISTORY.PHP
 * Overzicht van alle gesprekken van één specifieke chauffeur.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php'; // Zorg dat helpers.php bestaat voor de status badges

// 1. Check ID
if (!isset($_GET['driver_id'])) {
    header("Location: dashboard.php");
    exit;
}
$driver_id = $_GET['driver_id'];

// 2. Haal Chauffeur Info op
try {
    $stmtDriver = $pdo->prepare("SELECT * FROM drivers WHERE id = ?");
    $stmtDriver->execute([$driver_id]);
    $driver = $stmtDriver->fetch(PDO::FETCH_ASSOC);

    if (!$driver) die("Chauffeur niet gevonden.");

    // 3. Haal ALLE gesprekken op van deze chauffeur (Historie)
    // Nieuwste eerst
    $stmtForms = $pdo->prepare("
        SELECT f.*, u.email as creator_email 
        FROM feedback_forms f
        LEFT JOIN users u ON f.created_by_user_id = u.id
        WHERE f.driver_id = ?
        ORDER BY f.form_date DESC
    ");
    $stmtForms->execute([$driver_id]);
    $forms = $stmtForms->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Dossier <?php echo htmlspecialchars($driver['name']); ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- CORE THEME (Gelijk aan Dashboard) --- */
        :root { --brand-color: #0176d3; --bg-body: #f3f2f2; --text-main: #181818; --text-light: #706e6b; --border-color: #dddbda; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* --- LAYOUT & SIDEBAR (Gecorrigeerd) --- */
        .sidebar { width: 240px; background: #1a2233; color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { height: 60px; padding: 0 20px; display: flex; align-items: center; background: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { max-height: 40px; }
        .nav-list { list-style: none; padding: 20px 0; margin: 0; }
        .nav-item a { display: flex; align-items: center; padding: 12px 20px; color: #b0b6c3; text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item a:hover, .nav-item a.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--brand-color); }
        .nav-item .material-icons-outlined { margin-right: 12px; }

        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        /* --- HEADER STYLING (Toegevoegd) --- */
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; position: sticky; top: 0; z-index: 10; flex-shrink: 0; }

        .content-body { padding: 24px; max-width: 1000px; margin: 0 auto; width: 100%; }

        /* Cards */
        .card { background: white; border: 1px solid var(--border-color); border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 24px; }
        .card-body { padding: 24px; }

        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .driver-title { margin: 0; font-size: 24px; color: var(--text-main); }
        .driver-sub { color: var(--text-light); font-size: 14px; margin-top: 4px; }

        /* Buttons */
        .btn { text-decoration: none; padding: 10px 16px; border-radius: 4px; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; border: 1px solid transparent; cursor: pointer; }
        .btn-primary { background: var(--brand-color); color: white; }
        .btn-primary:hover { background: #014486; }
        .btn-secondary { background: white; border-color: var(--border-color); color: var(--text-main); }
        .btn-secondary:hover { background: #f3f2f2; }

        /* Timeline Styles */
        .timeline { position: relative; padding-left: 20px; }
        .timeline-item { position: relative; border-left: 2px solid #e0e0e0; padding-left: 24px; padding-bottom: 32px; }
        .timeline-item:last-child { border-left: 2px solid transparent; }
        
        .timeline-marker { 
            position: absolute; left: -9px; top: 0; width: 16px; height: 16px; 
            background: white; border: 3px solid var(--brand-color); border-radius: 50%; 
        }
        
        .timeline-content { 
            background: white; border: 1px solid var(--border-color); border-radius: 6px; padding: 16px; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: 0.2s;
        }
        .timeline-content:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.08); border-color: #ccc; }

        .item-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .item-title { font-weight: 700; font-size: 15px; color: var(--brand-color); }
        .item-date { font-size: 13px; color: #999; }
        
        .item-badges { display: flex; gap: 8px; margin-top: 8px; }
        .mini-badge { background: #f3f4f6; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; color: #374151; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="content-body">
            
            <div class="page-header">
                <div>
                    <h1 class="driver-title"><?php echo htmlspecialchars($driver['name']); ?></h1>
                    <div class="driver-sub">
                        Personeelsnummer: <?php echo htmlspecialchars($driver['employee_id']); ?> 
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <span class="material-icons-outlined">arrow_back</span> Terug
                    </a>
                    <a href="feedback_create.php?prefill_driver=<?php echo $driver['id']; ?>" class="btn btn-primary">
                        <span class="material-icons-outlined">add</span> Nieuw
                    </a>
                </div>
            </div>

            <?php if(empty($forms)): ?>
                <div class="card">
                    <div class="card-body" style="text-align: center; color: #999;">
                        <span class="material-icons-outlined" style="font-size: 48px; color: #ccc;">folder_open</span>
                        <p>Nog geen dossiers gevonden voor deze chauffeur.</p>
                        <a href="feedback_create.php?prefill_driver=<?php echo $driver['id']; ?>" style="color:var(--brand-color); font-weight:600;">Start het eerste gesprek</a>
                    </div>
                </div>
            <?php else: ?>
                
                <div class="timeline">
                    <?php foreach($forms as $form): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="item-header">
                                <span class="item-title">
                                    <?php echo htmlspecialchars($form['review_moment'] ?: 'Gesprek (Geen type)'); ?>
                                </span>
                                <span class="item-date">
                                    <?php echo date('d-m-Y', strtotime($form['form_date'])); ?>
                                </span>
                            </div>
                            
                            <div style="font-size: 13px; margin-bottom: 12px; color: var(--text-light);">
                                Status: <?php echo format_status_badge($form['status']); ?> • 
                                Gemaakt door: <?php echo htmlspecialchars($form['creator_email']); ?>
                            </div>

                            <div class="item-badges">
                                <?php if($form['otd_score']): ?><span class="mini-badge">OTD: <?php echo $form['otd_score']; ?></span><?php endif; ?>
                                <?php if($form['ftr_score']): ?><span class="mini-badge">FTR: <?php echo $form['ftr_score']; ?></span><?php endif; ?>
                                <?php if($form['skills_rating'] > 0): ?><span class="mini-badge">Skills: <?php echo $form['skills_rating']; ?>/5</span><?php endif; ?>
                            </div>

                            <div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                                <a href="feedback_view.php?id=<?php echo $form['id']; ?>" style="text-decoration:none; color:var(--brand-color); font-weight:600; font-size:13px; display:flex; align-items:center; gap:4px;">
                                    Bekijk volledig dossier <span class="material-icons-outlined" style="font-size:16px;">arrow_forward</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>
    </main>
</body>
</html>