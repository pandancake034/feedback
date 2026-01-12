<?php
/**
 * LOGOUT.PHP
 * Logt de gebruiker uit en toont een spinner voordat er wordt doorgestuurd.
 */

// We laden config om eventuele basis instellingen te hebben, 
// maar we gaan vooral de sessie slopen.
require_once __DIR__ . '/config/config.php';

// Sessie starten (als die nog niet gestart is) om hem te kunnen vernietigen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Alle sessie variabelen leegmaken
$_SESSION = [];

// Sessie cookie verwijderen indien aanwezig
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Sessie vernietigen
session_destroy();

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Uitloggen...</title>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- COPY STYLING VAN LOGIN PAGINA --- */
        :root { --brand-color: #0176d3; --bg-body: #f3f2f2; --text-main: #181818; --text-secondary: #706e6b; }
        
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            text-align: center;
        }

        .logout-card {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 320px;
            display: flex;
            flex-direction: column;
            align-items: center;
            animation: fadeIn 0.5s ease-out;
        }

        h2 { margin: 0 0 10px 0; font-size: 20px; font-weight: 600; }
        p { margin: 0 0 20px 0; color: var(--text-secondary); font-size: 14px; }

        /* SPINNER */
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--brand-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="logout-card">
        <div class="spinner"></div>
        <h2>Tot ziens!</h2>
        <p>Je wordt veilig uitgelogd...</p>
    </div>

    <script>
        // Wacht 2000 milliseconden (2 seconden) en stuur dan door
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 2000);
    </script>

</body>
</html>