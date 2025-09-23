<?php
session_start();

// Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: homepage_user.php");
    exit();
}

$isLoggedIn = isset($_SESSION['Username']);
$username = $isLoggedIn ? $_SESSION['Username'] : null;
?>

<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>PLAYERBASE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/style_homepage.css">
</head>
<body>
    <header>
        <a href="homepage_user.php" class="header-link">
            <div class='logo-container'>
                <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo">
            </div>
        </a>
        <h1><a href="homepage_user.php" style="color: inherit; text-decoration: none;">PLAYERBASE</a></h1>
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

    <div class="testo-iniziale">
        <?php if ($isLoggedIn): ?>
            <h2>Benvenuto su Playerbase, <?php echo htmlspecialchars($username); ?>!</h2>
        <?php else: ?>
            <h2>Benvenuto su Playerbase!</h2>
        <?php endif; ?>
        <br />
    </div>

    <div class="main-container">
        <div class="slideshow-container">
            <div class="mySlides fade"><img src="img/squadra1.png" alt="Foto 1"></div>
            <div class="mySlides fade"><img src="img/ranieri.png" alt="Foto 2"></div>
            <div class="mySlides fade"><img src="img/totti_selfie.png" alt="Foto 3"></div>
            <div class="mySlides fade"><img src="img/ddr.png" alt="Foto 4"></div>
            <div class="mySlides fade"><img src="img/dybala_pellegrini.png" alt="Foto 5"></div>
            <div class="mySlides fade"><img src="img/ddr_totti.png" alt="Foto 6"></div>
            <div class="mySlides fade"><img src="img/ddr_totti_2006.png" alt="Foto 7"></div>
            <div class="mySlides fade"><img src="img/jose_conf.png" alt="Foto 8"></div>
        </div>

        <div class="table">
            <p>Scegli una tabella per visualizzare o modificare i dati:</p>
            <ul class="table-list">
                <li><a href="tabella_giocatore.php">Visualizza tutti i Giocatori di questa stagione</a></li>
                <li><a href="visualizzazione_classifica_marcatori.php">Visualizzazione della classifica dei marcatori</a></li>
                <li><a href="catalogo_maglie.php">Catalogo delle maglie</a></li>
                <?php if (isset($isLoggedIn) && $isLoggedIn == true): ?>
                <li><a href="storico_acquisti_utente.php">Visualizzazione dello storico degli acquisti effettuati</a></li>
                <li><a href="modifica_info_utente.php">Modificare le informazioni personali</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
    </footer>

    <script>
        let slideIndex = 0;
        showSlides();

        function showSlides() {
            let slides = document.getElementsByClassName("mySlides");
            for (let i = 0; i < slides.length; i++) {
                slides[i].style.display = "none";  
            }
            slideIndex++;
            if (slideIndex > slides.length) {slideIndex = 1}    
            slides[slideIndex-1].style.display = "block";  
            setTimeout(showSlides, 6000);
        }
    </script>
</body>
</html>
