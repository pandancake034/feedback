<?php
// config/config.php

// Start de sessie op elke pagina die dit bestand insluit
session_start();

// Database Instellingen (PAS DEZE AAN naar jouw gegevens)
define('DB_HOST', 'localhost');
define('DB_NAME', 'chauffeurs_dossier');
define('DB_USER', 'root'); // Vaak 'root' op lokale servers
define('DB_PASS', 'Mango2025!@');     // Vaak leeg op lokale servers, anders invullen

// Basis URL van de applicatie (handig voor links)
// Pas dit aan als je map anders heet of online staat
define('BASE_URL', 'http://172.21.24.76/feedback');
/ Tijdzone instellen op Nederland
date_default_timezone_set('Europe/Amsterdam');

// Foutmeldingen tonen (zet dit op 0 als de site live gaat!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
