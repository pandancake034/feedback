<?php
// config/config.php

// 1. VEILIGE SESSIE INSTELLINGEN (Moet voor session_start)
// Zorgt dat cookies niet via JavaScript uit te lezen zijn (HttpOnly) en alleen via HTTPS werken (indien beschikbaar)
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams["lifetime"],
    'path' => $cookieParams["path"],
    'domain' => $cookieParams["domain"],
    'secure' => isset($_SERVER['HTTPS']), // True als HTTPS gebruikt wordt
    'httponly' => true, // Beschermt tegen XSS
    'samesite' => 'Strict' // Beschermt tegen CSRF
]);

session_start();

// 2. AUTOMATISCHE LOGOUT (SESSION TIMEOUT)
$timeout_duration = 1800; // 1800 seconden = 30 minuten

if (isset($_SESSION['last_activity'])) {
    $duration = time() - $_SESSION['last_activity'];
    if ($duration > $timeout_duration) {
        // Sessie is verlopen
        session_unset();
        session_destroy();
        // Stuur door naar login met melding
        header("Location: " . (isset($_ENV['BASE_URL']) ? $_ENV['BASE_URL'] : '') . "/index.php?msg=timeout");
        exit;
    }
}
// Update laatste activiteit tijdstempel
$_SESSION['last_activity'] = time();

// 3. SECURITY HEADERS
// Beschermt tegen clickjacking en MIME-type sniffing
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// --- BESTAANDE CONFIGURATIE ---

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name); $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

loadEnv(__DIR__ . '/../.env');

define('APP_TITLE', $_ENV['APP_TITLE'] ?? 'FeedbackFlow');
define('BASE_URL',  $_ENV['BASE_URL']  ?? 'http://localhost');
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'chauffeurs_dossier');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

date_default_timezone_set('Europe/Amsterdam');

if (isset($_ENV['DISPLAY_ERRORS']) && $_ENV['DISPLAY_ERRORS'] == '1') {
    ini_set('display_errors', 1); error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0); error_reporting(0);
}

// CSRF Token Generatie
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

function verify_csrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Beveiligingsfout (CSRF): Sessie verlopen. Vernieuw de pagina.");
    }
}

require_once __DIR__ . '/../includes/helpers.php';
?>