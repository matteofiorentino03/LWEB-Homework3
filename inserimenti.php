<?php
// Avvia la sessione
session_start();

/* ==========================
    Logout
========================== */
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

/* ==========================
    Controllo Login
========================== */
if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo'])) {
    header("Location: entering.html");
    exit();
}

/* ==========================
    Controllo Ruolo: SOLO GESTORE
========================== */
$ruolo = strtolower($_SESSION['Ruolo']);
if ($ruolo !== 'gestore') {
    header("Location: entering.html");
    exit();
}

/* ==========================
    Dati Utente + Homepage
========================== */
$username = $_SESSION['Username'];
$homepage_link = 'homepage_gestore.php';
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
    <!--  Logo e titolo portano alla homepage del Gestore -->
    <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
        <div class="logo-container">
            <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo" />
        </div>
    </a>
    <h1>
        <a href="<?= htmlspecialchars($homepage_link) ?>" style="color: inherit; text-decoration: none;">
            PLAYERBASE
        </a>
    </h1>
    <div class="utente-container">
        <div class="logout">
            <a href="?logout=true"><p>Logout</p></a>
        </div>
    </div>
</header>

<div class="testo-iniziale">
    <h2>Benvenuto <?= htmlspecialchars($username) ?>!</h2>
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
    <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
</footer>
</body>
</html>