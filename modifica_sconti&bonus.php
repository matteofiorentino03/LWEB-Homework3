<?php
session_start();

/* ==========================
    Controllo Accesso
========================== */
if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo'])) {
    header("Location: entering.html");
    exit();
}
$ruolo = strtolower($_SESSION['Ruolo']);
if ($ruolo !== 'gestore' && $ruolo !== 'amministratore') {
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
   LINK HOME IN BASE AL RUOLO
========================== */
$homepage_link = ($ruolo === 'gestore') ? 'homepage_gestore.php' : 'homepage_user.php';

/* ==========================
   FILE XML
========================== */
$bonusFile  = "xml/bonus.xml";
$scontiFile = "xml/sconti.xml";

/* ==========================
   UTILITY: Salva XML
========================== */
function salvaXML(DOMDocument $dom, string $path): void {
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->save($path);
}

/* ==========================
   VARIABILI DI STATO
========================== */
$errore = "";
$successo = "";

$data = new DateTime();
$annoCorrente = (int)$data->format("Y");

$bonusSelezionato     = $_POST['select_bonus']         ?? "";
$scontoCodiceSel      = $_POST['select_sconto_codice'] ?? "";
$scontoAutoSel        = $_POST['select_sconto_auto']   ?? "";

/* ==========================
   SALVATAGGI
========================== */

/* ----- BONUS: CREDITI, REPUTAZIONEMINIMA, ATTIVO ----- */
if (isset($_POST['update_bonus'])) {
    $idBonus = $_POST['id_bonus'] ?? '';
    $crediti = $_POST['crediti'] ?? '';
    $repMin  = $_POST['reputazione_minima'] ?? '';
    $stagione = $_POST['stagione'] ?? '';
    $attivo  = ($_POST['attivo'] ?? 'false') === 'true' ? 'true' : 'false';

    try {
        $dom = new DOMDocument();
        $dom->load($bonusFile);
        $premi = $dom->getElementsByTagName("premio");
        $trovato = false;

        foreach ($premi as $premio) {
            $idNode = $premio->getElementsByTagName("ID")[0]->nodeValue ?? '';
            if ($idNode === $idBonus) {
                $tipo = $premio->getElementsByTagName("tipo")[0]->nodeValue ?? '';

                // Controlli duplicati
                if ($tipo === "Reputazione" && $repMin !== "") {
                    foreach ($premi as $altro) {
                        $altroId = $altro->getElementsByTagName("ID")[0]->nodeValue ?? '';
                        if ($altroId !== $idBonus) {
                            $altroRep = $altro->getElementsByTagName("reputazioneMinima")[0]->nodeValue ?? '';
                            if ($altroRep !== "" && $altroRep == $repMin) {
                                $errore = "Esiste già un altro bonus con la stessa reputazione minima.";
                                throw new Exception($errore);
                            }
                        }
                    }
                }

                if ($tipo === "MagliaRetro" && $stagione === "") {
                    foreach ($premi as $altro) {
                        $altroId = $altro->getElementsByTagName("ID")[0]->nodeValue ?? '';
                        if ($altroId !== $idBonus) {
                            $altroStagione = $altro->getElementsByTagName("stagione")->length
                                ? $altro->getElementsByTagName("stagione")[0]->nodeValue
                                : '';
                            if ($altroStagione === "") {
                                $errore = "Esiste già un altro bonus MagliaRetro senza stagione.";
                                throw new Exception($errore);
                            }
                        }
                    }
                }

                // Aggiornamento campi
                if ($premio->getElementsByTagName("crediti")->length)
                    $premio->getElementsByTagName("crediti")[0]->nodeValue = $crediti;

                if ($premio->getElementsByTagName("reputazioneMinima")->length)
                    $premio->getElementsByTagName("reputazioneMinima")[0]->nodeValue = $repMin;

                if ($premio->getElementsByTagName("stagione")->length) {
                    if ($stagione !== "") {
                        $premio->getElementsByTagName("stagione")[0]->nodeValue = $stagione;
                    } else {
                        $stagioneNode = $premio->getElementsByTagName("stagione")[0];
                        $premio->removeChild($stagioneNode);
                    }
                } elseif ($stagione !== "") {
                    $premio->appendChild($dom->createElement("stagione", $stagione));
                }

                if ($premio->getElementsByTagName("attivo")->length)
                    $premio->getElementsByTagName("attivo")[0]->nodeValue = $attivo;

                $trovato = true;
                break;
            }
        }

        if ($trovato && empty($errore)) {
            salvaXML($dom, $bonusFile);
            $successo = "Bonus aggiornato correttamente.";
            $bonusSelezionato = $idBonus;
        } elseif (!$trovato) {
            $errore = "Bonus non trovato.";
        }
    } catch (Throwable $e) {
        if (empty($errore)) {
            $errore = "Errore aggiornamento bonus: " . $e->getMessage();
        }
    }
}

/* ----- SCONTI NEL NUOVO FORMATO ----- */
if (isset($_POST['update_sconto'])) {
    $id = $_POST['id_sconto'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $attivo = ($_POST['attivo'] ?? 'false') === 'true' ? 'true' : 'false';
    $dataInizio = $_POST['data_inizio'] ?? '';
    $dataFine = $_POST['data_fine'] ?? '';
    $percentualeFissa = $_POST['percentuale_fissa'] ?? '';
    $percentualeMax = $_POST['percentuale_max'] ?? '';
    $sogliaCreditiStep = $_POST['soglia_crediti_step'] ?? '';
    $patternCodice = $_POST['pattern_codice'] ?? '';
    $periodoMensilita = $_POST['periodo_mensilita'] ?? '';
    $incrementoPercentuale = $_POST['incremento_percentuale'] ?? '';

    // Condizioni maglia (per FEDELISSIMO e sconti automatici)
    $stagioni = $_POST['stagioni'] ?? [];
    $tipiMaglia = $_POST['tipi_maglia'] ?? [];

    // Soglie reputazione - CORREZIONE: gestione array corretta
    $soglieReputazione = [];
    if (isset($_POST['max_reputazione']) && is_array($_POST['max_reputazione'])) {
        foreach ($_POST['max_reputazione'] as $index => $maxRep) {
            // Accetta anche valori vuoti per mantenere la struttura
            $percentuale = $_POST['percentuale_sconto'][$index] ?? '';
            $soglieReputazione[] = [
                'maxReputazione' => $maxRep,
                'percentualeSconto' => $percentuale
            ];
        }
    }

    try {
        $dom = new DOMDocument();
        $dom->load($scontiFile);
        $xpath = new DOMXPath($dom);
        $scontoNode = $xpath->query("//sconto[ID='$id']")->item(0);
        
        if (!$scontoNode) {
            $errore = "Sconto non trovato.";
        } else {
            // CONTROLLO: Verifica se stiamo rimuovendo tutte le stagioni
            $condizioniEsistenti = $scontoNode->getElementsByTagName('condizioniMaglia')->item(0);
            $haStagioniEsistenti = $condizioniEsistenti && $condizioniEsistenti->getElementsByTagName('stagione')->length > 0;
            
            // Se prima aveva stagioni e ora non ne ha più, verifica che non ci siano altri sconti dello stesso tipo senza stagioni
            if ($haStagioniEsistenti && empty($stagioni)) {
                $scontiStessoTipo = $xpath->query("//sconto[tipo='$tipo' and ID!='$id']");
                $trovatoSenzaStagioni = false;
                
                foreach ($scontiStessoTipo as $scontoAltro) {
                    $condizioniAltro = $scontoAltro->getElementsByTagName('condizioniMaglia')->item(0);
                    if (!$condizioniAltro || $condizioniAltro->getElementsByTagName('stagione')->length === 0) {
                        $trovatoSenzaStagioni = true;
                        break;
                    }
                }
                
                if ($trovatoSenzaStagioni) {
                    $errore = "Impossibile rimuovere tutte le stagioni. Esiste già un altro sconto di tipo '$tipo' senza stagioni specificate.";
                    throw new Exception($errore);
                }
            }

            // Campi base
            $campiBase = [
                'attivo' => $attivo,
                'data_inizio' => $dataInizio,
                'data_fine' => $dataFine,
                'percentualeFissa' => $percentualeFissa,
                'percentualeMax' => $percentualeMax,
                'sogliaCreditiStep' => $sogliaCreditiStep,
                'patternCodice' => $patternCodice,
                'periodoMensilita' => $periodoMensilita,
                'incrementoPercentuale' => $incrementoPercentuale
            ];

            foreach ($campiBase as $campo => $valore) {
                $node = $scontoNode->getElementsByTagName($campo)->item(0);
                if ($node) {
                    if ($valore !== '') {
                        $node->nodeValue = $valore;
                    } else {
                        $scontoNode->removeChild($node);
                    }
                } elseif ($valore !== '') {
                    $scontoNode->appendChild($dom->createElement($campo, $valore));
                }
            }

            // Gestione condizioni maglia (per FEDELISSIMO e sconti automatici)
            $condizioniNode = $scontoNode->getElementsByTagName('condizioniMaglia')->item(0);
            // Gestione condizioni maglia
            $condizioniNode = $scontoNode->getElementsByTagName('condizioniMaglia')->item(0);

            if (!empty($stagioni) || !empty($tipiMaglia)) {
                if (!$condizioniNode) {
                    $condizioniNode = $dom->createElement('condizioniMaglia');
                    $scontoNode->appendChild($condizioniNode);
                } else {
                    // Pulisci eventuali vecchie condizioni
                    while ($condizioniNode->hasChildNodes()) {
                        $condizioniNode->removeChild($condizioniNode->firstChild);
                    }
                }

                // Aggiungi stagioni e tipi
                foreach ($stagioni as $stagione) {
                    if ($stagione !== '') $condizioniNode->appendChild($dom->createElement('stagione', $stagione));
                }
                foreach ($tipiMaglia as $tipoMaglia) {
                    if ($tipoMaglia !== '') $condizioniNode->appendChild($dom->createElement('tipo', $tipoMaglia));
                }

                // Rimuovi se rimasto vuoto
                if ($condizioniNode->childNodes->length === 0) {
                    $scontoNode->removeChild($condizioniNode);
                }

            } elseif ($condizioniNode) {
                // Rimuovi nodo esistente se non ci sono nuove condizioni
                $scontoNode->removeChild($condizioniNode);
            }


            // Gestione soglie reputazione
            $scontoRepNode = $scontoNode->getElementsByTagName('scontoReputazione')->item(0);
            
            // Filtra solo le soglie con entrambi i valori compilati
            $soglieValide = array_filter($soglieReputazione, function($soglia) {
            return trim((string)$soglia['maxReputazione']) !== '' &&
                  trim((string)$soglia['percentualeSconto']) !== '';
         });
            
            if (!empty($soglieValide)) {
                if (!$scontoRepNode) {
                    $scontoRepNode = $dom->createElement('scontoReputazione');
                    $scontoNode->appendChild($scontoRepNode);
                } else {
                    // Rimuovi soglie esistenti
                    while ($scontoRepNode->getElementsByTagName('soglia')->length > 0) {
                        $scontoRepNode->removeChild($scontoRepNode->getElementsByTagName('soglia')->item(0));
                    }
                }

                // Aggiungi nuove soglie valide
                foreach ($soglieValide as $soglia) {
                    $sogliaNode = $dom->createElement('soglia');
                    $sogliaNode->appendChild($dom->createElement('maxReputazione', $soglia['maxReputazione']));
                    $sogliaNode->appendChild($dom->createElement('percentualeSconto', $soglia['percentualeSconto']));
                    $scontoRepNode->appendChild($sogliaNode);
                }
            } elseif ($scontoRepNode) {
                $scontoNode->removeChild($scontoRepNode);
            }

            salvaXML($dom, $scontiFile);
            $successo = "Sconto aggiornato correttamente.";
            
            // Imposta la selezione corretta in base al tipo
            if (in_array($tipo, ['FEDELISSIMO', 'BENVENUTOLUPETTO'])) {
                $scontoCodiceSel = $id;
            } else {
                $scontoAutoSel = $id;
            }
        }
    } catch (Throwable $e) {
        if (empty($errore)) {
            $errore = "Errore aggiornamento sconto: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Gestione Sconti e Bonus</title>
  <link rel="stylesheet" href="styles/style_modifica_sconti.css">
  <script>
    function aggiungiSogliaReputazione() {
    const container = document.getElementById('soglie-reputazione-container');
    const index = container.children.length;
    const div = document.createElement('div');
    div.className = 'soglia-reputazione';
    div.innerHTML = `
        <label class="label">Max Reputazione</label>
        <input type="number" class="input-soglia-reputazione" name="max_reputazione[]" placeholder="Max reputazione">
        <label class="label">Percentuale Sconto</label>
        <input type="number" step="0.01" class="input-soglia-reputazione" name="percentuale_sconto[]" placeholder="Percentuale sconto">
        <button type="button" onclick="this.parentElement.remove()" class="btn-remove">Rimuovi</button>
    `;
    container.appendChild(div);
  }

    function aggiungiStagione(containerId) {
        const container = document.getElementById(containerId);
        const div = document.createElement('div');
        div.className = 'stagione-item';
        
        let selectHTML = '<select class="input" name="stagioni[]"><option value="">-- Seleziona Stagione --</option>';
        <?php 
        for ($anno = 1980; $anno <=  $annoCorrente; $anno++) {
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

    function aggiungiStagioneAuto() {
        aggiungiStagione('stagioni-container');
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
  <div class="utente-container"><div class="logout"><a href="?logout=true">Logout</a></div></div>
</header>

<main class="page">
  <?php if ($errore):   ?><div class="alert alert-error"><?= $errore ?></div><?php endif; ?>
  <?php if ($successo): ?><div class="alert alert-success"><?= $successo ?></div><?php endif; ?>

  <!-- ==========================
       SEZIONE BONUS
  ========================== -->
  <h2 class="page-title">Gestione Bonus</h2>

  <!-- Selettore bonus -->
<form method="post" class="card narrow">
  <label class="label" for="select_bonus">Seleziona Bonus:</label>
  <select id="select_bonus" name="select_bonus" class="input" onchange="this.form.submit()">
    <option value="">-- Seleziona --</option>
    <?php
    $domB = new DOMDocument();
    $domB->load($bonusFile);
    $premi = $domB->getElementsByTagName("premio");

    foreach ($premi as $premio) {
        $id   = $premio->getElementsByTagName("ID")[0]->nodeValue ?? '';
        $desc = $premio->getElementsByTagName("descrizione")[0]->nodeValue ?? '';
        $sel  = ($bonusSelezionato === $id) ? 'selected' : '';
        echo "<option value=\"".htmlspecialchars($id)."\" $sel>".htmlspecialchars($desc)."</option>";
    }
    ?>
  </select>
</form>

<?php
if ($bonusSelezionato !== "") {
    $xpath = new DOMXPath($domB);
    $bonusNode = $xpath->query("//premio[ID='$bonusSelezionato']")->item(0);

    if ($bonusNode) {
        $tipo        = $bonusNode->getElementsByTagName("tipo")[0]->nodeValue ?? '';
        $descrizione = $bonusNode->getElementsByTagName("descrizione")[0]->nodeValue ?? '';
        $crediti     = $bonusNode->getElementsByTagName("crediti")[0]->nodeValue ?? '';
        $stagione    = $bonusNode->getElementsByTagName("stagione")[0]->nodeValue ?? '';
        $repMin      = $bonusNode->getElementsByTagName("reputazioneMinima")[0]->nodeValue ?? '';
        $limiti      = $bonusNode->getElementsByTagName("limite_utilizzi")[0]->nodeValue ?? '';
        $dataInizio  = $bonusNode->getElementsByTagName("data_inizio")[0]->nodeValue ?? '';
        $attivo      = $bonusNode->getElementsByTagName("attivo")[0]->nodeValue ?? '';
        ?>
        
        <form method="post" class="card">
          <input type="hidden" name="id_bonus" value="<?= htmlspecialchars($bonusSelezionato) ?>">

          <div class="col">
            <label class="label">Tipo Bonus</label>
            <input type="text" class="input" value="<?= htmlspecialchars($tipo) ?>" readonly>
          </div>

          <div class="col">
            <label class="label">Descrizione</label>
            <input type="text" class="input" name="descrizione" value="<?= htmlspecialchars($descrizione) ?>" required>
          </div>

          <div class="col">
            <label class="label">Crediti</label>
            <input type="number" step="0.01" class="input" name="crediti" value="<?= htmlspecialchars($crediti) ?>" required>
          </div>

          <?php if ($tipo === "MagliaRetro"): ?>
            <div class="col">
              <label class="label">Stagione</label>
              <input type="number" class="input" name="stagione" value="<?= htmlspecialchars($stagione) ?>">
            </div>
          <?php endif; ?>

          <?php if ($tipo === "Reputazione"): ?>
            <div class="col">
              <label class="label">Reputazione Minima</label>
              <input type="number" class="input" name="reputazione_minima" value="<?= htmlspecialchars($repMin) ?>">
            </div>

            <div class="col">
              <label class="label">Limite utilizzi</label>
              <input type="number" class="input" name="limite_utilizzi" value="<?= htmlspecialchars($limiti) ?>">
            </div>

            <div class="col">
              <label class="label">Data inizio</label>
              <input type="date" class="input" name="data_inizio" value="<?= htmlspecialchars($dataInizio) ?>">
            </div>
          <?php endif; ?>

          <div class="col">
            <label class="label">Attivo</label>
            <select class="input" name="attivo">
              <option value="true"  <?= $attivo==="true" ? "selected":"" ?>>Attivo</option>
              <option value="false" <?= $attivo==="false"? "selected":"" ?>>Non attivo</option>
            </select>
          </div>

          <button type="submit" name="update_bonus" class="btn-submit">Salva Modifiche Bonus</button>
        </form>
        <?php
    }
}
?>

  <!-- ==========================
       SEZIONE SCONTI
  ========================== -->
  <h2 class="page-title page-title--sconti">Gestione Sconti</h2>

  <!-- ===== Sconti con Codice ===== -->
  <h3 class="section-subtitle">Sconti con Codice</h3>
  <form method="post" class="card narrow">
    <label class="label" for="select_sconto_codice">Seleziona Sconto con Codice:</label>
    <select id="select_sconto_codice" name="select_sconto_codice" class="input" onchange="this.form.submit()">
      <option value="">-- Seleziona --</option>
      <?php
      $domS = new DOMDocument();
      $domS->load($scontiFile);
      $sconti = $domS->getElementsByTagName("sconto");
      
      foreach ($sconti as $sconto) {
          $id   = $sconto->getElementsByTagName("ID")[0]->nodeValue ?? '';
          $tipo = $sconto->getElementsByTagName("tipo")[0]->nodeValue ?? '';
          $nome = $sconto->getElementsByTagName("nome")[0]->nodeValue ?? $id;
          
          // Mostra solo sconti con codice
          if (in_array($tipo, ['FEDELISSIMO', 'BENVENUTOLUPETTO'])) {
              $sel  = ($scontoCodiceSel === $id) ? 'selected' : '';
              echo "<option value=\"".htmlspecialchars($id)."\" $sel>".htmlspecialchars($nome)." (".htmlspecialchars($tipo).")</option>";
          }
      }
      ?>
    </select>
  </form>

  <?php
  if ($scontoCodiceSel !== ""):
      foreach ($sconti as $sconto) {
          if ($sconto->getElementsByTagName("ID")[0]->nodeValue === $scontoCodiceSel) {
              $tipo = $sconto->getElementsByTagName("tipo")[0]->nodeValue ?? '';
              $nome = $sconto->getElementsByTagName("nome")[0]->nodeValue ?? '';
              $attivo = $sconto->getElementsByTagName("attivo")[0]->nodeValue ?? 'false';
              $dataInizio = $sconto->getElementsByTagName("data_inizio")->length ? $sconto->getElementsByTagName("data_inizio")[0]->nodeValue : '';
              $dataFine = $sconto->getElementsByTagName("data_fine")->length ? $sconto->getElementsByTagName("data_fine")[0]->nodeValue : '';
              $percentualeFissa = $sconto->getElementsByTagName("percentualeFissa")->length ? $sconto->getElementsByTagName("percentualeFissa")[0]->nodeValue : '';
              $percentualeMax = $sconto->getElementsByTagName("percentualeMax")->length ? $sconto->getElementsByTagName("percentualeMax")[0]->nodeValue : '';
              $sogliaCreditiStep = $sconto->getElementsByTagName("sogliaCreditiStep")->length ? $sconto->getElementsByTagName("sogliaCreditiStep")[0]->nodeValue : '';
              $patternCodice = $sconto->getElementsByTagName("patternCodice")->length ? $sconto->getElementsByTagName("patternCodice")[0]->nodeValue : '';

              // Condizioni maglia per FEDELISSIMO
              $stagioni = [];
              $tipiMaglia = [];
              if ($sconto->getElementsByTagName("condizioniMaglia")->length) {
                  $condizioni = $sconto->getElementsByTagName("condizioniMaglia")[0];
                  foreach ($condizioni->getElementsByTagName("stagione") as $stagione) {
                      $stagioni[] = $stagione->nodeValue;
                  }
                  foreach ($condizioni->getElementsByTagName("tipo") as $tipoMaglia) {
                      $tipiMaglia[] = $tipoMaglia->nodeValue;
                  }
              }
              ?>
              <form method="post" class="card">
                <input type="hidden" name="id_sconto" value="<?= htmlspecialchars($scontoCodiceSel) ?>">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">

                <div class="grid">
                    <div class="col">
                      <label class="label">Nome</label>
                      <input class="input" type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" required>
                    </div>

                    <div class="col">
                      <label class="label">Attivo</label>
                      <select class="input" name="attivo">
                        <option value="true"  <?= $attivo==="true" ? "selected":"" ?>>Attivo</option>
                        <option value="false" <?= $attivo==="false"? "selected":"" ?>>Non attivo</option>
                      </select>
                    </div>

                    <div class="col">
                      <label class="label">Data Inizio</label>
                      <input class="input" type="date" name="data_inizio" value="<?= htmlspecialchars($dataInizio) ?>">
                    </div>

                    <div class="col">
                      <label class="label">Data Fine</label>
                      <input class="input" type="date" name="data_fine" value="<?= htmlspecialchars($dataFine) ?>">
                    </div>

                    <?php if ($tipo === 'FEDELISSIMO'): ?>
                    <div class="col">
                      <label class="label">Percentuale Fissa</label>
                      <input class="input" type="number" step="0.01" name="percentuale_fissa" value="<?= htmlspecialchars($percentualeFissa) ?>">
                    </div>
                    <?php endif; ?>
                      
                    <?php if ($tipo === 'BENVENUTOLUPETTO'): ?>
                    <div class="col">
                      <label class="label">Percentuale Max</label>
                      <input class="input" type="number" step="0.01" name="percentuale_max" value="<?= htmlspecialchars($percentualeMax) ?>">
                    </div>
                    <?php endif; ?>

                    <div class="col">
                      <label class="label">Pattern Codice</label>
                      <input class="input" type="text" name="pattern_codice" value="<?= htmlspecialchars($patternCodice) ?>" <?php if($tipo === 'BENVENUTOLUPETTO') echo 'disabled'; ?>>
                    </div>

                    <!-- Condizioni Maglia per FEDELISSIMO -->
                    <?php if ($tipo === 'FEDELISSIMO'): ?>
                    <div class="col col-full">
                      <label class="label">Condizioni Maglia - Stagioni</label>
                      <div id="stagioni-container-fedelissimo">
                        <?php foreach ($stagioni as $stagione): ?>
                          <div class="stagione-item">
                            <select class="input" name="stagioni[]">
                              <option value="">-- Seleziona Stagione --</option>
                              <?php 
                              for ($anno = 1980; $anno <= $annoCorrente; $anno++) {
                                  $stagioneOption = $anno . "/" . str_pad(($anno+1)%100, 2, "0", STR_PAD_LEFT);
                                  $selected = ($stagione === $stagioneOption) ? 'selected' : '';
                                  echo "<option value=\"$stagioneOption\" $selected>$stagioneOption</option>";
                              }
                              ?>
                              <option value="">Tutte le Stagioni</option>
                            </select>
                            <button type="button" onclick="this.parentElement.remove()" class="btn-remove">Rimuovi</button>
                          </div>
                        <?php endforeach; ?>
                        <?php if (empty($stagioni)): ?>
                          <div class="stagione-item">
                            <select class="input" name="stagioni[]">
                              <option value="">-- Seleziona Stagione --</option>
                              <?php 
                              for ($anno = 1980; $anno <= $annoCorrente; $anno++) {
                                  $stagioneOption = $anno . "/" . str_pad(($anno+1)%100, 2, "0", STR_PAD_LEFT);
                                  echo "<option value=\"$stagioneOption\">$stagioneOption</option>";
                              }
                              ?>
                              <option value="">Tutte le Stagioni</option>
                            </select>
                            <button type="button" onclick="this.parentElement.remove()" class="btn-remove">Rimuovi</button>
                          </div>
                        <?php endif; ?>
                      </div>
                      <button type="button" onclick="aggiungiStagioneFedelissimo()" class="btn-add">Aggiungi Stagione</button>
                    </div>

                    <div class="col col-full">
                      <label class="label">Condizioni Maglia - Tipi</label>
                      <div class="role-block">
                        <?php foreach (['CASA','FUORI','TERZA','PORTIERE'] as $tipoMaglia): ?>
                          <label class="checkbox-inline">
                            <input type="checkbox" name="tipi_maglia[]" value="<?= $tipoMaglia ?>"
                              <?= in_array($tipoMaglia, $tipiMaglia) ? 'checked':'' ?>>
                            <?= $tipoMaglia ?>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" name="update_sconto" class="btn-submit">Salva Modifiche Sconto</button>
              </form>
              <?php
              break;
          }
      }
  endif;
  ?>

  <!-- ===== Sconti senza codice ===== -->
  <h3 class="section-subtitle">Sconti senza codice</h3>
  <form method="post" class="card narrow">
    <label class="label" for="select_sconto_auto">Seleziona Sconto senza codice:</label>
    <select id="select_sconto_auto" name="select_sconto_auto" class="input" onchange="this.form.submit()">
      <option value="">-- Seleziona --</option>
      <?php
      foreach ($sconti as $sconto) {
          $id   = $sconto->getElementsByTagName("ID")[0]->nodeValue ?? '';
          $tipo = $sconto->getElementsByTagName("tipo")[0]->nodeValue ?? '';
          $nome = $sconto->getElementsByTagName("nome")[0]->nodeValue ?? $id;
          
          // Mostra solo sconti automatici
          if (in_array($tipo, ['RETRO', 'REPUTAZIONE', 'ANZIANITA', 'TIPO_MAGLIA'])) {
              $sel  = ($scontoAutoSel === $id) ? 'selected' : '';
              echo "<option value=\"".htmlspecialchars($id)."\" $sel>".htmlspecialchars($nome)." (".htmlspecialchars($tipo).")</option>";
          }
      }
      ?>
    </select>
  </form>

  <?php
  if ($scontoAutoSel !== ""):
      foreach ($sconti as $sconto) {
          if ($sconto->getElementsByTagName("ID")[0]->nodeValue === $scontoAutoSel) {
              $tipo = $sconto->getElementsByTagName("tipo")[0]->nodeValue ?? '';
              $nome = $sconto->getElementsByTagName("nome")[0]->nodeValue ?? '';
              $attivo = $sconto->getElementsByTagName("attivo")[0]->nodeValue ?? 'false';
              $dataInizio = $sconto->getElementsByTagName("data_inizio")->length ? $sconto->getElementsByTagName("data_inizio")[0]->nodeValue : '';
              $dataFine = $sconto->getElementsByTagName("data_fine")->length ? $sconto->getElementsByTagName("data_fine")[0]->nodeValue : '';
              $percentualeFissa = $sconto->getElementsByTagName("percentualeFissa")->length ? $sconto->getElementsByTagName("percentualeFissa")[0]->nodeValue : '';
              $percentualeMax = $sconto->getElementsByTagName("percentualeMax")->length ? $sconto->getElementsByTagName("percentualeMax")[0]->nodeValue : '';
              $periodoMensilita = $sconto->getElementsByTagName("periodoMensilita")->length ? $sconto->getElementsByTagName("periodoMensilita")[0]->nodeValue : '';
              $incrementoPercentuale = $sconto->getElementsByTagName("incrementoPercentuale")->length ? $sconto->getElementsByTagName("incrementoPercentuale")[0]->nodeValue : '';

              // Condizioni maglia
              $stagioni = [];
              $tipiMaglia = [];
              if ($sconto->getElementsByTagName("condizioniMaglia")->length) {
                  $condizioni = $sconto->getElementsByTagName("condizioniMaglia")[0];
                  foreach ($condizioni->getElementsByTagName("stagione") as $stagione) {
                      $stagioni[] = $stagione->nodeValue;
                  }
                  foreach ($condizioni->getElementsByTagName("tipo") as $tipoMaglia) {
                      $tipiMaglia[] = $tipoMaglia->nodeValue;
                  }
              }

              $soglieReputazione = [];
              if ($sconto->getElementsByTagName("scontoReputazione")->length) {
                  $scontoRep = $sconto->getElementsByTagName("scontoReputazione")[0];
                  foreach ($scontoRep->getElementsByTagName("soglia") as $soglia) {
                      $maxRep = $soglia->getElementsByTagName("maxReputazione")[0]->nodeValue ?? '';
                      $percSconto = $soglia->getElementsByTagName("percentualeSconto")[0]->nodeValue ?? '';
                      if ($maxRep !== '' && $percSconto !== '') {
                          $soglieReputazione[] = ['maxReputazione' => $maxRep, 'percentualeSconto' => $percSconto];
                      }
                  }
              }
              
              if (empty($soglieReputazione) && $tipo === 'REPUTAZIONE') {
                  $soglieReputazione[] = ['maxReputazione' => '', 'percentualeSconto' => ''];
              }
              ?>
              <form method="post" class="card">
                <input type="hidden" name="id_sconto" value="<?= htmlspecialchars($scontoAutoSel) ?>">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">

                <div class="grid">
                  <div class="col">
                    <label class="label">Nome</label>
                    <input class="input" type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" required>
                  </div>

                  <div class="col">
                    <label class="label">Tipo Sconto</label>
                    <input class="input" type="text" value="<?= htmlspecialchars($tipo) ?>" disabled>
                  </div>

                  <div class="col">
                    <label class="label">Attivo</label>
                    <select class="input" name="attivo">
                      <option value="true"  <?= $attivo==="true" ? "selected":"" ?>>Attivo</option>
                      <option value="false" <?= $attivo==="false"? "selected":"" ?>>Non attivo</option>
                    </select>
                  </div>

                  <div class="col">
                    <label class="label">Data Inizio</label>
                    <input class="input" type="date" name="data_inizio" value="<?= htmlspecialchars($dataInizio) ?>">
                  </div>

                  <div class="col">
                    <label class="label">Data Fine</label>
                    <input class="input" type="date" name="data_fine" value="<?= htmlspecialchars($dataFine) ?>">
                  </div>

                  <?php if (in_array($tipo, ['RETRO', 'TIPO_MAGLIA'])): ?>
                  <div class="col">
                    <label class="label">Percentuale Fissa</label>
                    <input class="input" type="number" step="0.01" name="percentuale_fissa" value="<?= htmlspecialchars($percentualeFissa) ?>">
                  </div>
                  <?php endif; ?>

                  <?php if ($tipo === 'ANZIANITA'): ?>
                  <div class="col">
                    <label class="label">Periodo Mensilità</label>
                    <input class="input" type="number" step="0.01" name="periodo_mensilita" value="<?= htmlspecialchars($periodoMensilita) ?>">
                  </div>
                  <div class="col">
                    <label class="label">Incremento Percentuale</label>
                    <input class="input" type="number" step="0.01" name="incremento_percentuale" value="<?= htmlspecialchars($incrementoPercentuale) ?>">
                  </div>
                  <?php endif; ?>

                  <?php if ($tipo === 'ANZIANITA' ): ?>
                  <div class="col">
                    <label class="label">Percentuale Max</label>
                    <input class="input" type="number" step="0.01" name="percentuale_max" value="<?= htmlspecialchars($percentualeMax) ?>">
                  </div>
                  <?php endif; ?>

                  <?php if (!in_array($tipo, [ 'TIPO_MAGLIA'])): ?>
                  <div class="col col-full">
                    <label class="label">Condizioni Maglia - Stagioni</label>
                    <div id="stagioni-container">
                      <?php foreach ($stagioni as $stagione): ?>
                        <div class="stagione-item">
                          <select class="input" name="stagioni[]">
                            <option value="">-- Seleziona Stagione --</option>
                            <?php 
                            for ($anno = 1980; $anno <=  $annoCorrente; $anno++) {
                                $stagioneOption = $anno . "/" . str_pad(($anno+1)%100, 2, "0", STR_PAD_LEFT);
                                $selected = ($stagione === $stagioneOption) ? 'selected' : '';
                                echo "<option value=\"$stagioneOption\" $selected>$stagioneOption</option>";
                            }
                            ?>
                            <option value="">Tutte le Stagioni</option>
                          </select>
                          <button type="button" onclick="this.parentElement.remove()" class="btn-remove">Rimuovi</button>
                        </div>
                      <?php endforeach; ?>
                      <?php if (empty($stagioni)): ?>
                        <div class="stagione-item">
                          <select class="input" name="stagioni[]">
                            <option value="">-- Seleziona Stagione --</option>
                            <?php 
                            for ($anno = 1980; $anno <=  $annoCorrente; $anno++) {
                                $stagioneOption = $anno . "/" . str_pad(($anno+1)%100, 2, "0", STR_PAD_LEFT);
                                echo "<option value=\"$stagioneOption\">$stagioneOption</option>";
                            }
                            ?>
                            <option value="">Tutte le Stagioni</option>
                          </select>
                          <button type="button" onclick="this.parentElement.remove()" class="btn-remove">Rimuovi</button>
                        </div>
                      <?php endif; ?>
                    </div>
                    <button type="button" onclick="aggiungiStagioneAuto()" class="btn-add">Aggiungi Stagione</button>
                  </div>
                  <?php endif; ?>

                  <div class="col col-full">
                    <label class="label">Condizioni Maglia - Tipi</label>
                    <div class="role-block">
                      <?php foreach (['CASA','FUORI','TERZA','PORTIERE'] as $tipoMaglia): ?>
                        <label class="checkbox-inline">
                          <input type="checkbox" name="tipi_maglia[]" value="<?= $tipoMaglia ?>"
                            <?= in_array($tipoMaglia, $tipiMaglia) ? 'checked':'' ?>>
                          <?= $tipoMaglia ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <?php if ($tipo === 'REPUTAZIONE'): ?>
                  <div class="col col-full">
                    <label class="label">Soglie Reputazione</label>
                    <div id="soglie-reputazione-container">
                      <?php foreach ($soglieReputazione as $soglia): ?>
                        <div class="soglia-reputazione">
                          <label class="label">Max Reputazione</label>
                          <input type="number" class="input-soglia-reputazione" name="max_reputazione[]" value="<?= htmlspecialchars($soglia['maxReputazione']) ?>" placeholder="Max reputazione">
                          <label class="label">Percentuale Sconto</label>
                          <input type="number" step="1" class="input-soglia-reputazione" name="percentuale_sconto[]" value="<?= htmlspecialchars($soglia['percentualeSconto']) ?>" placeholder="Percentuale sconto">
                          <button type="button" onclick="this.parentElement.remove()" class="btn-remove">Rimuovi</button>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="aggiungiSogliaReputazione()" class="btn-add">Aggiungi Soglia</button>
                  </div>
                  <?php endif; ?>

                </div>

                <button type="submit" name="update_sconto" class="btn-submit">Salva Modifiche Sconto</button>
              </form>
              <?php
              break;
          }
      }
  endif;
  ?>
</main>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
  <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
</footer>
</body>
</html>