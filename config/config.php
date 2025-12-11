<?php
// config/config.php

session_start();

/**
 * Functie om handmatig een .env bestand in te laden.
 * Dit vervangt de noodzaak voor een externe library.
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        // Als er geen .env is, stop dan (of geef een error)
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Sla commentaar regels over (beginnend met #)
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Splits op het = teken
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Zet in $_ENV en $_SERVER
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// 1. LAAD OMGEVINGSVARIABELEN
// We gaan één map omhoog (__DIR__ . '/../') om bij de root te komen
loadEnv(__DIR__ . '/../.env');

// 2. CONSTANTEN DEFINIËREN (Gebruikt door db.php en de rest van de app)
// We gebruiken ?? '' als fallback voor het geval de .env waarde mist.

define('APP_TITLE', $_ENV['APP_TITLE'] ?? 'FeedbackFlow');
define('BASE_URL',  $_ENV['BASE_URL']  ?? 'http://localhost');

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'chauffeurs_dossier');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// 3. OVERIGE INSTELLINGEN
date_default_timezone_set('Europe/Amsterdam');

// Foutmeldingen aan/uit zetten op basis van .env
if (isset($_ENV['DISPLAY_ERRORS']) && $_ENV['DISPLAY_ERRORS'] == '1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
?>