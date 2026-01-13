<?php
// index.php (Login Pagina)
require_once 'config/config.php';
require_once 'config/db.php';

// Check of gebruiker al is ingelogd
if (isset($_SESSION['user_id']) && empty($_POST)) {
    header("Location: dashboard.php");
    exit;
}

$error_message = "";
$login_success = false;

// --- FUNCTIES VOOR BRUTE FORCE PROTECTION ---

function checkLoginAttempts($pdo, $ip) {
    // Verwijder blokkades ouder dan 15 minuten
    $pdo->query("DELETE FROM login_attempts WHERE last_attempt_at < (NOW() - INTERVAL 15 MINUTE)");

    $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $attempt = $stmt->fetch();

    // Blokkeer na 5 pogingen
    if ($attempt && $attempt['attempts'] >= 5) {
        return false; // Gebruiker is geblokkeerd
    }
    return true; // Gebruiker mag proberen
}

function recordFailedLogin($pdo, $ip) {
    $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $attempt = $stmt->fetch();

    if ($attempt) {
        $pdo->prepare("UPDATE login_attempts SET attempts = attempts + 1, last_attempt_at = NOW() WHERE ip_address = ?")->execute([$ip]);
    } else {
        $pdo->prepare("INSERT INTO login_attempts (ip_address, attempts) VALUES (?, 1)")->execute([$ip]);
    }
}

function resetLoginAttempts($pdo, $ip) {
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
}

// --- LOGIN LOGICA ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    verify_csrf();
    
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // 1. Check of IP geblokkeerd is
    if (!checkLoginAttempts($pdo, $ip_address)) {
        $error_message = "Te veel foute pogingen. Probeer het over 15 minuten opnieuw.";
    } else {
        // Input cleanen
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        if (!empty($email) && !empty($password)) {
            try {
                $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    
                    // 2. Login Succesvol
                    session_regenerate_id(true); // Voorkomt Session Fixation
                    resetLoginAttempts($pdo, $ip_address); // Reset foute pogingen

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time(); // Start timer voor auto-logout

                    // Update last_login
                    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                    
                    // Log in system_logs (optioneel, als tabel bestaat)
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO system_logs (user_id, action_type, description, ip_address) VALUES (?, 'LOGIN', 'Succesvol ingelogd', ?)");
                        $logStmt->execute([$user['id'], $ip_address]);
                    } catch(PDOException $e) { /* Negeer log fout */ }

                    $login_success = true;
                    
                } else {
                    // 3. Login Mislukt
                    recordFailedLogin($pdo, $ip_address);
                    sleep(1); // Vertraging tegen snelle bots
                    $error_message = "Ongeldig e-mailadres of wachtwoord.";
                }
            } catch (PDOException $e) {
                $error_message = "Er ging iets mis met de database.";
            }
        } else {
            $error_message = "Vul alle velden in.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - <?php echo defined('APP_TITLE') ? APP_TITLE : 'LogistiekApp'; ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* (Dezelfde CSS als voorheen) */
        :root { --brand-color: #0176d3; --brand-dark: #014486; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; --border-color: #dddbda; --card-shadow: 0 2px 2px 0 rgba(0,0,0,0.1); --danger-bg: #fde8e8; --danger-text: #ea001e; --danger-border: #fbd5d5; --warning-bg: #fffbeb; --warning-text: #b45309; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-body); color: var(--text-main); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background-color: #fff; padding: 40px; border-radius: 4px; border: 1px solid var(--border-color); box-shadow: var(--card-shadow); width: 100%; max-width: 400px; text-align: center; min-height: 400px; display: flex; flex-direction: column; justify-content: center; }
        .app-logo { font-size: 24px; font-weight: 300; color: var(--text-secondary); margin-bottom: 24px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        h2 { font-size: 20px; font-weight: 700; color: var(--text-main); margin-bottom: 24px; }
        .input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 6px; color: var(--text-secondary); font-size: 13px; font-weight: 600; }
        .input-group input { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 4px; box-sizing: border-box; font-size: 14px; color: var(--text-main); transition: border-color 0.2s, box-shadow 0.2s; font-family: inherit; }
        .input-group input:focus { outline: none; border-color: var(--brand-color); box-shadow: 0 0 0 1px var(--brand-color); }
        .toggle-password { position: absolute; right: 12px; top: 36px; cursor: pointer; color: var(--text-secondary); font-size: 18px; user-select: none; }
        button { width: 100%; padding: 10px 16px; background-color: var(--brand-color); color: white; border: 1px solid transparent; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; transition: background-color 0.2s; }
        button:hover { background-color: var(--brand-dark); }
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 13px; text-align: left; display: flex; align-items: center; gap: 8px; }
        .alert-danger { background-color: var(--danger-bg); color: var(--danger-text); border: 1px solid var(--danger-border); }
        .alert-warning { background-color: var(--warning-bg); color: var(--warning-text); border: 1px solid #fcd34d; }
        .footer-text { margin-top: 24px; font-size: 12px; color: var(--text-secondary); }
        .spinner-container { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; }
        .spinner { width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid var(--brand-color); border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .success-text { font-size: 16px; font-weight: 600; color: var(--brand-color); animation: fadeIn 0.5s ease-in; }
    </style>
</head>
<body>

<div class="login-card">
    
    <?php if ($login_success): ?>
        <div class="spinner-container">
            <div class="spinner"></div>
            <div class="success-text">Succesvol ingelogd!</div>
            <div style="font-size: 13px; color: #999; margin-top: 8px;">Je wordt doorgestuurd...</div>
        </div>
        <script>setTimeout(function() { window.location.href = "dashboard.php"; }, 1500);</script>
    <?php else: ?>
        <div style="width: 100%;">
            <div class="app-logo">
                <span class="material-icons-outlined" style="font-size: 28px; color: var(--brand-color);">local_shipping</span>
                FeedbackFlow
            </div>
            
            <h2>Welkom terug</h2>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'timeout'): ?>
                <div class="alert alert-warning">
                    <span class="material-icons-outlined" style="font-size: 18px;">timer</span>
                    Je bent automatisch uitgelogd wegens inactiviteit.
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <span class="material-icons-outlined" style="font-size: 18px;">error_outline</span>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <?php echo csrf_field(); ?>

                <div class="input-group">
                    <label for="email">E-mailadres</label>
                    <input type="email" id="email" name="email" placeholder="naam@bedrijf.nl" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="input-group">
                    <label for="password">Wachtwoord</label>
                    <input type="password" id="password" name="password" placeholder="Voer je wachtwoord in" required>
                    <span class="material-icons-outlined toggle-password" onclick="togglePassword()">visibility</span>
                </div>

                <button type="submit">Inloggen</button>
            </form>
            
            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> <?php echo defined('APP_TITLE') ? APP_TITLE : 'FeedbackFlow'; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.querySelector('.toggle-password');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text'; icon.textContent = 'visibility_off';
        } else {
            passwordInput.type = 'password'; icon.textContent = 'visibility';
        }
    }
</script>

</body>
</html>