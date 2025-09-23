<?php
// Avvia la sessione
session_start();

// Verifica se l'utente ha cliccato sul link di logout
if (isset($_GET['logout'])) {
    // Distruggi tutte le variabili di sessione
    session_unset();

    // Distruggi la sessione
    session_destroy();

    // Reindirizza alla pagina entering.html
    header("Location: entering.html");
    exit();
}

// Verifica se l'utente è loggato, altrimenti reindirizza alla pagina di login
if (!isset($_SESSION['Username'])) {
    header("Location: entering.html");
    exit();
}
// Recupera il nome utente dalla sessione
$username = $_SESSION['Username'];
$ruolo = $_SESSION['Ruolo'];
$homepage_link = ($ruolo === 'admin') ? 'homepage_admin.php' : 'homepage_user.php';
?>

<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="it" lang="it">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MENU INSERIMENTI</title>
    <link rel="stylesheet" href="styles/style_inserimenti.css" />
</head>
<body>
<header>
        <a href="homepage_admin.php" class="header-link">
            <div class='logo-container'>
                <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo">
            </div>
        </a>
        <h1><a href="homepage_admin.php" style="color: inherit; text-decoration: none;">PLAYERBASE</a></h1> <!-- Cliccando qui si va a homepage.html -->
        <div class="utente-container">
            <div class="logout">
                <a href="?logout=true">
                    <p>Logout</p>
                </a>
            </div>
        </div>
    </header>

    <div class="testo-iniziale">
        <h2>Benvenuto <?php echo htmlspecialchars($username); ?>!</h2>
        <p>Seleziona il tipo di record da inserire nel database:</p>
    </div>

    <div class="main-container">
        <div class="table">
            <p>Seleziona una voce:</p>
            <ul class="table-list">
                <li><a href="inserimento_giocatore.php">Inserisci un Giocatore</a></li>
                <li><a href="inserimento_maglia.php">Inserisci una Maglia</a></li>
            </ul>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
    </footer>
</body>
</html>