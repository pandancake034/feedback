<?php
// includes/sidebar.php

// Huidige pagina naam ophalen om de 'active' class te zetten
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin_page = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;

// Check of gebruiker admin is (sessie is al gestart in de parent page)
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
?>
<aside class="sidebar">
    <div class="sidebar-header bg-white flex items-center justify-center p-4" style="background-color: #ffffff; border-bottom: 1px solid #dddbda;">
        <a href="<?php echo BASE_URL; ?>/dashboard.php" style="text-decoration:none; border:none;">
            <img src="https://i.imgur.com/qGySlgO.png" alt="LogistiekApp" class="sidebar-logo">
        </a>
    </div>

    <ul class="nav-list">
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">dashboard</span>
                Home
            </a>
        </li>

        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/feedback_overview.php" class="<?php echo ($current_page == 'feedback_overview.php') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">summarize</span>
                Overzicht
            </a>
        </li>

        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/feedback_form.php" class="<?php echo ($current_page == 'feedback_form.php' || $current_page == 'feedback_create.php') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">add_circle</span>
                Nieuw gesprek
            </a>
        </li>

        <?php if ($is_admin): ?>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/admin/index.php" class="<?php echo ($is_admin_page) ? 'active' : ''; ?>">
                <span class="material-icons-outlined">admin_panel_settings</span>
                Admin
            </a>
        </li>
        <?php endif; ?>
    </ul>
    
    <div style="margin-top: auto; padding: 20px;">
        <a href="<?php echo BASE_URL; ?>/logout.php" style="color: #b0b6c3; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 14px; transition: 0.2s;">
            <span class="material-icons-outlined">logout</span> Uitloggen
        </a>
    </div>
</aside>