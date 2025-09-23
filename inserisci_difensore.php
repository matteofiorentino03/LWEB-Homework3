<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: entering.html");
    exit();
}

$ruolo = $_SESSION['Ruolo'];
$homepage_link = ($ruolo === 'admin') ? 'homepage_admin.php' : 'homepage_user.php';

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
    $ruolo_dif = $_POST['RuoloDifensore'];

    foreach ([$gol_fatti, $assist, $ammonizioni, $espulsioni] as $val) {
        if ($val < 0) {
            $errore = "I valori numerici non possono essere negativi.";
            break;
        }
    }

    if ($errore === "") {
        $xmlFile = __DIR__ . '/xml/difensori.xml';
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (file_exists($xmlFile)) {
            $dom->load($xmlFile);
        } else {
            $root = $dom->createElement("difensori");
            $dom->appendChild($root);
        }

        $root = $dom->documentElement;

        $difensore = $dom->createElement("difensore");
        $difensore->appendChild($dom->createElement("ID_giocatore", $id_giocatore));
        $difensore->appendChild($dom->createElement("gol_fatti", $gol_fatti));
        $difensore->appendChild($dom->createElement("assist", $assist));
        $difensore->appendChild($dom->createElement("ammonizioni", $ammonizioni));
        $difensore->appendChild($dom->createElement("espulsioni", $espulsioni));
        $difensore->appendChild($dom->createElement("ruolo", $ruolo_dif));

        $root->appendChild($difensore);
        $dom->save($xmlFile);

        $successo = "Dati del difensore inseriti correttamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="it" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8" />
    <title>Inserimento Difensore</title>
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
        <h2>Inserisci dati Difensore</h2>
        <?php if ($errore) echo "<p style='color:red;'>$errore</p>"; ?>
        <?php if ($successo) echo "<p style='color:green;'>$successo</p>"; ?>
        <form method="post">
            <input type="number" name="Gol_Fatti" placeholder="Gol Fatti" required /><br /><br />
            <input type="number" name="Assist" placeholder="Assist" required /><br /><br />
            <input type="number" name="Ammonizioni" placeholder="Ammonizioni" required /><br /><br />
            <input type="number" name="Espulsioni" placeholder="Espulsioni" required /><br /><br />

            <label for="RuoloDifensore"><b>Ruolo:</b></label><br />
            <select name="RuoloDifensore" required>
                <option value="centrale">Centrale</option>
                <option value="terzino">Terzino</option>
                <option value="braccetto">Braccetto</option>
            </select><br /><br />

            <button type="submit">Conferma</button>
        </form>
    </div>
</div>

<footer>
    <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
</footer>
</body>
</html>