<?php
// logout.php
require_once 'config/config.php';

// Vernietig sessie data
session_destroy();

// Stuur terug naar login
header("Location: login.php");
exit;
?>