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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Functie om URL parameters uit te lezen (zoals ?msg=created)
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

// Check of er een bericht is en toon een mooie Toast
document.addEventListener('DOMContentLoaded', function() {
    const msg = getUrlParameter('msg');
    
    if (msg) {
        let title = 'Succes!';
        let icon = 'success';
        let text = '';

        // Pas tekst aan op basis van de msg
        switch(msg) {
            case 'created': text = 'Item succesvol aangemaakt.'; break;
            case 'updated': text = 'Gegevens succesvol gewijzigd.'; break;
            case 'deleted': text = 'Item verwijderd.'; icon = 'warning'; break;
            case 'saved':   text = 'Concept opgeslagen.'; break;
            case 'completed': text = 'Dossier succesvol afgerond!'; break;
            default: text = msg; // Fallback voor custom tekst
        }

        // Fire de Toast (rechtsboven, verdwijnt na 3 sec)
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: text,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
        
        // Haal de parameter uit de URL zodat hij niet blijft staan bij refresh
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>
</footer>