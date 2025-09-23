<?php
/* ================= DB ================= */
require_once __DIR__ . '/connect.php';

try {
    $conn = db();   // usa la funzione definita in connect.php
} catch (Throwable $e) {
    die("Errore DB: " . $e->getMessage());
}

// Verifica se il modulo di registrazione è stato inviato
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codice_fiscale = $_POST['Codice-Fiscale'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $ruolo = 'utente'; // Ruolo fisso (solo utenti)

    // Protezione contro SQL Injection
    $codice_fiscale = $conn->real_escape_string($codice_fiscale);
    $username = $conn->real_escape_string($username);
    $password = $conn->real_escape_string($password);

    // Query per inserire i dati nel database (ruolo fisso utente)
    $sql = "INSERT INTO Utenti (cf, ruolo, username, Password_Utente) 
            VALUES ('$codice_fiscale', '$ruolo', '$username', '$password')";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Registrazione completata con successo'); window.location.href='login.php';</script>";
        exit();
    } else {
        echo "<script>alert('Errore durante la registrazione: " . $conn->error . "');</script>";
    }
}
?>

<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="it" lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLAYERBASE</title>
    <link rel="stylesheet" href="styles/style_registrazione.css">
</head>
<body>
    <header>
        <div class='logo-container'>
            <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo">
        </div>
        <h1>REGISTRAZIONE PLAYERBASE</h1>
    </header>
    
    <div class="Reg-container">
        <form action="registrazione.php" method="POST" class="reg-form">
            <h2>Registra il tuo account</h2>

            <label for="Codice-Fiscale">CF:</label>
            <input type="text" id="Codice-Fiscale" name="Codice-Fiscale" maxlength="16" required />

            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required />

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required />

            <input type="submit" value="Registrati" />
        </form>
    </div>
    
    <footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
    </footer>
</body>
</html>