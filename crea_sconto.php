<?php
session_start();

// Accesso
if (isset($_GET['logout'])) { 
    session_unset(); 
    session_destroy(); 
    header("Location: entering.html"); 
    exit(); 
}
if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo']) || strtolower($_SESSION['Ruolo']) !== 'gestore') { 
    header("Location: entering.html"); 
    exit(); 
}

// File e stato
$scontiFile = "xml/sconti.xml";
$homepage_link = "homepage_gestore.php";
$tipoSelezionato = $_POST['select_sconto'] ?? "";

function salvaXML(DOMDocument $dom, string $path): void {
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->save($path);
}

$errore = "";
$successo = "";


$annoCorrente = (int)date("Y");

// Handle salvataggio
if (isset($_POST['invio'])) {
    $xml = new DOMDocument();
    $xml->load($scontiFile);
    $xpath = new DOMXPath($xml);

    // CONTROLLO: Verifica se stiamo creando uno sconto senza stagioni
    $stagioni = $_POST['stagioni'] ?? [];
    $tipo = $_POST['tipo'];
    $tipiMaglia = $_POST['tipiMaglia'] ?? [];
    
    // CONTROLLO OBBLIGATORIETÀ STAGIONI
    if (empty($stagioni)) {
        $errore = "È obbligatorio selezionare almeno una stagione o 'Tutte'.";
    }
    
    // Se non stiamo specificando stagioni, verifica che non ci siano altri sconti dello stesso tipo senza stagioni
    else if ($stagioni[0] === "" && empty($errore)) {
        $scontiStessoTipo = $xpath->query("//sconto[tipo='$tipo']");
        $trovatoSenzaStagioni = false;
        
        foreach ($scontiStessoTipo as $scontoAltro) {
            $condizioniAltro = $scontoAltro->getElementsByTagName('condizioniMaglia')->item(0);
            // Controlla se non ha condizioni maglia OPPURE se ha condizioni maglia ma senza stagioni
            if (!$condizioniAltro || $condizioniAltro->getElementsByTagName('stagione')->length === 0) {
                $trovatoSenzaStagioni = true;
                break;
            }
        }
        
        if ($trovatoSenzaStagioni) {
            $errore = "Impossibile creare lo sconto senza stagioni. Esiste già un altro sconto di tipo '$tipo' senza stagioni specificate.";
        }
        
    }

    // CONTROLLO: Verifica se esiste già uno sconto con le stesse condizioni
    if (empty($errore)) {
        $scontiStessoTipo = $xpath->query("//sconto[tipo='$tipo']");
        
        foreach ($scontiStessoTipo as $scontoEsistente) {
            $condizioniEsistenti = $scontoEsistente->getElementsByTagName('condizioniMaglia')->item(0);
            
            // Se entrambi non hanno condizioni, sono uguali
            if ($stagioni[0] === "" && empty($tipiMaglia) && !$condizioniEsistenti) {
                $errore = "Esiste già uno sconto di tipo '$tipo' senza condizioni specificate.";
                break;
            }
            
            // Se entrambi hanno condizioni, confrontale
            if ($condizioniEsistenti && ($stagioni[0] !== "" || !empty($tipiMaglia))) {
                $stagioniEsistenti = [];
                $tipiMagliaEsistenti = [];
                
                // Recupera stagioni esistenti
                foreach ($condizioniEsistenti->getElementsByTagName('stagione') as $stagione) {
                    $stagioniEsistenti[] = $stagione->nodeValue;
                }
                
                // Recupera tipi maglia esistenti
                foreach ($condizioniEsistenti->getElementsByTagName('tipo') as $tipoMaglia) {
                    $tipiMagliaEsistenti[] = $tipoMaglia->nodeValue;
                }
                
                // Se abbiamo selezionato "Tutte", confronta con array vuoto
                $stagioniNew = ($stagioni[0] === "") ? [] : $stagioni;
                $stagioniExisting = $stagioniEsistenti;
                sort($stagioniNew);
                sort($stagioniExisting);
                
                // Confronta tipi maglia (ordiniamo per confronto indipendente dall'ordine)
                $tipiMagliaNew = $tipiMaglia;
                $tipiMagliaExisting = $tipiMagliaEsistenti;
                sort($tipiMagliaNew);
                sort($tipiMagliaExisting);
                
                // Se entrambi gli array sono uguali, trovato duplicato
                if ($stagioniNew === $stagioniExisting && $tipiMagliaNew === $tipiMagliaExisting) {
                    $errore = "Esiste già uno sconto di tipo '$tipo' con le stesse condizioni (stagioni e tipi maglia).";
                    break;
                }
            }
        }
    }

    // Se c'è un errore, interrompi il salvataggio
    if (!empty($errore)) {
        // Mostra l'errore e non procedere con il salvataggio
    } else {
        // Calcola nuovo ID numerico sequenziale
        $ids = $xpath->query("//sconto/ID");
        $maxId = 0;
        foreach ($ids as $idNode) {
            $currentId = (int)$idNode->nodeValue;
            if ($currentId > $maxId) {
                $maxId = $currentId;
            }
        }
        $ID = $maxId + 1;

        $root = $xml->getElementsByTagName("sconti")->item(0);
        
        // Crea il nuovo elemento sconto
        $sconto = $xml->createElement("sconto");
        
        // Aggiungi un ritorno a capo e indentazione per il nuovo sconto
        $sconto->appendChild($xml->createTextNode("\n    "));
        
        // Campi comuni con formattazione
        $sconto->appendChild($xml->createElement("ID", $ID));
        $sconto->appendChild($xml->createTextNode("\n    "));
        $sconto->appendChild($xml->createElement("tipo", $_POST['tipo']));
        $sconto->appendChild($xml->createTextNode("\n    "));
        $sconto->appendChild($xml->createElement("nome", $_POST['descrizione']));
        $sconto->appendChild($xml->createTextNode("\n    "));
        $sconto->appendChild($xml->createElement("attivo", $_POST['attivo'] ?? "false"));
        $sconto->appendChild($xml->createTextNode("\n    "));

        if (!empty($_POST['data_inizio'])) {
            $sconto->appendChild($xml->createElement("data_inizio", $_POST['data_inizio']));
            $sconto->appendChild($xml->createTextNode("\n    "));
        }
        if (!empty($_POST['data_fine'])) {
            $sconto->appendChild($xml->createElement("data_fine", $_POST['data_fine']));
            $sconto->appendChild($xml->createTextNode("\n    "));
        }
        if (!empty($_POST['percentualeFissa'])) {
            $sconto->appendChild($xml->createElement("percentualeFissa", $_POST['percentualeFissa']));
            $sconto->appendChild($xml->createTextNode("\n    "));
        }
        if (!empty($_POST['percentualeMax'])) {
            $sconto->appendChild($xml->createElement("percentualeMax", $_POST['percentualeMax']));
            $sconto->appendChild($xml->createTextNode("\n    "));
        }
        if (!empty($_POST['sogliaCreditiStep'])) {
            $sconto->appendChild($xml->createElement("sogliaCreditiStep", $_POST['sogliaCreditiStep']));
            $sconto->appendChild($xml->createTextNode("\n    "));
        }
        
        // Pattern Codice SOLO per FEDELISSIMO
        if ($_POST['tipo'] === 'FEDELISSIMO' && !empty($_POST['patternCodice'])) {
            $sconto->appendChild($xml->createElement("patternCodice", $_POST['patternCodice']));
            $sconto->appendChild($xml->createTextNode("\n    "));
        }

        // Condizioni Maglia (per RETRO, TIPO_MAGLIA, ma ora anche per FEDELISSIMO e ANZIANITA se compilate)
        if (($stagioni[0] !== "" && !empty($stagioni)) || !empty($tipiMaglia)) {
            $cond = $xml->createElement("condizioniMaglia");
            $cond->appendChild($xml->createTextNode("\n      "));
            
            if ($stagioni[0] !== "" && !empty($stagioni)) {
                foreach ($stagioni as $stagione) {
                    if (trim($stagione) !== "") {
                        $cond->appendChild($xml->createElement("stagione", $stagione));
                        $cond->appendChild($xml->createTextNode("\n      "));
                    }
                }
            }
            if (!empty($tipiMaglia)) {
                foreach ($tipiMaglia as $tipoMaglia) {
                    $cond->appendChild($xml->createElement("tipo", $tipoMaglia));
                    $cond->appendChild($xml->createTextNode("\n      "));
                }
            }
            
            // Rimuovi l'ultimo ritorno a capo e spazi in eccesso
            $lastChild = $cond->lastChild;
            if ($lastChild && $lastChild->nodeType === XML_TEXT_NODE) {
                $cond->removeChild($lastChild);
            }
            
            $cond->appendChild($xml->createTextNode("\n    "));
            $sconto->appendChild($cond);
            $sconto->appendChild($xml->createTextNode("\n  "));
        }

        // Campi specifici ANZIANITA
        if ($_POST['tipo'] === "ANZIANITA") {
            if (!empty($_POST['periodoMensilita'])) {
                $sconto->appendChild($xml->createElement("periodoMensilita", $_POST['periodoMensilita']));
                $sconto->appendChild($xml->createTextNode("\n    "));
            }
            if (!empty($_POST['incrementoPercentuale'])) {
                $sconto->appendChild($xml->createElement("incrementoPercentuale", $_POST['incrementoPercentuale']));
                $sconto->appendChild($xml->createTextNode("\n    "));
            }
            if (!empty($_POST['percentualeMax'])) {
                $sconto->appendChild($xml->createElement("percentualeMax", $_POST['percentualeMax']));
                $sconto->appendChild($xml->createTextNode("\n    "));
            }
        }

        // Rimuovi l'ultimo ritorno a capo e spazi in eccesso
        $lastChild = $sconto->lastChild;
        if ($lastChild && $lastChild->nodeType === XML_TEXT_NODE) {
            $sconto->removeChild($lastChild);
        }
        
        $sconto->appendChild($xml->createTextNode("\n  "));
        
        // Aggiungi il nuovo sconto al root
        $root->appendChild($sconto);
        
        // Salva il file con formattazione
        salvaXML($xml, $scontiFile);

        $successo = "Sconto {$_POST['tipo']} creato correttamente con ID $ID.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Immissione Sconti</title>
  <link rel="stylesheet" href="styles/style_crea_bonus.css">
  <script>
    function aggiungiStagione(containerId) {
      const container = document.getElementById(containerId);
      const div = document.createElement('div');
      div.className = 'stagione-item';
      
      let selectHTML = '<select class="input" name="stagioni[]" required><option value="">-- Seleziona Stagione --</option>';
      <?php 
      for ($anno = 1980; $anno <= $annoCorrente; $anno++) {
          $stagione = $anno . "/" . str_pad(($anno+1)%100, 2, "0", STR_PAD_LEFT);
          echo "selectHTML += '<option value=\"$stagione\">$stagione</option>';";
      }
      ?>
      selectHTML += '</select><button type="button" onclick="this.parentElement.remove()" class="btn-remove">Rimuovi</button>';
      
      div.innerHTML = selectHTML;
      container.appendChild(div);
    }

    function aggiungiStagioneFedelissimo() {
      aggiungiStagione('stagioni-container-fedelissimo');
    }

    function aggiungiStagioneAnzianita() {
      aggiungiStagione('stagioni-container-anzianita');
    }

    function aggiungiStagioneRetro() {
      aggiungiStagione('stagioni-container-retro');
    }
  </script>
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

<!-- Selezione Tipo di Sconto -->
<form method="post" class="card narrow">
  <label class="label" for="select_sconto">Seleziona Tipo di Sconto:</label>
  <select id="select_sconto" name="select_sconto" class="input" onchange="this.form.submit()">
    <option value="">-- Seleziona --</option>
    <option value="FEDELISSIMO"   <?= $tipoSelezionato==="FEDELISSIMO"?"selected":"" ?>>Fedelissimo</option>
    <option value="ANZIANITA"     <?= $tipoSelezionato==="ANZIANITA"?"selected":"" ?>>Anzianità</option>
    <option value="TIPO_MAGLIA"   <?= $tipoSelezionato==="TIPO_MAGLIA"?"selected":"" ?>>Per Tipo Maglia</option>
    <option value="RETRO"         <?= $tipoSelezionato==="RETRO"?"selected":"" ?>>Retro</option>
  </select>
</form>

<?php if ($tipoSelezionato === "FEDELISSIMO"): ?>
<form method="post" class="card_narrow">
  <h2>Crea Sconto Fedelissimo</h2>
  <input type="hidden" name="tipo" value="FEDELISSIMO" required>

  <label>Descrizione</label>
  <input type="text" name="descrizione" required>

  <label>Attivo</label>
  <select name="attivo" required>
    <option value="true">true</option>
    <option value="false">false</option>
  </select>

  <label>Data Inizio</label>
  <input type="date" name="data_inizio" required>

  <label>Data Fine</label>
  <input type="date" name="data_fine" required>

  <label>Percentuale Fissa</label>
  <input type="number" step="1" name="percentualeFissa" min="1" required>

  <label>Soglia Crediti Step</label>
  <input type="number" step="1" name="sogliaCreditiStep" min="1" required>

  <label>Pattern Codice</label>
  <input type="text" name="patternCodice" required>

  <label>Stagioni</label>
  <div id="stagioni-container-fedelissimo" class="stagioni-container">
    <div class="stagione-item">
      <select name="stagioni[]" required>
        <option value="">-- Seleziona Stagione --</option>
        <?php 
        for ($anno = 1980; $anno <= $annoCorrente; $anno++) {
            $stagione = $anno . "/" . str_pad(($anno+1)%100, 2, "0", STR_PAD_LEFT);
            echo "<option value=\"$stagione\">$stagione</option>";
        }
        ?>
        <option value="">Tutte</option>
      </select>
      <button type="button" onclick="this.parentElement.remove()" class="btn-remove">Rimuovi</button>
    </div>
  </div>
  <button type="button" onclick="aggiungiStagioneFedelissimo()" class="btn-add">Aggiungi Stagione</button>

  <label><input type="checkbox" name="tipiMaglia[]" value="CASA"> CASA</label>
  <label><input type="checkbox" name="tipiMaglia[]" value="FUORI"> FUORI</label>
  <label><input type="checkbox" name="tipiMaglia[]" value="TERZA"> TERZA</label>
  <label><input type="checkbox" name="tipiMaglia[]" value="PORTIERE"> PORTIERE</label>

  <input type="submit" name="invio" value="Salva">
</form>
<?php endif; ?>

<?php if ($tipoSelezionato === "ANZIANITA"): ?>
<form method="post" class="card_narrow">
  <h2>Crea Sconto Anzianità</h2>
  <input type="hidden" name="tipo" value="ANZIANITA">

  <label>Descrizione</label>
  <input type="text" name="descrizione" required>

  <label>Attivo</label>
  <select name="attivo" required>
    <option value="true">true</option>
    <option value="false">false</option>
  </select>

  <label>Data Inizio</label>
  <input type="date" name="data_inizio" required>

  <label>Data Fine</label>
  <input type="date" name="data_fine" required>

  <label>Periodo Mensilità</label>
  <input type="number" name="periodoMensilita" min="1" step="1" required>

  <label>Incremento Percentuale</label>
  <input type="number" step="1" name="incrementoPercentuale" min="1" required>

  <label>Percentuale Max</label>
  <input type="number" step="1" name="percentualeMax" min="1" required>

  <label>Stagioni</label>
  <div id="stagioni-container-anzianita" class="stagioni-container">
    <div class="stagione-item">
      <select name="stagioni[]" required>
        <option value="">-- Seleziona Stagione --</option>
        <?php 
        for ($anno = 2000; $anno <= $annoCorrente; $anno++) {
            $stagione = $anno . "/" . str_pad(($anno+1)%100, 2, "0", STR_PAD_LEFT);
            echo "<option value=\"$stagione\">$stagione</option>";
        }
        ?>
        <option value="">Tutte</option>
      </select>
      <button type="button" onclick="this.parentElement.remove()" class="btn-remove">Rimuovi</button>
    </div>
  </div>
  <button type="button" onclick="aggiungiStagioneAnzianita()" class="btn-add">Aggiungi Stagione</button>

  <label><input type="checkbox" name="tipiMaglia[]" value="CASA"> CASA</label>
  <label><input type="checkbox" name="tipiMaglia[]" value="FUORI"> FUORI</label>
  <label><input type="checkbox" name="tipiMaglia[]" value="TERZA"> TERZA</label>
  <label><input type="checkbox" name="tipiMaglia[]" value="PORTIERE"> PORTIERE</label>

  <input type="submit" name="invio" value="Salva">
</form>
<?php endif; ?>

<?php if ($tipoSelezionato === "TIPO_MAGLIA"): ?>
<form method="post" class="card_narrow">
  <h2>Crea Sconto per Tipo Maglia</h2>
  <input type="hidden" name="tipo" value="TIPO_MAGLIA">

  <label>Descrizione</label>
  <input type="text" name="descrizione" required>

  <label>Attivo</label>
  <select name="attivo" required>
    <option value="true">true</option>
    <option value="false">false</option>
  </select>

  <label>Data Inizio</label>
  <input type="date" name="data_inizio" required>

  <label>Data Fine</label>
  <input type="date" name="data_fine" required>

  <label>Percentuale Fissa</label>
  <input type="number" step="1" name="percentualeFissa" min="1" required>

  <fieldset>
    <legend>Condizioni Maglia</legend>
    <label><input type="checkbox" name="tipiMaglia[]" value="CASA"> CASA</label>
    <label><input type="checkbox" name="tipiMaglia[]" value="FUORI"> FUORI</label>
    <label><input type="checkbox" name="tipiMaglia[]" value="TERZA"> TERZA</label>
    <label><input type="checkbox" name="tipiMaglia[]" value="PORTIERE"> PORTIERE</label>
  </fieldset>

  <input type="submit" name="invio" value="Salva">
</form>
<?php endif; ?>

<?php if ($tipoSelezionato === "RETRO"): ?>
<form method="post" class="card_narrow">
  <h2>Crea Sconto Retro</h2>
  <input type="hidden" name="tipo" value="RETRO">

  <label>Descrizione</label>
  <input type="text" name="descrizione" required>

  <label>Attivo</label>
  <select name="attivo" required>
    <option value="true">true</option>
    <option value="false">false</option>
  </select>

  <label>Data Inizio</label>
  <input type="date" name="data_inizio" required>

  <label>Data Fine</label>
  <input type="date" name="data_fine" required>

  <label>Percentuale Fissa</label>
  <input type="number" step="1" name="percentualeFissa" min="1" required>

  <fieldset>
    <legend>Condizioni Maglia</legend>
    <label>Stagioni</label>
    <div id="stagioni-container-retro" class="stagioni-container">
      <div class="stagione-item">
        <select name="stagioni[]" required>
          <option value="">-- Seleziona Stagione --</option>
          <?php 
          for ($anno = 2000; $anno <= $annoCorrente; $anno++) {
              $stagione = $anno . "/" . str_pad(($anno+1)%100, 2, "0", STR_PAD_LEFT);
              echo "<option value=\"$stagione\">$stagione</option>";
          }
          ?>
          <option value="">Tutte</option>
        </select>
        <button type="button" onclick="this.parentElement.remove()" class="btn-remove">Rimuovi</button>
      </div>
    </div>
    <button type="button" onclick="aggiungiStagioneRetro()" class="btn-add">Aggiungi Stagione</button>

    <label><input type="checkbox" name="tipiMaglia[]" value="CASA"> CASA</label>
    <label><input type="checkbox" name="tipiMaglia[]" value="FUORI"> FUORI</label>
    <label><input type="checkbox" name="tipiMaglia[]" value="TERZA"> TERZA</label>
    <label><input type="checkbox" name="tipiMaglia[]" value="PORTIERE"> PORTIERE</label>
  </fieldset>

  <input type="submit" name="invio" value="Salva">
</form>
<?php endif; ?>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
  <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
</footer>

</body>
</html>