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
?>

<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="it" lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLAYERBASE</title>
    <link rel="stylesheet" href="styles/style_homepage.css">
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
        <h2>Benvenuto su Playerbase, <?php echo htmlspecialchars($username); ?>!</h2> <!-- Mostra il nome utente -->
        <br />
    </div>


    <div class="main-container">
    <!-- Slideshow di 4 foto -->
        <!-- Slideshow di 9 foto -->
        <div class="slideshow-container">
            <div class="mySlides fade">
                <img src="img/squadra1.png" alt="Foto 1">
            </div>
            <div class="mySlides fade">
                <img src="img/ranieri.png" alt="Foto 2">
            </div>
            <div class="mySlides fade">
                <img src="img/totti_selfie.png" alt="Foto 3">
            </div>
            <div class="mySlides fade">
                <img src="img/ddr.png" alt="Foto 4">
            </div>
            <div class="mySlides fade">
                <img src="img/dybala_pellegrini.png" alt="Foto 5">
            </div>
            <div class="mySlides fade">
                <img src="img/ddr_totti.png" alt="Foto 6">
            </div>
            <div class="mySlides fade">
                <img src="img/ddr_totti_2006.png" alt="Foto 7">
            </div>
            <div class="mySlides fade">
                <img src="img/jose_conf.png" alt="Foto 8">
            </div>
        </div>
    
        <!-- Sezione tabella -->
        <div class="table">
            <p>Scegli una tabella per visualizzare o modificare i dati:</p>
            <ul class="table-list">
                <li><a href="inserimenti.php">Inserire un nuovo record</a></li>
                <li><a href="modifiche.php">Modificare le informazioni di un record</a></li>
                <li><a href="cancella_giocatore.php">Cancellare un giocatore</a></li>
                <li><a href="visualizzazione_tabelle.php">Visualizzazione delle tabelle</a></li>
                <li><a href="dashboard.php">Visualizza tutti gli Utenti registrati</a></li>
                <li><a href="accettazione_crediti.php">Accettazione richieste dei crediti</a></li>
                <li><a href="storico_acquisti.php">Visualizzazione dello storico degli acquisti effettuati dagli utenti</a></li>
                <li><a href="storico_inserimenti.php">Visualizzazione dello storico degli inserimenti</a></li>
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
            setTimeout(showSlides, 4000); // Cambia immagine ogni 4 secondi
        }
    </script>
</body>
</html>
