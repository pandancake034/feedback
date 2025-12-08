<?php
/**
 * FEEDBACK_FORM.PHP
 * Het invulformulier voor een specifiek feedback gesprek.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// 1. BEVEILIGING & CONTROLES
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check of er een ID is meegegeven
if (!isset($_GET['id'])) {
    header("Location: dashboard.php"); // Geen ID? Terug naar dashboard.
    exit;
}

$form_id = $_GET['id'];
$error   = "";
$success = "";

// 2. DATA OPHALEN
try {
    // Haal formulier data op + naam van de chauffeur
    $sql = "SELECT f.*, d.name as driver_name, d.employee_id 
            FROM feedback_forms f 
            JOIN drivers d ON f.driver_id = d.id 
            WHERE f.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$form_id]);
    $form = $stmt->fetch();

    if (!$form) {
        die("Formulier niet gevonden.");
    }

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

// 3. OPSLAAN (POST VERWERKING)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Bepaal de status: Als er op "Afronden" is geklikt, wordt status 'completed'
        $new_status = (isset($_POST['action']) && $_POST['action'] === 'complete') ? 'completed' : 'open';

        $sql = "UPDATE feedback_forms SET 
                    routes_count = ?,
                    otd_score = ?,
                    ftr_score = ?,
                    errors_text = ?,
                    nokd_text = ?,
                    late_text = ?,
                    driving_behavior = ?,
                    warnings = ?,
                    kw_score = ?,
                    skills_rating = ?,
                    proficiency_rating = ?,
                    client_compliment = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['routes_count'] ?? 0,
            $_POST['otd_score'],
            $_POST['ftr_score'],
            $_POST['errors_text'],
            $_POST['nokd_text'],
            $_POST['late_text'],
            $_POST['driving_behavior'],
            $_POST['warnings'],
            $_POST['kw_score'],
            $_POST['skills_rating'],
            $_POST['proficiency_rating'],
            $_POST['client_compliment'],
            $new_status,
            $form_id
        ]);

        // Herlaad de pagina om de nieuwe waarden te tonen
        if ($new_status === 'completed') {
            header("Location: dashboard.php?msg=completed");
            exit;
        } else {
            $success = "Wijzigingen succesvol opgeslagen.";
            // Ververs data
            $stmt = $pdo->prepare("SELECT f.*, d.name as driver_name, d.employee_id FROM feedback_forms f JOIN drivers d ON f.driver_id = d.id WHERE f.id = ?");
            $stmt->execute([$form_id]);
            $form = $stmt->fetch();
        }

    } catch (PDOException $e) {
        $error = "Fout bij opslaan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Invullen | <?php echo htmlspecialchars($form['driver_name']); ?></title>
    
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- CSS (Zelfde Enterprise Stijl) --- */
        :root {
            --brand-color: #0176d3;
            --brand-dark: #014486;
            --sidebar-bg: #1a2233;
            --bg-body: #f3f2f2;
            --text-main: #181818;
            --text-secondary: #706e6b;
            --border-color: #dddbda;
            --card-shadow: 0 2px 2px 0 rgba(0,0,0,0.1);
            --success-bg: #d1fae5;
            --success-text: