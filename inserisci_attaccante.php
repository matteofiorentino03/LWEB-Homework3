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
    $gol_fatti = (int)$_POST['Gol_Fatti'];
    $assist = (int)$_POST['Assist'];
    $ammonizioni = (int)$_POST['Ammonizioni'];
    $espulsioni = (int)$_POST['Espulsioni'];
    $ruolo_attaccante = $_POST['RuoloAttaccante'];

    foreach ([$gol_fatti, $assist, $ammonizioni, $espulsioni] as $val) {
        if ($val < 0) {
            $errore = "I valori numerici non possono essere negativi.";
            break;
        }
    }

    if ($errore === "") {
        $xmlFile = __DIR__ . '/xml/attaccanti.xml';

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (file_exists($xmlFile)) {
            $dom->load($xmlFile);
        } else {
            $root = $dom->createElement("attaccanti");
            $dom->appendChild($root);
        }

        $root = $dom->documentElement;

        $attaccante = $dom->createElement("attaccante");

        $attaccante->appendChild($dom->createElement("ID_giocatore", $id_giocatore));
        $attaccante->appendChild($dom->createElement("gol_fatti", $gol_fatti));
        $attaccante->appendChild($dom->createElement("assist", $assist));
        $attaccante->appendChild($dom->createElement("ammonizioni", $ammonizioni));
        $attaccante->appendChild($dom->createElement("espulsioni", $espulsioni));
        $attaccante->appendChild($dom->createElement("ruolo", $ruolo_attaccante));

        $root->appendChild($attaccante);
        $dom->save($xmlFile);

        $successo = "Dati dell'attaccante inseriti correttamente.";
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
    <title>Inserimento Attaccante</title>
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
            <h2>Completamento dati per il ruolo: Attaccante</h2>
            <?php if ($errore) echo "<p style='color:red;'>$errore</p>"; ?>
            <?php if ($successo) echo "<p style='color:green;'>$successo</p>"; ?>
            <form method="post">
                <input type="number" name="Gol_Fatti" placeholder="Gol Fatti" required /><br><br>
                <input type="number" name="Assist" placeholder="Assist" required /><br><br>
                <input type="number" name="Ammonizioni" placeholder="Ammonizioni" required /><br><br>
                <input type="number" name="Espulsioni" placeholder="Espulsioni" required /><br><br>
                <label for="RuoloAttaccante"><b>Ruolo Attaccante:</b></label><br>
                <select name="RuoloAttaccante" required>
                    <option value="punta">Punta</option>
                    <option value="seconda punta">Seconda Punta</option>
                    <option value="ala">Ala</option>
                    <option value="falso 9">Falso 9</option>
                </select><br><br>
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
