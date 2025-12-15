<?php
// config/config.php

session_start();

/**
 * Functie om handmatig een .env bestand in te laden.
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// 1. LAAD OMGEVINGSVARIABELEN
loadEnv(__DIR__ . '/../.env');

// 2. CONSTANTEN DEFINIËREN
define('APP_TITLE', $_ENV['APP_TITLE'] ?? 'FeedbackFlow');
define('BASE_URL',  $_ENV['BASE_URL']  ?? 'http://localhost');

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'chauffeurs_dossier');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// 3. OVERIGE INSTELLINGEN
date_default_timezone_set('Europe/Amsterdam');

if (isset($_ENV['DISPLAY_ERRORS']) && $_ENV['DISPLAY_ERRORS'] == '1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// --- 4. CSRF BEVEILIGING (NIEUW) ---

// Token genereren als die er nog niet is
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper functie voor in HTML formulieren
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

// Helper functie om de check te doen (aanroepen bij POST verwerking)
function verify_csrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Beveiligingsfout (CSRF): Sessie verlopen of ongeldig verzoek. Vernieuw de pagina.");
    }
}

// ... (Je bestaande code en CSRF functies) ...

// Helper functies inladen
require_once __DIR__ . '/../includes/helpers.php';

?>
?>