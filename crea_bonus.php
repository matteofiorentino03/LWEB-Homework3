<?php
session_start();

// logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

// Controllo accesso gestore
if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo']) || strtolower($_SESSION['Ruolo']) !== 'gestore') {
    header("Location: entering.html");
    exit();
}

// Percorsi file XML
$bonusFile = "xml/bonus.xml";
$homepage_link = "homepage_gestore.php";
$bonusSelezionato = $_POST['select_bonus'] ?? "";

// Funzione salvataggio XML
function salvaXML(DOMDocument $dom, string $path): void {
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->save($path);
}

$errore = "";
$successo = "";

if (isset($_POST['invio'])) {

    // 🔸 BONUS MAGLIA RETRO
    if ($bonusSelezionato === "MagliaRetro") {

        $xmlBonus = new DOMDocument();
        $xmlBonus->load($bonusFile);
        $xpathBonus = new DOMXPath($xmlBonus);

        // Calcola nuovo ID
        $premi = $xpathBonus->query("//premio");
        $ID = $premi->length + 1;

        // Dati da form
        $descrizione = trim($_POST['descrizione_bonus']);
        $crediti = trim($_POST['crediti_bonus']);
        $stagione = trim($_POST['stagione'] ?? "");
        $attivo = $_POST['attivo'] ?? "";

        // Validazione
        // Validazione
if ($attivo == "") {
    $errore = "Seleziona lo stato di attivazione prima di procedere.";
} elseif ($descrizione === "" || $crediti === "") {
    $errore = "Compila tutti i campi obbligatori.";
} else {
    // Verifica se esiste già un bonus MagliaRetro senza stagione
    $bonusSenzaStagione = false;
    $bonusStagioneDuplicata = false;

    $premiMagliaRetro = $xpathBonus->query("//premio[tipo='MagliaRetro']");
    foreach ($premiMagliaRetro as $premio) {
        $stagioneNode = $premio->getElementsByTagName("stagione")->item(0);
        $stagioneEsistente = $stagioneNode ? trim($stagioneNode->nodeValue) : "";

        if ($stagioneEsistente === "") {
            $bonusSenzaStagione = true;
        }

        if ($stagione !== "" && $stagioneEsistente === $stagione) {
            $bonusStagioneDuplicata = true;
        }
    }
    if ($stagione === "" && $bonusSenzaStagione) {
        $errore = "Esiste già un bonus MagliaRetro senza stagione. Specifica una stagione per evitare conflitti.";
    } elseif ($stagione !== "" && $bonusStagioneDuplicata) {
        $errore = "Esiste già un bonus MagliaRetro per la stagione selezionata.";
    } else {
        // Inserimento nel file XML
        $root = $xmlBonus->getElementsByTagName("bonus")->item(0);
        $nuovoPremio = $xmlBonus->createElement("premio");

        $nuovoPremio->appendChild($xmlBonus->createElement("ID", $ID));
        $nuovoPremio->appendChild($xmlBonus->createElement("tipo", "MagliaRetro"));
        $nuovoPremio->appendChild($xmlBonus->createElement("descrizione", $descrizione));
        $nuovoPremio->appendChild($xmlBonus->createElement("crediti", number_format((float)$crediti, 2, '.', '')));
        if ($stagione !== "") {
            $nuovoPremio->appendChild($xmlBonus->createElement("stagione", $stagione));
        }
        $nuovoPremio->appendChild($xmlBonus->createElement("attivo", $attivo));

        $root->appendChild($nuovoPremio);
        salvaXML($xmlBonus, $bonusFile);

        $successo = "Bonus Maglia Retro salvato correttamente!";
    }
}


    // 🔸 BONUS REPUTAZIONE
    } elseif ($bonusSelezionato === "Reputazione") {

        $xmlBonus = new DOMDocument();
        $xmlBonus->load($bonusFile);
        $xpathBonus = new DOMXPath($xmlBonus);

        // Calcola nuovo ID
        $premi = $xpathBonus->query("//premio");
        $ID = $premi->length + 1;

        // Dati da form
        $descrizione = trim($_POST['descrizione_bonus']);
        $crediti = trim($_POST['crediti_bonus']);
        $data_iscrizione = trim($_POST['data_iscrizione'] ?? "");
        $attivo = $_POST['attivo'] ?? "";
        $limiti_utilizzi = isset($_POST['limiti_utilizzi']) && $_POST['limiti_utilizzi'] !== "" ? $_POST['limiti_utilizzi'] : -1;
        $reputazione_minima = isset($_POST['reputazione_minima']) && $_POST['reputazione_minima'] !== "" ? $_POST['reputazione_minima'] : -1;

        // Validazione
        if ($attivo == "") {
            $errore = "Seleziona lo stato di attivazione prima di procedere.";
        } elseif ($descrizione === "" || $crediti === "" || $data_iscrizione === "") {
            $errore = "Compila tutti i campi obbligatori.";
        } else {
            $duplicato = false;
            $premi_esistenti = $xpathBonus->query("//premio[tipo='Reputazione']");

            foreach ($premi_esistenti as $premio) {
                $data_exist = $premio->getElementsByTagName("data_iscrizione_minima")[0]->nodeValue ?? '';
                $reput_exist = $premio->getElementsByTagName("reputazione_minima")[0]->nodeValue ?? '';

                if ($data_exist === $data_iscrizione && $reput_exist == $reputazione_minima) {
                    $duplicato = true;
                    break;
                }
            }

            if ($duplicato) {
                $errore = "Esiste già un bonus Reputazione con la stessa data di iscrizione e stessa reputazione minima.";
            } else {
                // Inserimento nel file XML
                $root = $xmlBonus->getElementsByTagName("bonus")->item(0);
                $nuovoPremio = $xmlBonus->createElement("premio");

                $nuovoPremio->appendChild($xmlBonus->createElement("ID", $ID));
                $nuovoPremio->appendChild($xmlBonus->createElement("tipo", "Reputazione"));
                $nuovoPremio->appendChild($xmlBonus->createElement("descrizione", $descrizione));
                $nuovoPremio->appendChild($xmlBonus->createElement("crediti", number_format((float)$crediti, 2, '.', '')));
                $nuovoPremio->appendChild($xmlBonus->createElement("data_inizio", $data_iscrizione));
                $nuovoPremio->appendChild($xmlBonus->createElement("limite_utilizzi", (int)$limiti_utilizzi));
                $nuovoPremio->appendChild($xmlBonus->createElement("reputazioneMinima", (int)$reputazione_minima));
                $nuovoPremio->appendChild($xmlBonus->createElement("attivo", $attivo));

                $root->appendChild($nuovoPremio);
                salvaXML($xmlBonus, $bonusFile);

                $successo = "Bonus Reputazione salvato correttamente!";
            }
        }
    } 
} 
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Immissione bonus</title>
  <link rel="stylesheet" href="styles/style_crea_bonus.css">
</head>
<body>

<header>
  <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
    <div class="logo-container">
      <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo" />
    </div>
  </a>
  <h1><a href="<?= htmlspecialchars($homepage_link) ?>" style="color:inherit;text-decoration:none;">PLAYERBASE</a></h1>
  <div class="utente-container">
      <div class="logout"><a href="?logout=true">Logout</a></div>
  </div>
</header>

<!-- Messaggi -->
<?php if ($errore): ?>
  <div class="message-box error"><?= htmlspecialchars($errore) ?></div>
<?php elseif ($successo): ?>
  <div class="message-box success"><?= htmlspecialchars($successo) ?></div>
<?php endif; ?>

<!-- Selezione Bonus -->
<form method="post" class="card narrow">
  <label class="label" for="select_bonus">Seleziona Bonus:</label>
  <select id="select_bonus" name="select_bonus" class="input" onchange="this.form.submit()">
    <option value="">-- Seleziona --</option>
    <option value="MagliaRetro" <?= $bonusSelezionato === "MagliaRetro" ? 'selected' : '' ?>>MagliaRetro</option>
    <option value="Reputazione" <?= $bonusSelezionato === "Reputazione" ? 'selected' : '' ?>>Reputazione</option>
  </select>
</form>

<?php if ($bonusSelezionato === "MagliaRetro"): ?>
<form method="post" class="card_narrow">
  <h2>Registra il tuo bonus Maglia Retro</h2>

  <input type="hidden" name="select_bonus" value="<?= htmlspecialchars($bonusSelezionato) ?>">

  <label for="descrizione_bonus">Descrizione bonus:</label>
  <input type="text" id="descrizione_bonus" name="descrizione_bonus" required>

  <label for="crediti_bonus">Crediti:</label>
  <input type="number" id="crediti_bonus" name="crediti_bonus" required>

  <?php 
  $domB = new DOMDocument();
  $domB->load($bonusFile);
  $xpath = new DOMXPath($domB);

  // ATTENZIONE: nel tuo XSD il nodo si chiama <stagione>, non <data>
  $nodiBonus1 = $xpath->query("//premio[tipo='MagliaRetro']/stagione");
  $nodiBonus2 = $xpath->query("//premio[tipo='MagliaRetro']");
  $stagioneRichiesta = ($nodiBonus1->length !== $nodiBonus2->length);
  ?>

  <label>Stagioni</label>
   <select name="stagione" id="stagione">
    <option value="">-- Seleziona Stagione --</option>
    <?php 
    for ($anno = 1980; $anno <= 2025; $anno++) {
        $stagione = $anno . "/" . str_pad(($anno+1)%100, 2, "0", STR_PAD_LEFT);
        echo "<option value=\"$stagione\">$stagione</option>";
    }
    ?>
    <option value="">Tutte</option>
  </select>

  <label for="attivo">Attivazione</label>
  <select name="attivo" id="attivo">
    <option value="">-- SELEZIONA --</option>
    <option value="true">Attivo (True)</option>
    <option value="false">Disattivo (False)</option>
  </select>

  <input type="submit" value="Salva" name="invio">
</form>
<?php endif; ?>

<?php if ($bonusSelezionato === "Reputazione"): ?>
<form method="post" class="card_narrow">

<input type="hidden" name="select_bonus" value="<?= htmlspecialchars($bonusSelezionato) ?>">

  <h2>Registra il tuo bonus Reputazione</h2>

  <label for="descrizione_bonus">Descrizione bonus:</label>
  <input type="text" id="descrizione_bonus" name="descrizione_bonus" required>

  <label for="crediti_bonus">Crediti:</label>
  <input type="number" id="crediti_bonus" name="crediti_bonus" required>

  <label for="data_iscrizione">Data iscrizione minima</label>
  <input type="date" id="data_iscrizione" name="data_iscrizione" required>

  <label for="limiti_utilizzi">Limite utilizzi</label>
  <input type="number" id="limiti_utilizzi" name="limiti_utilizzi">

  <label for="reputazione_minima">Reputazione minima</label>
  <input type="number" id="reputazione_minima" name="reputazione_minima">

  <label for="attivo">Attivazione</label>
  <select name="attivo" id="attivo">
    <option value="">-- SELEZIONA --</option>
    <option value="true">Attivo (True)</option>
    <option value="false">Disattivo (False)</option>
  </select>

  <input type="submit" value="Salva" name="invio">
</form>
<?php endif; ?>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
  <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
</footer>

</body>
</html>