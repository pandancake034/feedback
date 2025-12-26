<?php
// login.php
require_once 'config/config.php';
require_once 'config/db.php';

// Check of gebruiker al is ingelogd
if (isset($_SESSION['user_id']) && empty($_POST)) {
    header("Location: /feedback/dashboard.php");
exit;

}

$error_message = "";
$login_success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // BEVEILIGING: CSRF Check (uit de vorige stap)
    verify_csrf();

    // BEVEILIGING: Input schoonmaken (Sanitize)
    // We halen spaties weg en zorgen dat het een geldig e-mailformaat is
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        try {
            // 1. Zoek de gebruiker
            $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // 2. Controleer wachtwoord
            if ($user && password_verify($password, $user['password'])) {
                
                // BEVEILIGING: Sessie Fixatie Preventie
                // Genereer een nieuw sessie-ID en verwijder de oude. 
                // Dit voorkomt dat hackers een gestolen sessie overnemen.
                session_regenerate_id(true);

                // 3. Login succesvol! Sla gegevens op
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Update de 'last_login' tijd
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                // Log deze actie (Succes)
                try {
                    $logStmt = $pdo->prepare("INSERT INTO system_logs (user_id, action_type, description, ip_address) VALUES (?, 'LOGIN', 'Succesvol ingelogd', ?)");
                    $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);
                } catch(PDOException $logEx) {
                    // Loggen mag de login niet breken, dus we vangen de error stil op
                }

                $login_success = true;
                
            } else {
                // BEVEILIGING: Vertraging tegen Brute Force (Timing Attack)
                // Wacht 1 seconde voordat we de foutmelding tonen.
                // Dit maakt het voor bots onmogelijk om duizenden wachtwoorden per minuut te proberen.
                sleep(1); 
                
                $error_message = "Ongeldig e-mailadres of wachtwoord.";
                
                // Optioneel: Je zou hier ook mislukte pogingen kunnen loggen in system_logs
                // als je database 'user_id' op NULL toestaat.
            }
        } catch (PDOException $e) {
            $error_message = "Er ging iets mis met de database."; // Toon geen technische details aan de gebruiker
        }
    } else {
        $error_message = "Vul alle velden in.";
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
        /* --- STYLING (Ongewijzigd) --- */
        :root { --brand-color: #0176d3; --brand-dark: #014486; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; --border-color: #dddbda; --card-shadow: 0 2px 2px 0 rgba(0,0,0,0.1); --danger-bg: #fde8e8; --danger-text: #ea001e; --danger-border: #fbd5d5; }
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

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <span class="material-icons-outlined" style="font-size: 18px;">error_outline</span>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <?php echo csrf_field(); ?>

                <div class="input-group">
                    <label for="email">E-mailadres</label>
                    <input type="email" id="email" name="email" placeholder="naam@hellofresh.nl" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="input-group">
                    <label for="password">Wachtwoord</label>
                    <input type="password" id="password" name="password" placeholder="Voer je wachtwoord in" required>
                    <span class="material-icons-outlined toggle-password" onclick="togglePassword()">visibility</span>
                </div>

                <button type="submit">Inloggen</button>
            </form>
            
            <div class="footer-text">
                &copy; <?php echo date('Y'); ?> <?php echo defined('APP_TITLE') ? APP_TITLE : 'Chauffeurs Dossier'; ?>
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