<?php
session_start();

/* ==========================
    Controllo Accesso
========================== */
if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo'])) {
    header("Location: entering.html");
    exit();
}

if (strtolower($_SESSION['Ruolo']) !== 'gestore') {
    // Solo i gestori possono accedere
    header("Location: entering.html");
    exit();
}

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
   VARIABILI BASE
========================== */
$ruoloUtente = $_SESSION['Ruolo'];
$username = $_SESSION['Username'];
$homepage_link = 'homepage_gestore.php';
$id_giocatore = $_GET['id'] ?? null;
$errore = "";
$successo = "";

if (!$id_giocatore) {
    die("ID giocatore mancante.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gol_subiti = (int)$_POST['Gol_Subiti'];
    $gol_fatti = (int)$_POST['Gol_Fatti'];
    $assist = (int)$_POST['Assist'];
    $clean_sheet = (int)$_POST['Cleansheet'];
    $ammonizioni = (int)$_POST['Ammonizioni'];
    $espulsioni = (int)$_POST['Espulsioni'];

    foreach ([$gol_subiti, $gol_fatti, $assist, $clean_sheet, $ammonizioni, $espulsioni] as $val) {
        if ($val < 0) {
            $errore = "I valori numerici non possono essere negativi.";
            break;
        }
    }

    if ($errore === "") {
        $xmlFile = __DIR__ . '/xml/portieri.xml';
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (file_exists($xmlFile)) {
            $dom->load($xmlFile);
        } else {
            $root = $dom->createElement("portieri");
            $dom->appendChild($root);
        }

        $root = $dom->documentElement;

        $portiere = $dom->createElement("portiere");
        $portiere->appendChild($dom->createElement("ID_giocatore", $id_giocatore));
        $portiere->appendChild($dom->createElement("gol_subiti", $gol_subiti));
        $portiere->appendChild($dom->createElement("gol_fatti", $gol_fatti));
        $portiere->appendChild($dom->createElement("assist", $assist));
        $portiere->appendChild($dom->createElement("clean_sheet", $clean_sheet));
        $portiere->appendChild($dom->createElement("ammonizioni", $ammonizioni));
        $portiere->appendChild($dom->createElement("espulsioni", $espulsioni));

        $root->appendChild($portiere);
        $dom->save($xmlFile);

        $successo = "Dati del portiere inseriti correttamente.";
    }
    echo "<script>
                alert('Giocatore inserito con successo!');
                window.location.href = 'homepage_gestore.php';
              </script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="it" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8" />
    <title>Inserimento Portiere</title>
    <link rel="stylesheet" href="styles/style_inserimenti_g.css" />
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
            <a href="?logout=true">Logout</a>
        </div>
    </div>
</header>

<div class="main-container">
    <div class="table">
        <h2>Inserisci dati Portiere</h2>
        <?php if ($errore) echo "<p style='color:red;'>$errore</p>"; ?>
        <?php if ($successo) echo "<p style='color:green;'>$successo</p>"; ?>
        <form method="post">
            <input type="number" name="Gol_Subiti" placeholder="Gol Subiti" required /><br /><br />
            <input type="number" name="Gol_Fatti" placeholder="Gol Fatti" required /><br /><br />
            <input type="number" name="Assist" placeholder="Assist" required /><br /><br />
            <input type="number" name="Cleansheet" placeholder="Clean Sheet" required /><br /><br />
            <input type="number" name="Ammonizioni" placeholder="Ammonizioni" required /><br /><br />
            <input type="number" name="Espulsioni" placeholder="Espulsioni" required /><br /><br />
            <button type="submit">Conferma</button>
        </form>
    </div>
</div>

<footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>
</body>
</html>
