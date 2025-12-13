<?php
// login.php
require_once 'config/config.php'; // Voor sessies en instellingen
require_once 'config/db.php';     // Voor de database verbinding

// Check of gebruiker al is ingelogd
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error_message = "";

// Verwerk het formulier als er op de knop gedrukt is
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        try {
            // 1. Zoek de gebruiker in de database
            $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // 2. Controleer wachtwoord
            if ($user && password_verify($password, $user['password'])) {
                // 3. Login succesvol! Sla gegevens op in sessie
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Update de 'last_login' tijd in de database
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                // Log deze actie in system_logs
                $logStmt = $pdo->prepare("INSERT INTO system_logs (user_id, action_type, description, ip_address) VALUES (?, 'LOGIN', 'Gebruiker is ingelogd', ?)");
                $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);

                header("Location: dashboard.php");
                exit;
            } else {
                $error_message = "Ongeldig e-mailadres of wachtwoord.";
            }
        } catch (PDOException $e) {
            $error_message = "Er ging iets mis met de database: " . $e->getMessage();
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
        /* --- ENTERPRISE THEME VARIABLES --- */
        :root {
            --brand-color: #0176d3;
            --brand-dark: #014486;
            --bg-body: #f3f2f2;
            --text-main: #181818;
            --text-secondary: #706e6b;
            --border-color: #dddbda;
            --card-shadow: 0 2px 2px 0 rgba(0,0,0,0.1);
            --danger-bg: #fde8e8;
            --danger-text: #ea001e;
            --danger-border: #fbd5d5;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-card {
            background-color: #fff;
            padding: 40px;
            border-radius: 4px; /* Enterprise style is vaak iets hoekiger dan 12px */
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .app-logo {
            font-size: 24px;
            font-weight: 300;
            color: var(--text-secondary);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 24px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
        }

        .input-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
            color: var(--text-main);
            transition: border-color 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--brand-color);
            box-shadow: 0 0 0 1px var(--brand-color);
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 36px;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 18px;
            user-select: none;
        }

        button {
            width: 100%;
            padding: 10px 16px;
            background-color: var(--brand-color);
            color: white;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        button:hover {
            background-color: var(--brand-dark);
        }

        /* Error box stijl */
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-danger {
            background-color: var(--danger-bg);
            color: var(--danger-text);
            border: 1px solid var(--danger-border);
        }
        
        .footer-text {
            margin-top: 24px;
            font-size: 12px;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>

<div class="login-card">
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
        &copy; <?php echo date('Y'); ?> <?php echo defined('APP_TITLE') ? APP_TITLE : 'Chauffeurs Dossier'; ?>
    </div>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.querySelector('.toggle-password');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text'; 
            icon.textContent = 'visibility_off';
        } else {
            passwordInput.type = 'password'; 
            icon.textContent = 'visibility';
        }
    }
</script>

</body>
</html>