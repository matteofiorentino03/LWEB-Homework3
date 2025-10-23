<?php
session_start();
require_once __DIR__ . '/connect.php';

$errore = "";

try {
    $conn = db(); //  connessione corretta al DB selezionato
} catch (Throwable $e) {
    die("Errore DB: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errore = "Inserisci username e password.";
    } else {
        $stmt = $conn->prepare("SELECT ID, ruolo, status, Password_Utente FROM Utenti WHERE username = ? AND Password_Utente = ?");
        if (!$stmt) die("Errore prepare: " . $conn->error);

        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();

            if (strtolower($row['status']) !== 'attivo') {
                if (strtolower($row['status']) === 'bannato')
                    $errore = "Account Bannato. Contatta l'amministratore.";
                else
                    $errore = "Account non attivo (stato: {$row['status']}).";
            } else {
                session_regenerate_id(true);
                $_SESSION['Username']   = $username;
                $_SESSION['Ruolo']      = ucfirst(strtolower($row['ruolo']));
                $_SESSION['ID_Utente']  = (int)$row['ID'];

                switch ($_SESSION['Ruolo']) {
                    case 'Amministratore': header("Location: homepage_admin.php"); break;
                    case 'Gestore': header("Location: homepage_gestore.php"); break;
                    default: header("Location: homepage_user.php");
                }
                exit;
            }
        } else {
            $errore = "Credenziali non valide. Riprova.";
        }
        $stmt->close();
    }
}
?>

<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="it" lang="it">
<head>
    <meta charset="utf-8" />
    <title>PLAYERBASE</title>
    <link rel="stylesheet" href="styles/style_login.css" />
</head>
<body>
    <header>
        <div class="logo-container">
            <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo" />
        </div>
        <h1>LOGIN PLAYERBASE</h1>
    </header>

    <div class="login-container">
        <form action="login.php" method="POST">
            <h2>Accedi al tuo account</h2>
            <?php if ($errore): ?>
                <p style="color:red;font-weight:bold;"><?= $errore ?></p>
            <?php endif; ?>

            <label for="username">Username:</label><br />
            <input type="text" id="username" name="username" required /><br /><br />

            <label for="password">Password:</label><br />
            <input type="password" id="password" name="password" required /><br /><br />

            <p>Non hai un account? <a href="registrazione.php">Registrati qui</a></p><br />
            <input type="submit" value="Accedi" />
        </form>
    </div>

<footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>
</body>
</html>
