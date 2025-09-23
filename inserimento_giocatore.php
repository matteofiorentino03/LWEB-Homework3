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
$ruoloUtente = $_SESSION['Ruolo'];
$id_utente = $_SESSION['ID_Utente'] ?? null;
$homepage_link = ($ruoloUtente === 'admin') ? 'homepage_admin.php' : 'homepage_user.php';

$errore = "";
$successo = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cf = trim($_POST['CF']);
    $nome = trim($_POST['Nome']);
    $cognome = trim($_POST['Cognome']);
    $altezza = (float)$_POST['Altezza'];
    $nazionalita = trim($_POST['Nazionalita']);
    $num_maglia = (int)$_POST['Num_Maglia'];
    $data_nascita = $_POST['DataNascita'];
    $market_value = (float)$_POST['Market_Value'];
    $presenze_stagionali = (int)$_POST['PresenzeStagionali'];
    $data_inizio = $_POST['Data_Inizio'];
    $tipo_contratto = $_POST['Tipo_Contratto'];
    $scadenza = $_POST['Scadenza'];
    $stipendio = (float)$_POST['Stipendio'];
    $ruolo = $_POST['Ruolo'];

    if (!$id_utente) {
        $errore = "Utente non identificato. Eseguire nuovamente il login.";
    }

    $oggi = date('Y-m-d');
    if ($data_nascita >= $oggi) $errore .= "La data di nascita deve essere precedente a oggi.<br>";
    if ($altezza < 1.31) $errore .= "Altezza minima 1.31m.<br>";
    if ($num_maglia < 1 || $num_maglia > 99) $errore .= "Numero maglia tra 1 e 99.<br>";

    // === XML: Controllo se numero maglia già presente ===
    $xmlFile = __DIR__ . '/xml/giocatori.xml';
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->load($xmlFile);

    $giocatori = $dom->getElementsByTagName("giocatore");
    foreach ($giocatori as $g) {
        $nMaglia = $g->getElementsByTagName("num_maglia")[0]->nodeValue ?? "";
        if ((int)$nMaglia === $num_maglia) {
            $errore .= "Numero di maglia già utilizzato.<br>";
            break;
        }
    }

    if ($market_value < 0) $errore .= "Market value non può essere negativo.<br>";
    if ($presenze_stagionali < 0) $errore .= "Presenze stagionali non possono essere negative.<br>";
    if ($stipendio < 0) $errore .= "Stipendio non può essere negativo.<br>";

    // === Generazione cod_contratto ===
    $iniziale_nome = strtoupper(substr($nome, 0, 1));
    $iniziale_cognome = strtoupper(substr($cognome, 0, 1));
    $meseanno = date('my', strtotime($data_inizio));
    $cod_contratto = $iniziale_cognome . $iniziale_nome . $meseanno;

    if ($errore === "") {
        // Calcola nuovo ID
        $maxID = 0;
        foreach ($giocatori as $g) {
            $idVal = $g->getElementsByTagName("ID")[0]->nodeValue ?? 0;
            if ((int)$idVal > $maxID) $maxID = (int)$idVal;
        }
        $newID = $maxID + 1;

        // Crea nuovo nodo <giocatore>
        $root = $dom->documentElement;
        $new = $dom->createElement("giocatore");

        $new->appendChild($dom->createElement("ID", $newID));
        $new->appendChild($dom->createElement("cf", $cf));
        $new->appendChild($dom->createElement("nome", $nome));
        $new->appendChild($dom->createElement("cognome", $cognome));
        $new->appendChild($dom->createElement("nazionalita", $nazionalita));
        $new->appendChild($dom->createElement("datanascita", $data_nascita));
        $new->appendChild($dom->createElement("num_maglia", $num_maglia));
        $new->appendChild($dom->createElement("altezza", $altezza));
        $new->appendChild($dom->createElement("market_value", $market_value));
        $new->appendChild($dom->createElement("presenze", $presenze_stagionali));
        $new->appendChild($dom->createElement("cod_contratto", $cod_contratto));
        $new->appendChild($dom->createElement("Tipo_Contratto", $tipo_contratto));
        $new->appendChild($dom->createElement("stipendio", $stipendio));
        $new->appendChild($dom->createElement("Data_inizio", $data_inizio));
        $new->appendChild($dom->createElement("Data_scadenza", $scadenza));
        $new->appendChild($dom->createElement("ID_utenti", $id_utente));

        $root->appendChild($new);
        $dom->save($xmlFile);

        echo "<script>
                alert('Giocatore inserito correttamente!');
                window.location.href = 'inserisci_" . strtolower($ruolo) . ".php?id=$newID';
              </script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="it" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8" />
    <title>Inserimento Giocatore</title>
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
        <h2>Inserisci un nuovo Giocatore</h2>
        <?php if ($errore !== "") echo "<p style='color:red;'>$errore</p>"; ?>
        <form method="post">
            <input type="text" name="CF" placeholder="Codice Fiscale" required /><br><br>
            <input type="text" name="Nome" placeholder="Nome" required /><br><br>
            <input type="text" name="Cognome" placeholder="Cognome" required /><br><br>
            <input type="number" step="0.01" name="Altezza" placeholder="Altezza in metri" required /><br><br>
            <input type="text" name="Nazionalita" placeholder="Nazionalità" required /><br><br>
            <input type="number" name="Num_Maglia" placeholder="Numero Maglia" required /><br><br>
            <label for="DataNascita"><b>Data di Nascita:</b></label><br>
            <input type="date" name="DataNascita" required /><br><br>
            <input type="number" step="0.01" name="Market_Value" placeholder="Valore di Mercato" required /><br><br>
            <input type="number" name="PresenzeStagionali" placeholder="Presenze Stagionali" required /><br><br>
            <label for="Data_Inizio"><b>Data di Inizio del Contratto:</b></label><br>
            <input type="date" name="Data_Inizio" required /><br><br>
            <label for="Tipo_Contratto"><b>Tipo di Contratto:</b></label><br>
            <select name="Tipo_Contratto" required>
                <option value="TRASFERIMENTO TEMPORANEO">Trasferimento Temporaneo</option>
                <option value="TRASFERIMENTO DEFINITIVO">Trasferimento Definitivo</option>
                <option value="PROMOSSO DALLA PRIMAVERA">Promosso dalla Primavera</option>
                <option value="RINNOVATO">Rinnovato</option>
            </select><br><br>
            <label for="Scadenza"><b>Scadenza del Contratto:</b></label><br>
            <input type="date" name="Scadenza" required /><br><br>
            <input type="number" step="0.01" name="Stipendio" placeholder="Stipendio" required /><br><br>
            <label for="Ruolo"><b>Ruolo:</b></label><br>
            <select name="Ruolo" required>
                <option value="Portiere">Portiere</option>
                <option value="Difensore">Difensore</option>
                <option value="Centrocampista">Centrocampista</option>
                <option value="Attaccante">Attaccante</option>
            </select><br><br>
            <button type="submit">Inserisci Giocatore</button>
        </form>
    </div>
</div>

<footer>
    <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
</footer>
</body>
</html>