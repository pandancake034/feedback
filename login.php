<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mijn Inlogpagina</title>
    
    <style>
        /* Dit zorgt voor een mooie achtergrond en zet alles in het midden */
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh; /* Volledige hoogte van het scherm */
            margin: 0;
        }

        /* Dit is de "kaart" waar het inlogformulier in zit */
        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px; /* Ronde hoekjes */
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); /* Schaduw effect */
            width: 300px;
            text-align: center;
        }

        /* De stijl van de invulvelden */
        input[type="text"], input[type="password"] {
            width: 100%; /* Vul de hele breedte */
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box; /* Zorgt dat padding de breedte niet verpest */
        }

        /* De stijl van de inlogknop */
        button {
            background-color: #007bff; /* Blauwe kleur */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }

        /* Kleurverandering als je over de knop muist */
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <h2>Welkom Terug</h2>
        
        <form>
            <label for="username" style="display:none;">Gebruikersnaam</label>
            <input type="text" id="username" name="username" placeholder="Gebruikersnaam" required>
            
            <label for="password" style="display:none;">Wachtwoord</label>
            <input type="password" id="password" name="password" placeholder="Wachtwoord" required>
            
            <button type="submit">Inloggen</button>
        </form>
        
        <p style="margin-top: 15px; font-size: 12px; color: #666;">
            Nog geen account? <a href="#">Registreer hier</a>
        </p>
    </div>

</body>
</html>