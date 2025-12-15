<?php
// includes/helpers.php

/**
 * 1. STATUS BADGE
 * Geeft direct de juiste HTML <span> terug voor statussen (Open/Completed).
 */
function format_status_badge($status) {
    // Zorg dat we altijd met kleine letters vergelijken voor de zekerheid
    $status = strtolower($status ?? '');
    
    if ($status === 'completed') {
        return '<span class="status-badge bg-completed">Completed</span>';
    }
    // Default fallback is 'Open'
    return '<span class="status-badge bg-open">Open</span>';
}

/**
 * 2. ROL BADGE (Voor Admin paneel)
 * Geeft een blauw label voor Admin, grijs voor User.
 */
function format_role_badge($role) {
    $role = strtolower($role ?? '');
    $class = ($role === 'admin') ? 'badge-admin' : 'badge-user';
    // ucfirst() zorgt voor een hoofdletter (admin -> Admin)
    return '<span class="badge ' . $class . '">' . htmlspecialchars(ucfirst($role)) . '</span>';
}

/**
 * 3. SCORE FORMATTER (Voor OTD / FTR scores)
 * Geeft een groen label als de score hoog genoeg is (boven threshold).
 */
function format_score_badge($value, $threshold = 96) {
    if ($value === '' || $value === null) {
        return '-';
    }
    
    // Haal het % teken even weg om te kunnen rekenen
    $numericValue = floatval(str_replace('%', '', $value));
    
    $class = 'score-badge';
    if ($numericValue >= $threshold) {
        $class .= ' success';
    }
    
    return '<span class="' . $class . '">' . htmlspecialchars($value) . '</span>';
}

/**
 * 4. SLIMME NAAM WEERGAVE
 * Toont "Voornaam Achternaam" als die er zijn, anders het e-mailadres.
 */
function format_user_name($firstName, $lastName, $email) {
    if (!empty($firstName) || !empty($lastName)) {
        return htmlspecialchars(trim($firstName . ' ' . $lastName));
    }
    return htmlspecialchars($email);
}
?>