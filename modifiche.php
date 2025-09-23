<?php
session_start();

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

if (!isset($_SESSION['Username'])) {
    header("Location: entering.html");
    exit();
}

$username = $_SESSION['Username'];
$ruolo = $_SESSION['Ruolo'];
$homepage_link = ($ruolo === 'admin') ? 'homepage_admin.php' : 'homepage_user.php';
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
    <div class="table">
        <h2>Benvenuto <?php echo htmlspecialchars($username); ?>!</h2>
        <p>Seleziona il tipo di record da modificare:</p>
        <ul class="table-list">
            <li><a href="modifica_giocatore.php">Modifica un giocatore</a></li>
            <li><a href="modifica_maglia.php">Modifica una maglia</a></li>
            <li><a href="modifica_utente.php">Modifica un utente</a></li> 
        </ul>
    </div>
</div>

<footer>
    <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
</footer>
</body>
</html>