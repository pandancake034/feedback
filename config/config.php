<?php
// config/config.php
//

// Start de sessie op elke pagina
session_start();

// 1. ALGEMENE INSTELLINGEN
define('APP_TITLE', 'FeedbackFlow | HUB Nieuwegein'); // <--- HIER IS JE NIEUWE TITEL

// 2. DATABASE INSTELLINGEN
define('DB_HOST', 'localhost');
define('DB_NAME', 'chauffeurs_dossier');
define('DB_USER', 'root');
define('DB_PASS', 'Mango2025!@');

// 3. OVERIGE INSTELLINGEN
define('BASE_URL', 'http://172.21.24.76/feedback');
date_default_timezone_set('Europe/Amsterdam');

// Foutmeldingen (Zet op 0 in productie)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>