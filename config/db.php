<?php
// config/db.php

// We sluiten eerst de configuratie in, zodat we de constanten (DB_HOST etc.) kunnen gebruiken
require_once 'config.php';

try {
    // Data Source Name (DSN) samenstellen
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

    // Opties voor de verbinding
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Gooi errors als er iets mis is
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Geef data terug als nette arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Gebruik echte prepared statements (veiliger)
    ];

    // De verbinding maken
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (\PDOException $e) {
    // Als de verbinding mislukt, stop alles en toon de foutmelding
    // In een echte productie omgeving toon je hier een nette "Oeps" pagina.
    die("Database verbinding mislukt: " . $e->getMessage());
}
?>