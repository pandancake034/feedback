<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beveiligde Inlogomgeving</title>
    <style>
        /* --- 1. ALGEMENE STIJL --- */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #e9ecef;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        /* --- 2. DE KAART (CONTAINER) --- */
        .login-card {
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 350px;
            text-align: center;
        }

        .login-card h2 {
            margin-bottom: 20px;
            color: #333;
        }

        /* --- 3. INPUT VELDEN & GROEPEN --- */
        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: #666;
            font-weight: 600;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            padding-right: 40px; /* Ruimte voor het oog-icoon */
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box; /* Belangrijk voor padding */
            transition: border-color 0.3s;
        }

        .input-group input:focus {
            border-color: #007bff;
            outline: none;
        }

        /* Icoontje om wachtwoord te tonen */
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 38px;
            cursor: pointer;
            color: #888;
            user-select: none;
        }

        /* --- 4. MELDINGEN & VEILIGHEID --- */
        .message-box {
            display: none; /* Standaard verborgen */
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .message-error {
            background-color: #fde8e8;
            color: #c53030;
            border: 1px solid #fbd5d5;
            display: block;
        }
        .message-success {
            background-color: #def7ec;
            color: #03543f;
            border: 1px solid #bcf0da;
            display: block;
        }

        /* --- 5. KNOPPEN & ACTIES --- */
        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #0056b3;
        }

        /* Laad-animatie klasse voor de knop */
        button.loading {
            background-color: #ccc;
            cursor: not-allowed;
            position: relative;
            color: transparent; /* Verberg tekst tijdens laden */
        }
        button.loading::after {
            content: "";
            position: absolute;
            left: 50%;
            top: 50%;
            width: 16px;
            height: 16px;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid #333;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .footer-links {
            margin-top: 20px;
            font-size: 12px;
        }
        .footer-links a {
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Inloggen</h2>

    <div id="messageBox" class="message-box"></div>

    <form id="loginForm">
        <div class="input-group">
            <label for="email">E-mailadres</label>
            <input type="email" id="email" placeholder="naam@voorbeeld.nl" required>
        </div>

        <div class="input-group">
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" placeholder="Je wachtwoord" required>
            <span class="toggle-password" onclick="togglePassword()">👁</span>
        </div>

        <button type="submit" id="submitBtn">Veilig Inloggen</button>
    </form>

    <div class="footer-links">
        <a href="#">Wachtwoord vergeten?</a>
    </div>
</div>

<script>
    // --- JAVASCRIPT LOGICA ---

    // 1. Functie om wachtwoord zichtbaar/onzichtbaar te maken
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.querySelector('.toggle-password');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.textContent = '🙈'; // Verander icoon
        } else {
            passwordInput.type = 'password';
            icon.textContent = '👁'; // Verander icoon terug
        }
    }

    // 2. Het inlogproces afhandelen
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Voorkom dat de pagina ververst

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const messageBox = document.getElementById('messageBox');
        const submitBtn = document.getElementById('submitBtn');

        // Reset berichten
        messageBox.className = 'message-box';
        messageBox.style.display = 'none';

        // Basis Validatie (Beveiliging stap 1)
        if(password.length < 6) {
            showMessage("Wachtwoord moet minimaal 6 tekens zijn.", "error");
            return;
        }

        // Start 'Laden' (Simulatie van server communicatie)
        submitBtn.classList.add('loading');
        
        // Simuleer wachttijd van 2 seconden
        setTimeout(() => {
            submitBtn.classList.remove('loading');

            // Simuleer een check (In het echt checkt de server dit)
            if (email === "test@test.nl" && password === "123456") {
                showMessage("Succes! Je wordt doorgestuurd...", "success");
                // Hier zou je normaal doorverwijzen: window.location.href = '/dashboard';
            } else {
                showMessage("Ongeldig e-mailadres of wachtwoord.", "error");
            }
        }, 2000);
    });

    // Hulpfunctie om berichten te tonen
    function showMessage(text, type) {
        const messageBox = document.getElementById('messageBox');
        messageBox.textContent = text;
        messageBox.style.display = 'block';
        if(type === 'error') {
            messageBox.classList.add('message-error');
        } else {
            messageBox.classList.add('message-success');
        }
    }
</script>

</body>
</html>