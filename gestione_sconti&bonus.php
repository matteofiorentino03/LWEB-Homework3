<?php
session_start();

/* ==========================
    Controllo Accesso
========================== */
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo'])) {
    header("Location: entering.html");
    exit();
}

// Solo il Gestore può accedere
if (strtolower($_SESSION['Ruolo']) !== 'gestore') {
    header("Location: entering.html");
    exit();
}

/* ==========================
   VARIABILI DI SESSIONE
========================== */
$username = $_SESSION['Username'];
$ruolo = $_SESSION['Ruolo'];
$homepage_link = 'homepage_gestore.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>MENU MODIFICHE</title>
    <link rel="stylesheet" href="styles/style_inserimenti.css" />
</head>
<body>
<header>
    <a href="<?php echo $homepage_link; ?>" class="header-link">
        <div class="logo-container">
            <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo" />
        </div>
    </a>
    <h1><a href="<?php echo $homepage_link; ?>" style="color: inherit; text-decoration: none;">PLAYERBASE</a></h1>
    <div class="utente-container">
        <div class="logout">
            <a href="?logout=true"><p>Logout</p></a>
        </div>
    </div>
</header>

<div class="main-container">
    <div class="table" >
        <h2 style="flex:1;text-align:center;">Benvenuto <?php echo htmlspecialchars($username); ?>!</h2>
        <p></p>
        <ul class="table-list">
            <li><a href="crea_bonus.php">Crea un bonus</a></li>
            <li><a href="crea_sconto.php">crea uno sconto</a></li>
            <li><a href="modifica_sconti&bonus.php">Modifica sconto/bonus</a></li>
            <li><a href="elimina_sconti&bonus.php">Elimina sconto/bonus</a></li> 
        </ul>
    </div>
</div>

<footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>
</body>
</html>
