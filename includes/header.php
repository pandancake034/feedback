<header class="top-header">
    <div class="header-left">
        <?php if(isset($page_title)): ?>
            <div style="font-weight: 700; font-size: 16px; color: var(--text-main); display:flex; align-items:center; gap:8px;">
                <span class="material-icons-outlined" style="color:var(--brand-color);">folder_shared</span> 
                <?php echo htmlspecialchars($page_title); ?>
            </div>
        <?php else: ?>
            <div class="search-bar" onclick="if(typeof openSearch === 'function') openSearch();">
                <input type="text" placeholder="Druk op '/' om te zoeken..." readonly style="cursor: pointer; border: 1px solid var(--border-color); padding: 8px 12px; border-radius: 4px; font-size: 13px; background: #f9f9f9;">
            </div>
        <?php endif; ?>
    </div>

    <div id="live-clock" style="font-size: 13px; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 6px;">
        <span class="material-icons-outlined" style="font-size: 16px;">schedule</span>
        <span id="clock-text"><?php echo date('l d-m-Y H:i'); ?></span>
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
                // Nederlandse notatie: bv. zaterdag 13-12-2025 14:30
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: '2-digit', 
                    day: '2-digit', 
                    hour: '2-digit', 
                    minute: '2-digit' 
                };
                const timeString = now.toLocaleDateString('nl-NL', options).replace(',', '');
                const clockEl = document.getElementById('clock-text');
                if(clockEl) clockEl.innerText = timeString.charAt(0).toUpperCase() + timeString.slice(1);
            }
            setInterval(updateClock, 1000);
            updateClock(); // Direct uitvoeren bij laden
        })();
    </script>
</header>