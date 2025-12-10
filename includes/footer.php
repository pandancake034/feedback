<style>
    .app-footer {
        margin-top: auto; /* Zorgt dat footer naar beneden duwt als flex container gebruikt wordt */
        padding: 20px 24px;
        border-top: 1px solid var(--border-color);
        background-color: #fff;
        color: var(--text-secondary);
        font-size: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .footer-links a {
        color: var(--brand-color);
        text-decoration: none;
        margin-left: 15px;
    }
    .footer-links a:hover {
        text-decoration: underline;
    }
</style>

<footer class="app-footer">
    <div>
        &copy; <?php echo date('Y'); ?> FeedbackFlow | Alle rechten voorbehouden.
    </div>
    <div class="footer-links">
        <a href="#">Privacy</a>
        <a href="#">Support</a>
        <span>Versie 1.0.2</span>
    </div>
</footer>