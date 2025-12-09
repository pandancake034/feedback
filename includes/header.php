<header class="top-header">
    <div class="header-left">
        <?php if(isset($page_title)): ?>
            <div style="font-weight: 700; font-size: 16px; color: var(--text-main); display:flex; align-items:center; gap:8px;">
                <span class="material-icons-outlined" style="color:var(--brand-color);">folder_shared</span> 
                <?php echo htmlspecialchars($page_title); ?>
            </div>
        <?php else: ?>
            <div class="search-bar">
                <input type="text" placeholder="Zoeken..." style="border: 1px solid var(--border-color); padding: 8px 12px; border-radius: 4px;">
            </div>
        <?php endif; ?>
    </div>

    <div class="user-profile">
        <span class="material-icons-outlined" style="margin-right: 8px; color: var(--text-secondary);">account_circle</span>
        <?php echo htmlspecialchars($_SESSION['email'] ?? 'Gebruiker'); ?>
        <a href="logout.php" title="Uitloggen" style="margin-left: 15px; color: var(--text-secondary); text-decoration: none; display: flex; align-items: center;">
            <span class="material-icons-outlined" style="font-size: 20px;">logout</span>
        </a>
    </div>
</header>