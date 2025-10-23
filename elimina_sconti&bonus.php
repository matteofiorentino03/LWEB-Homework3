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

$bonusSelezionato = $_POST['select_bonus'] ?? "";
$scontoSelezionato = $_POST['select_sconto'] ?? "";

/* ==========================
   ELIMINAZIONI
========================== */

/* ----- ELIMINA BONUS ----- */
if (isset($_POST['delete_bonus'])) {
    $idBonus = $_POST['id_bonus'] ?? '';

    try {
        $dom = new DOMDocument();
        $dom->load($bonusFile);
        $premi = $dom->getElementsByTagName("premio");
        $trovato = false;

        foreach ($premi as $premio) {
            $idNode = $premio->getElementsByTagName("ID")[0]->nodeValue ?? '';
            if ($idNode === $idBonus) {
                // Rimuovi il nodo <premio>
                $premio->parentNode->removeChild($premio);
                $trovato = true;
                break;
            }
        }

        if ($trovato) {
            salvaXML($dom, $bonusFile);
            $successo = "Bonus eliminato correttamente.";
            $bonusSelezionato = ""; // reset selezione
        } else {
            $errore = "Bonus non trovato.";
        }
    } catch (Throwable $e) {
        $errore = "Errore eliminazione bonus: " . $e->getMessage();
    }
}

/* ----- ELIMINA SCONTO ----- */
if (isset($_POST['delete_sconto'])) {
    $idSconto = $_POST['id_sconto'] ?? '';

    try {
        $dom = new DOMDocument();
        $dom->load($scontiFile);
        $sconti = $dom->getElementsByTagName("sconto");
        $trovato = false;

        foreach ($sconti as $sconto) {
            $idNode = $sconto->getElementsByTagName("ID")[0]->nodeValue ?? '';
            if ($idNode === $idSconto) {
                // Rimuovi il nodo <sconto>
                $sconto->parentNode->removeChild($sconto);
                $trovato = true;
                break;
            }
        }

        if ($trovato) {
            salvaXML($dom, $scontiFile);
            $successo = "Sconto eliminato correttamente.";
            $scontoSelezionato = ""; // reset selezione
        } else {
            $errore = "Sconto non trovato.";
        }
    } catch (Throwable $e) {
        $errore = "Errore eliminazione sconto: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Elimina Sconti e Bonus</title>
  <link rel="stylesheet" href="styles/style_modifica_sconti.css">
  <style>
    .elimina {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        margin-top: 20px;
        width: 100%;
    }
    
    .elimina:hover {
        background-color: #c82333;
    }
    
    .input[readonly] {
        background-color: #f8f9fa;
        border-color: #dee2e6;
        color: #6c757d;
    }
    
    .card {
        max-width: 600px;
        margin: 0 auto;
    }
    
    .narrow {
        max-width: 400px;
    }
  </style>
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
       SEZIONE ELIMINA BONUS
  ========================== -->
  <h2 class="page-title">Elimina Bonus</h2>

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
  // Se è stato selezionato un bonus, recupero i suoi dati
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
          
          <!-- Scheda di sola lettura -->
          <form method="post" class="card">
            <!-- ID nascosto per sapere quale eliminare -->
            <input type="hidden" name="id_bonus" value="<?= htmlspecialchars($bonusSelezionato) ?>">

            <div class="col">
                <label class="label">Tipo Bonus</label>
                <input type="text" class="input" value="<?= htmlspecialchars($tipo) ?>" readonly>
            </div>

            <div class="col">
                <label class="label">Descrizione</label>
                <input type="text" class="input" value="<?= htmlspecialchars($descrizione) ?>" readonly>
            </div>

            <div class="col">
                <label class="label">Crediti</label>
                <input type="number" step="0.01" class="input" value="<?= htmlspecialchars($crediti) ?>" readonly>
            </div>

            <?php if ($tipo === "MagliaRetro"): ?>
                <div class="col">
                <label class="label">Stagione</label>
                <input type="number" class="input" value="<?= htmlspecialchars($stagione) ?>" readonly>
                </div>
            <?php endif; ?>

            <?php if ($tipo === "Reputazione"): ?>
                <div class="col">
                <label class="label">Reputazione Minima</label>
                <input type="number" class="input" value="<?= htmlspecialchars($repMin) ?>" readonly>
                </div>

                <div class="col">
                <label class="label">Limite utilizzi</label>
                <input type="number" class="input" value="<?= htmlspecialchars($limiti) ?>" readonly>
                </div>

                <div class="col">
                <label class="label">Data inizio</label>
                <input type="date" class="input" value="<?= htmlspecialchars($dataInizio) ?>" readonly>
                </div>
            <?php endif; ?>

            <div class="col">
                <label class="label">Attivo</label>
                <select class="input" disabled>
                <option value="true"  <?= $attivo==="true" ? "selected":"" ?>>Attivo</option>
                <option value="false" <?= $attivo==="false"? "selected":"" ?>>Non attivo</option>
                </select>
            </div>

            <button type="submit" name="delete_bonus" class="elimina"
                    onclick="return confirm('Sei sicuro di voler eliminare questo bonus?');">
                Elimina Bonus
            </button>
          </form>
          <?php
      }
  }
  ?>

  <!-- ==========================
       SEZIONE ELIMINA SCONTI
  ========================== -->
  <h2 class="page-title page-title--sconti">Elimina Sconti</h2>

  <!-- Selettore sconti -->
  <form method="post" class="card narrow">
    <label class="label" for="select_sconto">Seleziona Sconto:</label>
    <select id="select_sconto" name="select_sconto" class="input" onchange="this.form.submit()">
      <option value="">-- Seleziona --</option>
      <?php
      $domS = new DOMDocument();
      $domS->load($scontiFile);
      $sconti = $domS->getElementsByTagName("sconto");
      
      foreach ($sconti as $sconto) {
          $id   = $sconto->getElementsByTagName("ID")[0]->nodeValue ?? '';
          $tipo = $sconto->getElementsByTagName("tipo")[0]->nodeValue ?? '';
          $nome = $sconto->getElementsByTagName("nome")[0]->nodeValue ?? $id;
          
          $sel  = ($scontoSelezionato === $id) ? 'selected' : '';
          echo "<option value=\"".htmlspecialchars($id)."\" $sel>".htmlspecialchars($nome)." (".htmlspecialchars($tipo).")</option>";
      }
      ?>
    </select>
  </form>

  <?php
  if ($scontoSelezionato !== ""):
      foreach ($sconti as $sconto) {
          if ($sconto->getElementsByTagName("ID")[0]->nodeValue === $scontoSelezionato) {
              $tipo = $sconto->getElementsByTagName("tipo")[0]->nodeValue ?? '';
              $nome = $sconto->getElementsByTagName("nome")[0]->nodeValue ?? '';
              $attivo = $sconto->getElementsByTagName("attivo")[0]->nodeValue ?? 'false';
              $dataInizio = $sconto->getElementsByTagName("data_inizio")->length ? $sconto->getElementsByTagName("data_inizio")[0]->nodeValue : '';
              $dataFine = $sconto->getElementsByTagName("data_fine")->length ? $sconto->getElementsByTagName("data_fine")[0]->nodeValue : '';
              $percentualeFissa = $sconto->getElementsByTagName("percentualeFissa")->length ? $sconto->getElementsByTagName("percentualeFissa")[0]->nodeValue : '';
              $percentualeMax = $sconto->getElementsByTagName("percentualeMax")->length ? $sconto->getElementsByTagName("percentualeMax")[0]->nodeValue : '';
              ?>
              <!-- Scheda sconto di sola lettura -->
              <form method="post" class="card">
                <input type="hidden" name="id_sconto" value="<?= htmlspecialchars($scontoSelezionato) ?>">

                <div class="grid">
                    <div class="col">
                      <label class="label">Nome</label>
                      <input class="input" type="text" value="<?= htmlspecialchars($nome) ?>" readonly>
                    </div>

                    <div class="col">
                      <label class="label">Tipo Sconto</label>
                      <input class="input" type="text" value="<?= htmlspecialchars($tipo) ?>" readonly>
                    </div>

                    <div class="col">
                      <label class="label">Attivo</label>
                      <select class="input" disabled>
                        <option value="true"  <?= $attivo==="true" ? "selected":"" ?>>Attivo</option>
                        <option value="false" <?= $attivo==="false"? "selected":"" ?>>Non attivo</option>
                      </select>
                    </div>

                    <div class="col">
                      <label class="label">Data Inizio</label>
                      <input class="input" type="date" value="<?= htmlspecialchars($dataInizio) ?>" readonly>
                    </div>

                    <div class="col">
                      <label class="label">Data Fine</label>
                      <input class="input" type="date" value="<?= htmlspecialchars($dataFine) ?>" readonly>
                    </div>

                    <?php if ($percentualeFissa !== ''): ?>
                    <div class="col">
                      <label class="label">Percentuale Fissa</label>
                      <input class="input" type="number" step="0.01" value="<?= htmlspecialchars($percentualeFissa) ?>" readonly>
                    </div>
                    <?php endif; ?>

                    <?php if ($percentualeMax !== ''): ?>
                    <div class="col">
                      <label class="label">Percentuale Max</label>
                      <input class="input" type="number" step="0.01" value="<?= htmlspecialchars($percentualeMax) ?>" readonly>
                    </div>
                    <?php endif; ?>

                    <!-- Mostra informazioni aggiuntive in base al tipo -->
                    <?php if ($tipo === 'REPUTAZIONE'): ?>
                      <div class="col col-full">
                        <label class="label">Soglie Reputazione</label>
                        <?php
                        if ($sconto->getElementsByTagName("scontoReputazione")->length) {
                            $scontoRep = $sconto->getElementsByTagName("scontoReputazione")[0];
                            $soglie = $scontoRep->getElementsByTagName("soglia");
                            foreach ($soglie as $soglia) {
                                $maxRep = $soglia->getElementsByTagName("maxReputazione")[0]->nodeValue ?? '';
                                $percSconto = $soglia->getElementsByTagName("percentualeSconto")[0]->nodeValue ?? '';
                                echo "<div style='margin-bottom: 10px;'>";
                                echo "<input class='input' type='text' value='Max: $maxRep - Sconto: $percSconto%' readonly>";
                                echo "</div>";
                            }
                        }
                        ?>
                      </div>
                    <?php endif; ?>

                    <?php if ($tipo === 'ANZIANITA'): ?>
                      <?php
                      $periodoMensilita = $sconto->getElementsByTagName("periodoMensilita")->length ? $sconto->getElementsByTagName("periodoMensilita")[0]->nodeValue : '';
                      $incrementoPercentuale = $sconto->getElementsByTagName("incrementoPercentuale")->length ? $sconto->getElementsByTagName("incrementoPercentuale")[0]->nodeValue : '';
                      ?>
                      <?php if ($periodoMensilita !== ''): ?>
                      <div class="col">
                        <label class="label">Periodo Mensilità</label>
                        <input class="input" type="number" step="0.01" value="<?= htmlspecialchars($periodoMensilita) ?>" readonly>
                      </div>
                      <?php endif; ?>
                      <?php if ($incrementoPercentuale !== ''): ?>
                      <div class="col">
                        <label class="label">Incremento Percentuale</label>
                        <input class="input" type="number" step="0.01" value="<?= htmlspecialchars($incrementoPercentuale) ?>" readonly>
                      </div>
                      <?php endif; ?>
                    <?php endif; ?>

                    <!-- Condizioni Maglia -->
                    <?php
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
                    <?php if (!empty($stagioni)): ?>
                    <div class="col col-full">
                      <label class="label">Stagioni Applicabili</label>
                      <?php foreach ($stagioni as $stagione): ?>
                        <input class="input" type="text" value="<?= htmlspecialchars($stagione) ?>" readonly style="margin-bottom: 5px;">
                      <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($tipiMaglia)): ?>
                    <div class="col col-full">
                      <label class="label">Tipi Maglia Applicabili</label>
                      <?php foreach ($tipiMaglia as $tipoMaglia): ?>
                        <input class="input" type="text" value="<?= htmlspecialchars($tipoMaglia) ?>" readonly style="margin-bottom: 5px;">
                      <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" name="delete_sconto" class="elimina"
                        onclick="return confirm('Sei sicuro di voler eliminare questo sconto?');">
                    Elimina Sconto
                </button>
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