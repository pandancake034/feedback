<?php
/**
 * ADMIN/INDEX.PHP
 * Versie 2.0: Tabbladen structuur & Dossierbeheer
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

// 1. BEVEILIGING & ROL CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
// Alleen admins mogen hier komen
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../dashboard.php");
    exit;
}

$msg = "";
$error = "";
$activeTab = $_GET['tab'] ?? 'users'; // Standaard tabblad

// 2. LOGICA: CRUD ACTIES (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. GEBRUIKER TOEVOEGEN
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        try {
            if (empty($_POST['email']) || empty($_POST['password'])) throw new Exception("Vul minimaal e-mail en wachtwoord in.");
            
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = $_POST['role'] ?? 'user';
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            
            $stmt->execute([$firstName, $lastName, $_POST['email'], $hash, $role]);
            header("Location: index.php?msg=created&tab=users"); exit;
        } catch (Exception $e) { $error = $e->getMessage(); $activeTab = 'users'; }
    }

    // B. GEBRUIKER BEWERKEN
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        try {
            $id = $_POST['user_id'];
            $email = $_POST['email'];
            $role = $_POST['role'];
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            
            if (!empty($_POST['password'])) {
                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $email, $role, $hash, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $email, $role, $id]);
            }
            header("Location: index.php?msg=updated&tab=users"); exit;
        } catch (Exception $e) { $error = $e->getMessage(); $activeTab = 'users'; }
    }

    // C. GEBRUIKER VERWIJDEREN
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        try {
            if ($_POST['user_id'] == $_SESSION['user_id']) throw new Exception("Je kunt je eigen account niet verwijderen.");
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_POST['user_id']]);
            header("Location: index.php?msg=deleted&tab=users"); exit;
        } catch (Exception $e) { $error = $e->getMessage(); $activeTab = 'users'; }
    }

    // D. DOSSIER VERWIJDEREN (NIEUW)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_form') {
        try {
            $stmt = $pdo->prepare("DELETE FROM feedback_forms WHERE id = ?");
            $stmt->execute([$_POST['form_id']]);
            header("Location: index.php?msg=form_deleted&tab=dossiers"); exit;
        } catch (Exception $e) { $error = $e->getMessage(); $activeTab = 'dossiers'; }
    }
}

// 3. DATA OPHALEN

// A. Statistieken
$statsQuery = "SELECT u.id, u.first_name, u.last_name, u.email, COUNT(f.id) as assigned_count 
               FROM users u 
               LEFT JOIN feedback_forms f ON u.id = f.assigned_to_user_id 
               GROUP BY u.id 
               ORDER BY assigned_count DESC";
$userStats = $pdo->query($statsQuery)->fetchAll();

// B. Gebruikers
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// C. Alle Dossiers (voor het dossier tabblad)
$forms = $pdo->query("SELECT f.id, f.form_date, f.status, d.name as driver_name, d.employee_id, u.email as creator_email 
                      FROM feedback_forms f 
                      JOIN drivers d ON f.driver_id = d.id 
                      LEFT JOIN users u ON f.created_by_user_id = u.id 
                      ORDER BY f.created_at DESC")->fetchAll();

// Meldingen
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'created') $msg = "Gebruiker aangemaakt.";
    if ($_GET['msg'] == 'updated') $msg = "Gebruiker gewijzigd.";
    if ($_GET['msg'] == 'deleted') $msg = "Gebruiker verwijderd.";
    if ($_GET['msg'] == 'form_deleted') $msg = "Dossier definitief verwijderd.";
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Admin Beheer - <?php echo defined('APP_TITLE') ? APP_TITLE : 'LogistiekApp'; ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- ENTERPRISE THEME --- */
        :root {
            --brand-color: #0176d3; --brand-dark: #014486;
            --sidebar-bg: #1a2233; --bg-body: #f3f2f2;
            --text-main: #181818; --text-secondary: #706e6b;
            --border-color: #dddbda; --card-shadow: 0 2px 2px 0 rgba(0,0,0,0.1);
            --success-bg: #d1fae5; --success-text: #065f46;
            --danger-bg: #fde8e8; --danger-text: #c53030;
        }

        body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; background-color: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; }

        /* Sidebar & Layout */
        .sidebar { width: 240px; background-color: var(--sidebar-bg); color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .sidebar-header { height: 60px; display: flex; align-items: center; padding: 0 20px; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); }
        .sidebar-logo { max-height: 40px; width: auto; }
        .nav-list { list-style: none; padding: 20px 0; margin: 0; flex-grow: 1; }
        .nav-item a { display: flex; align-items: center; padding: 12px 20px; color: #b0b6c3; text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item a:hover, .nav-item a.active { background-color: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--brand-color); }
        .nav-item .material-icons-outlined { margin-right: 12px; font-size: 20px; }

        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-header { height: 60px; background: white; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: flex-end; padding: 0 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .page-body { padding: 24px; max-width: 1400px; margin: 0 auto; width: 100%; flex-grow: 1; }

        /* Tabs Styles */
        .tab-nav { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 24px; background: white; border-radius: 4px 4px 0 0; }
        .tab-btn { padding: 16px 24px; border: none; background: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 14px; font-weight: 600; color: var(--text-secondary); transition: 0.2s; }
        .tab-btn:hover { background: #f9f9f9; color: var(--brand-color); }
        .tab-btn.active { border-bottom-color: var(--brand-color); color: var(--brand-color); }
        
        .tab-content { display: none; animation: fadeIn 0.2s ease-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Cards & Grid */
        .grid-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { background: white; border: 1px solid var(--border-color); border-radius: 4px; box-shadow: var(--card-shadow); display: flex; flex-direction: column; }
        .card-body { padding: 16px; flex-grow: 1; }
        .card-header { padding: 12px 16px; border-bottom: 1px solid var(--border-color); background-color: #fcfcfc; display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 14px; }
        
        .stat-number { font-size: 28px; font-weight: 300; color: var(--brand-color); }
        .stat-label { font-size: 12px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size: 11px; background: #fafafa; }
        td { padding: 10px; border-bottom: 1px solid #eee; color: var(--text-main); vertical-align: middle; }
        
        /* Buttons & Badges */
        .btn { padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; border: none; }
        .btn-brand { background: var(--brand-color); color: white; }
        .btn-icon { background: none; border: none; cursor: pointer; color: var(--text-secondary); padding: 4px; }
        .btn-icon:hover { color: var(--brand-color); background: #f3f2f2; border-radius: 4px; }
        .btn-icon.delete:hover { color: var(--danger-text); }
        
        .badge { padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-admin { background: #e0e7ff; color: #3730a3; }
        .badge-user { background: #f3f4f6; color: #374151; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal { background: white; width: 100%; max-width: 500px; border-radius: 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-weight: 700; background: #f8f9fa; }
        .modal-body { padding: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: var(--text-secondary); }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 14px; }
        .modal-footer { padding: 16px 20px; background: #f8f9fa; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="https://i.imgur.com/qGySlgO.png" alt="LogistiekApp" class="sidebar-logo">
        </div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="../dashboard.php">
                    <span class="material-icons-outlined">dashboard</span> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="index.php" class="active">
                    <span class="material-icons-outlined">admin_panel_settings</span> Beheer & Admin
                </a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div style="font-size: 13px; font-weight: 600; display:flex; align-items:center; gap:8px;">
                <span class="material-icons-outlined">account_circle</span>
                <?php echo htmlspecialchars($_SESSION['email']); ?> (Admin)
                <a href="../logout.php" style="margin-left:15px; color:var(--text-secondary); text-decoration:none;"><span class="material-icons-outlined">logout</span></a>
            </div>
        </header>

        <div class="page-body">
            
            <div style="margin-bottom: 24px;">
                <h1 style="margin: 0; font-size: 24px;">Systeembeheer</h1>
                <div style="color: var(--text-secondary); font-size: 13px;">Beheer gebruikers, bekijk statistieken en schoon dossiers op.</div>
            </div>

            <?php if ($msg): ?>
                <div style="background:var(--success-bg); color:var(--success-text); padding:10px; border-radius:4px; margin-bottom:20px; font-size:14px; border:1px solid #a7f3d0;">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background:var(--danger-bg); color:var(--danger-text); padding:10px; border-radius:4px; margin-bottom:20px; font-size:14px; border:1px solid #fbd5d5;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="tab-nav">
                <button class="tab-btn <?php echo ($activeTab === 'users') ? 'active' : ''; ?>" onclick="openTab('users')">
                    <span class="material-icons-outlined" style="vertical-align:middle; font-size:18px; margin-right:6px;">people</span>
                    Gebruikers
                </button>
                <button class="tab-btn <?php echo ($activeTab === 'dossiers') ? 'active' : ''; ?>" onclick="openTab('dossiers')">
                    <span class="material-icons-outlined" style="vertical-align:middle; font-size:18px; margin-right:6px;">folder_delete</span>
                    Dossiers & Opruimen
                </button>
                <button class="tab-btn <?php echo ($activeTab === 'stats') ? 'active' : ''; ?>" onclick="openTab('stats')">
                    <span class="material-icons-outlined" style="vertical-align:middle; font-size:18px; margin-right:6px;">insights</span>
                    Statistieken
                </button>
            </div>

            <div id="tab-users" class="tab-content <?php echo ($activeTab === 'users') ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <span>Alle Gebruikers</span>
                        <button onclick="openModal('create')" class="btn btn-brand">
                            <span class="material-icons-outlined" style="font-size:16px;">add</span> Nieuw
                        </button>
                    </div>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Naam</th>
                                    <th>E-mailadres</th>
                                    <th>Rol</th>
                                    <th>Laatst Ingelogd</th>
                                    <th style="text-align: right;">Acties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($user['role'] === 'admin') ? 'badge-admin' : 'badge-user'; ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['last_login'] ? date('d-m-Y H:i', strtotime($user['last_login'])) : '-'; ?></td>
                                    <td style="text-align: right;">
                                        <button class="btn-icon" title="Bewerken" onclick='openModal("edit", <?php echo json_encode($user); ?>)'>
                                            <span class="material-icons-outlined" style="font-size:18px;">edit</span>
                                        </button>
                                        
                                        <?php if($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn-icon delete" title="Verwijderen">
                                                <span class="material-icons-outlined" style="font-size:18px;">delete</span>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="tab-dossiers" class="tab-content <?php echo ($activeTab === 'dossiers') ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        Alle Dossiers (Definitief Verwijderen)
                    </div>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Chauffeur</th>
                                    <th>Personeelsnr</th>
                                    <th>Status</th>
                                    <th>Gemaakt door</th>
                                    <th style="text-align: right;">Actie</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($forms as $form): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($form['form_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($form['driver_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($form['employee_id']); ?></td>
                                    <td>
                                        <span class="badge" style="background:<?php echo $form['status']=='completed'?'#d1fae5':'#fffbeb'; ?>; color:<?php echo $form['status']=='completed'?'#065f46':'#b45309'; ?>">
                                            <?php echo ucfirst($form['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($form['creator_email']); ?></td>
                                    <td style="text-align: right;">
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('PAS OP: Dit verwijdert het dossier definitief. Dit kan niet ongedaan worden gemaakt. Doorgaan?');">
                                            <input type="hidden" name="action" value="delete_form">
                                            <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
                                            <button type="submit" class="btn-icon delete" title="Definitief Verwijderen">
                                                <span class="material-icons-outlined" style="font-size:18px;">delete_forever</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="tab-stats" class="tab-content <?php echo ($activeTab === 'stats') ? 'active' : ''; ?>">
                <div class="grid-row">
                    <?php foreach($userStats as $stat): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="stat-number"><?php echo $stat['assigned_count']; ?></div>
                            <div class="stat-label">Gesprekken</div>
                            <div style="font-size:13px; margin-top:4px; font-weight:600;">
                                <?php 
                                    $displayName = (!empty($stat['first_name']) || !empty($stat['last_name'])) 
                                        ? $stat['first_name'] . ' ' . $stat['last_name'] 
                                        : $stat['email'];
                                    echo htmlspecialchars($displayName); 
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </main>

    <div id="userModal" class="modal-overlay">
        <div class="modal">
            <form method="POST">
                <div class="modal-header">
                    <span id="modalTitle">Gebruiker</span>
                    <button type="button" onclick="closeModal()" style="background:none; border:none; cursor:pointer; font-size:20px;">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="user_id" id="userId">

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Voornaam</label>
                            <input type="text" name="first_name" id="inputFirstName" class="form-control" placeholder="Jan">
                        </div>
                        <div class="form-group">
                            <label>Achternaam</label>
                            <input type="text" name="last_name" id="inputLastName" class="form-control" placeholder="Jansen">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>E-mailadres *</label>
                        <input type="email" name="email" id="inputEmail" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Rol</label>
                        <select name="role" id="inputRole" class="form-control">
                            <option value="user">Gebruiker (Teamlead)</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label id="passLabel">Wachtwoord</label>
                        <input type="password" name="password" id="inputPass" class="form-control" placeholder="Vul in om in te stellen/wijzigen">
                        <div id="passHelp" style="font-size:11px; color:#999; margin-top:4px; display:none;">Laat leeg om huidig wachtwoord te behouden.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#fff; border:1px solid #ddd;" onclick="closeModal()">Annuleren</button>
                    <button type="submit" class="btn btn-brand">Opslaan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- TABS LOGICA ---
        function openTab(tabName) {
            // Alle tabs verbergen
            document.querySelectorAll('.tab-content').forEach(div => div.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            
            // Gekozen tab tonen
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Knop highlighten
            // We zoeken de knop die deze functie aanroept, maar hier simpeler: selecteer op onclick attr
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => {
                if(btn.getAttribute('onclick').includes(tabName)) {
                    btn.classList.add('active');
                }
            });

            // Update URL zonder reload (optioneel, voor als je refresht)
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        // --- MODAL LOGICA ---
        const modal = document.getElementById('userModal');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const userId = document.getElementById('userId');
        
        const inputFirstName = document.getElementById('inputFirstName');
        const inputLastName = document.getElementById('inputLastName');
        const inputEmail = document.getElementById('inputEmail');
        const inputRole = document.getElementById('inputRole');
        const inputPass = document.getElementById('inputPass');
        const passLabel = document.getElementById('passLabel');
        const passHelp = document.getElementById('passHelp');

        function openModal(mode, userData = null) {
            modal.style.display = 'flex';
            
            if (mode === 'create') {
                modalTitle.textContent = 'Nieuwe Gebruiker Aanmaken';
                formAction.value = 'create';
                userId.value = '';
                
                inputFirstName.value = '';
                inputLastName.value = '';
                inputEmail.value = '';
                inputRole.value = 'user';
                
                inputPass.required = true;
                passLabel.textContent = 'Wachtwoord *';
                passHelp.style.display = 'none';
            } else {
                modalTitle.textContent = 'Gebruiker Bewerken';
                formAction.value = 'edit';
                userId.value = userData.id;
                
                inputFirstName.value = userData.first_name || '';
                inputLastName.value = userData.last_name || '';
                inputEmail.value = userData.email;
                inputRole.value = userData.role;
                
                inputPass.value = '';
                inputPass.required = false;
                passLabel.textContent = 'Wachtwoord (Optioneel)';
                passHelp.style.display = 'block';
            }
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>