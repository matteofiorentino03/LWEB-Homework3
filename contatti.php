<?php
session_start();

/* ==========================
    HOMEPAGE IN BASE AL RUOLO
========================== */
$homepage_link = 'homepage_user.php'; // Default per chi non è loggato

if (isset($_SESSION['Ruolo'])) {
    $ruolo = strtolower($_SESSION['Ruolo']);
    if ($ruolo === 'gestore') {
        $homepage_link = 'homepage_gestore.php';
    } elseif ($ruolo === 'amministratore') {
        $homepage_link = 'homepage_admin.php';
    } else {
        $homepage_link = 'homepage_user.php';
    }
}
?>

<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="it" lang="it">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Contatti & Privacy - PLAYERBASE</title>
    <link rel="stylesheet" href="styles/style_contatti.css" />
</head>
<body>
    <header class="site-header">
        <!--  Logo e titolo portano alla homepage in base al ruolo -->
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
    </header>

    <main class="content">
        <h1>Contatti & Informazioni</h1>

        <div class="contact-info">
            <h2>Contattaci</h2>
            <p>Hai bisogno di assistenza o vuoi inviarci un feedback? Siamo sempre felici di ascoltarti.</p>
            <ul>
                <li><strong>Email:</strong> <a href="mailto:info@playerbase.it">info@playerbase.it</a></li>
                <li><strong>Telefono:</strong> +39 06 1234567</li>
                <li><strong>Indirizzo:</strong> Via dei Campioni, 10 - 00100 Roma (RM)</li>
            </ul>
        </div>

        <div class="policy-info">
            <h2>Policy & Condizioni d'Uso</h2>
            <p>
                L'accesso e l'utilizzo del sito <strong>PlayerBase</strong> implicano l'accettazione delle nostre condizioni
                d'uso. Tutti i contenuti presenti (testi, immagini, marchi e loghi) sono di proprietà dei rispettivi titolari
                e non possono essere riprodotti senza autorizzazione.
            </p>
            <p>
                PlayerBase si impegna a garantire la correttezza delle informazioni pubblicate ma non può essere ritenuto
                responsabile di eventuali errori o interruzioni del servizio.
            </p>
        </div>

        <div class="privacy-info">
            <h2>Informativa sulla Privacy (GDPR)</h2>
            <p>
                Ai sensi del Regolamento (UE) 2016/679 (GDPR), informiamo gli utenti che i dati personali forniti
                volontariamente tramite registrazione o interazione sul sito sono trattati in modo lecito, corretto e trasparente.
            </p>
            <ul>
                <li><strong>Titolare del trattamento:</strong> PlayerBase S.r.l.</li>
                <li><strong>Email per privacy:</strong> <a href="mailto:privacy@playerbase.it">privacy@playerbase.it</a></li>
                <li><strong>Finalità:</strong> gestione account utente, acquisti, comunicazioni e attività promozionali personalizzate.</li>
                <li><strong>Diritti dell’utente:</strong> accesso, rettifica, cancellazione, opposizione e portabilità dei dati.</li>
            </ul>
            <p>
                I dati non verranno ceduti a terzi non autorizzati e saranno conservati solo per il tempo necessario
                alle finalità indicate. L’utente può richiedere in qualsiasi momento la cancellazione del proprio account
                o la modifica dei consensi di trattamento.
            </p>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>
</body>
</html>