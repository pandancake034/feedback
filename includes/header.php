<header class="top-header">
    <div class="header-left">
        <?php if(isset($page_title)): ?>
            <div style="font-weight: 700; font-size: 16px; color: var(--text-main); display:flex; align-items:center; gap:8px;">
                <span class="material-icons-outlined" style="color:var(--brand-color);">folder_shared</span> 
                <?php echo htmlspecialchars($page_title); ?>
            </div>
        <?php else: ?>
            <div class="search-bar">
                <input type="text" placeholder="Zoeken..." style="border: 1px solid var(--border-color); padding: 8px 12px; border-radius: 4px; font-size: 13px;">
            </div>
        <?php endif; ?>
    </div>

    <div id="live-clock" style="font-size: 13px; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 6px;">
        <span class="material-icons-outlined" style="font-size: 16px;">schedule</span>
        <span id="clock-text"><?php echo date('d-m-Y H:i'); ?></span>
    </div>

    <div class="user-profile" style="display:flex; align-items:center;">
        <span class="material-icons-outlined" style="margin-right: 8px; color: var(--text-secondary);">account_circle</span>
        <span style="font-size: 13px; font-weight: 600;"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Gebruiker'); ?></span>
        <a href="logout.php" title="Uitloggen" style="margin-left: 15px; color: var(--text-secondary); text-decoration: none; display: flex; align-items: center;">
            <span class="material-icons-outlined" style="font-size: 20px;">logout</span>
        </a>
    </div>

    <script>
        (function() {
            function updateClock() {
                const now = new Date();
                const day = String(now.getDate()).padStart(2, '0');
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const year = now.getFullYear();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                
                const timeString = `${day}-${month}-${year} ${hours}:${minutes}`;
                const clockEl = document.getElementById('clock-text');
                if(clockEl) clockEl.innerText = timeString;
            }
            // Update elke seconde (zodat de minuutsprong direct zichtbaar is)
            setInterval(updateClock, 1000);
        })();
    </script>
</header>