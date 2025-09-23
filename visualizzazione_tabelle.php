<?php
session_start();

// Se è stato cliccato logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: homepage_user.php");
    exit();
}

// Verifica se loggato
$isLoggedIn = isset($_SESSION['Username']);
$username = $isLoggedIn ? $_SESSION['Username'] : null;
$ruolo = $isLoggedIn && isset($_SESSION['Ruolo']) ? $_SESSION['Ruolo'] : null;

// Link alla homepage
$homepage_link = ($ruolo === 'admin') ? 'homepage_admin.php' : 'homepage_user.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>Visualizzazione Tabelle</title>
    <link rel="stylesheet" href="styles/style_inserimenti.css" />
</head>
<body>
<header>
    <a href="<?php echo $homepage_link; ?>" class="header-link">
        <div class="logo-container">
            <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo" />
        </div>
    </a>
    <h1>
        <a href="<?php echo $homepage_link; ?>" style="color: inherit; text-decoration: none;">PLAYERBASE</a>
    </h1>

    <div class="utente-container">
            <div class="logout">
                <?php if ($isLoggedIn): ?>
                    <a href="?logout=true"><p>Logout</p></a>
                <?php else: ?>
                    <a href="entering.html"><p>Login / Registrati</p></a>
                <?php endif; ?>
            </div>
    </div>
</header>

<div class="main-container">
    <div class="table">
        <h2>Visualizza i dati delle Tabelle</h2>
        <p>Seleziona la tabella da visualizzare:</p>
        <ul class="table-list">
            <li><a href="tabella_giocatore.php">Visualizza tutti i Giocatori di questa stagione</a></li>
            <li><a href="tabella_maglia.php">Visualizza tutte le Maglie</a></li>
        </ul>
    </div>
</div>

<footer>
    <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
</footer>
</body>
</html>