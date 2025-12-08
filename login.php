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

                // Log deze actie in system_logs (zoals in je database ontwerp)
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
    <title>Inloggen - Chauffeurs Dossier</title>
    <style>
        /* Jouw bestaande CSS, iets ingekort voor overzicht */
        body { font-family: 'Segoe UI', sans-serif; background-color: #e9ecef; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background-color: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 350px; text-align: center; }
        .input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 5px; color: #666; font-weight: 600; }
        .input-group input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        .toggle-password { position: absolute; right: 10px; top: 38px; cursor: pointer; }
        button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #0056b3; }
        
        /* Error box stijl */
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; text-align: left; }
        .alert-danger { background-color: #fde8e8; color: #c53030; border: 1px solid #fbd5d5; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Dossier Inloggen</h2>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="input-group">
            <label for="email">E-mailadres</label>
            <input type="email" id="email" name="email" placeholder="admin@logistiek.nl" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>

        <div class="input-group">
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password" placeholder="Wachtwoord" required>
            <span class="toggle-password" onclick="togglePassword()">👁</span>
        </div>

        <button type="submit">Inloggen</button>
    </form>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.querySelector('.toggle-password');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text'; icon.textContent = '🙈';
        } else {
            passwordInput.type = 'password'; icon.textContent = '👁';
        }
    }
</script>

</body>
</html>
