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
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="it" lang="it">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PLAYERBASE</title>
    <link rel="stylesheet" href="styles/style_abous.css" />
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

    <main class="content">
        <h1>Chi Siamo</h1>

        <div class="about-section">
            <p>
                <strong>PlayerBase</strong> è una piattaforma web dedicata agli appassionati della squadra A.S. Roma,
                che integra la gestione sportiva con quella commerciale. Il sito è pensato per offrire un'esperienza
                completa e interattiva, sia per i visitatori occasionali che per gli utenti registrati, gestori e amministratori.
            </p>
        </div>

        <div class="features-section">
            <h2>Cosa Trovi nel Sito</h2>
            <ul>
                <li><strong>Catalogo Maglie:</strong> Visualizza e acquista maglie ufficiali, retrò e personalizzate della A.S. Roma. Ogni maglia è descritta con immagini, dettagli e possibilità di personalizzazione.</li>
                <li><strong>Statistiche Giocatori:</strong> Consulta le performance dei giocatori suddivisi per ruolo (attaccanti, centrocampisti, difensori, portieri).</li>
                <li><strong>Classifica Marcatori:</strong> Visualizza la classifica aggiornata e scaricabile in formato PDF o CSV.</li>
                <li><strong>FAQ e Discussioni:</strong> Partecipa a dibattiti, poni domande e condividi opinioni con altri utenti. I contributi possono essere valutati per utilità e supporto.</li>
                <li><strong>Reputazione Utente:</strong> Ogni utente ha un punteggio reputazionale basato sulle interazioni e giudizi ricevuti, che può influenzare sconti e bonus.</li>
                <li><strong>Sconti e Bonus:</strong> Accedi a promozioni personalizzate in base alla tua attività, fedeltà, reputazione e tipo di maglia acquistata.</li>
                <li><strong>Gestione Crediti:</strong> Richiedi crediti da spendere sul sito, con approvazione da parte dell'amministratore.</li>
                <li><strong>Storico Acquisti:</strong> Consulta e stampa i tuoi ordini, gestisci il carrello e monitora le transazioni.</li>
            </ul>
        </div>
    </main>
<footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti | policy | privacy</a>
    </footer>
</body>
</html>
