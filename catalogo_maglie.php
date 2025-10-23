<?php
session_start();

/* ===== Header logic ===== */
$is_logged  = isset($_SESSION['Username']);
$ruolo      = $is_logged ? strtolower($_SESSION['Ruolo']) : '';
$is_admin   = ($ruolo === 'amministratore');
$homepage_link = $is_admin ? 'homepage_admin.php' : 'homepage_user.php';

/*  Logout */
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

$config = require 'config.php';
$dsn = "mysql:host={$config['host']};dbname={$config['name']};charset=utf8";
$pdo = new PDO($dsn, $config['user'], $config['pass']);
$id_utente = (int)($_SESSION['ID_Utente'] ?? -1);
$stmt = $pdo->query("SELECT reputazione FROM utenti where ID = $id_utente");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$reputazione = 0;
if ($row && isset($row['reputazione'])) {
    $reputazione = (int)$row['reputazione'];
    $_SESSION['Reputazione'] = $reputazione;
}
// === Caricamento XML ===
$xml_path = 'xml/maglie.xml';
if (!file_exists($xml_path)) {
    die("Errore: File XML non trovato.");
}
$xml = simplexml_load_file($xml_path);


// === Raggruppamento maglie per (tipo + stagione) ===
$maglie_raggruppate = [];

foreach ($xml->maglia as $maglia) {
    $tipo     = (string) $maglia->tipo;
    $stagione = (string) $maglia->stagione;
    $taglia   = (string) $maglia->taglia;
    $costo    = (float)  $maglia->costo_fisso;
    $img      = (string) $maglia->path_immagine;
    $key = $tipo . '|' . $stagione;

    if (!isset($maglie_raggruppate[$key])) {
        $maglie_raggruppate[$key] = [
            'tipo'     => $tipo,
            'stagione' => $stagione,
            'taglie'   => [],
            'prezzi'   => [],
            'immagini' => [],
        ];
    }

    $maglie_raggruppate[$key]['taglie'][]   = $taglia;
    $maglie_raggruppate[$key]['prezzi'][]   = $costo;
    if ($img !== '') {
        $maglie_raggruppate[$key]['immagini'][] = $img;
    }
}

$tipiMagliaScontati = [];
$scontiRetro = [];

if (file_exists('xml/sconti.xml')) {
    $scontiDom = new DOMDocument();
    $scontiDom->load('xml/sconti.xml');
    $doc = new DOMXPath($scontiDom);
    $scontiNodes = $doc->query('//sconto');

    $oggi = new DateTime();

    foreach ($scontiNodes as $sconto) {
        // Verifica se lo sconto è attivo
        $attivo = strtolower(trim($doc->evaluate('string(attivo)', $sconto)));
        if ($attivo !== 'true') continue;

        // Verifica validità temporale
        $inizioNode = $sconto->getElementsByTagName("data_inizio")->item(0);
        $fineNode = $sconto->getElementsByTagName("data_fine")->item(0);

        $valido = true;
        if ($inizioNode && $fineNode) {
            $inizio = new DateTime($inizioNode->nodeValue);
            $fine = new DateTime($fineNode->nodeValue);
            $valido = ($oggi >= $inizio && $oggi <= $fine);
        } elseif ($inizioNode) {
            $inizio = new DateTime($inizioNode->nodeValue);
            $valido = ($oggi >= $inizio);
        } elseif ($fineNode) {
            $fine = new DateTime($fineNode->nodeValue);
            $valido = ($oggi <= $fine);
        }

        if (!$valido) continue;

        // Estrai tipo e percentuale
        $tipoSconto = strtoupper(trim($doc->evaluate('string(tipo)', $sconto)));
        $percentuale = floatval($doc->evaluate('string(percentualeFissa)', $sconto));

        if ($tipoSconto === 'TIPO_MAGLIA') {
            $tipiNodes = $doc->evaluate('condizioniMaglia/tipo', $sconto);
            foreach ($tipiNodes as $t) {
                $tipoMaglia = strtoupper(trim($t->nodeValue));
                $tipiMagliaScontati[$tipoMaglia] = $percentuale;
            }
        } elseif ($tipoSconto === 'RETRO') {
            $stagioniNodes = $doc->evaluate('condizioniMaglia/stagione', $sconto);
            $tipiNodes = $doc->evaluate('condizioniMaglia/tipo', $sconto);
            foreach ($stagioniNodes as $stagioneNode) {
                $stagione = trim($stagioneNode->nodeValue);
                if ($stagione === '') continue;

                foreach ($tipiNodes as $tipoNode) {
                    $tipo = strtoupper(trim($tipoNode->nodeValue));
                    if ($tipo === '') continue;

                    if (!isset($scontiRetro[$stagione])) {
                        $scontiRetro[$stagione] = [];
                    }

                    // Salva la percentuale per la combinazione stagione + tipo
                    $scontiRetro[$stagione][$tipo] = $percentuale;
                }
            }
        }
    }
}


/* === FILTRI === */
$search   = strtolower(trim($_GET['search'] ?? ''));
$stagione_filtro = $_GET['stagione'] ?? '';
$tipo_filtro = strtolower($_GET['tipo'] ?? '');
$ordine = $_GET['ordine_prezzo'] ?? '';

/* === APPLICAZIONE FILTRI === */
$maglie_filtrate = array_filter($maglie_raggruppate, function($m) use ($search, $stagione_filtro, $tipo_filtro) {
    $nome = strtolower($m['tipo'].' '.$m['stagione']);
    if ($search && !str_contains($nome, $search)) return false;
    if ($stagione_filtro && $m['stagione'] !== $stagione_filtro) return false;
    if ($tipo_filtro && strtolower($m['tipo']) !== $tipo_filtro) return false;
    return true;
});

/* === Ordinamento per prezzo === */
if ($ordine === 'asc' || $ordine === 'desc') {
    usort($maglie_filtrate, function($a, $b) use ($ordine) {
        $pa = min($a['prezzi']);
        $pb = min($b['prezzi']);
        return $ordine === 'asc' ? $pa <=> $pb : $pb <=> $pa;
    });
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <title>Catalogo Maglie</title>
  <link rel="stylesheet" href="styles/style_catalogo_maglia.css" />
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
    <?php if ($is_logged): ?>
      <div class="logout"><a href="?logout=true">Logout</a></div>
    <?php else: ?>
      <div class="logout"><a href="entering.html">Login/Registrati</a></div>
    <?php endif; ?>
  </div>
</header>

<main class="page">
  <h2 class="page-title">Catalogo maglie</h2>

  <!--  SEZIONE FILTRI -->
  <form method="get" class="filter-form">
    <div class="filter-group">
      <!--  Ricerca per nome -->
      <div class="filter-item">
        <label for="search">Cerca maglia:</label>
        <input type="text" name="search" id="search" placeholder="Es. Casa 2025/26"
               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
      </div>

      <!--  Ordina per prezzo -->
      <div class="filter-item">
        <label for="ordine_prezzo">Ordina per prezzo:</label>
        <select name="ordine_prezzo" id="ordine_prezzo">
          <option value="">-- Nessun ordine --</option>
          <option value="asc" <?= (($_GET['ordine_prezzo'] ?? '') === 'asc') ? 'selected' : '' ?>>Crescente</option>
          <option value="desc" <?= (($_GET['ordine_prezzo'] ?? '') === 'desc') ? 'selected' : '' ?>>Decrescente</option>
        </select>
      </div>

      <!--  Filtro per stagione -->
      <div class="filter-item">
        <label for="stagione">Stagione:</label>
        <select name="stagione" id="stagione">
          <option value="">-- Tutte --</option>
          <?php
            $stagioni = array_unique(array_map(fn($m) => $m['stagione'], $maglie_raggruppate));
            sort($stagioni);
            foreach ($stagioni as $st):
          ?>
            <option value="<?= htmlspecialchars($st) ?>" <?= (($_GET['stagione'] ?? '') === $st) ? 'selected' : '' ?>>
              <?= htmlspecialchars($st) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!--  Filtro per tipo -->
      <div class="filter-item">
        <label for="tipo">Tipo:</label>
        <select name="tipo" id="tipo">
          <option value="">-- Tutti --</option>
          <?php
            $tipi = array_unique(array_map(fn($m) => strtolower($m['tipo']), $maglie_raggruppate));
            foreach ($tipi as $t):
          ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= (($_GET['tipo'] ?? '') === $t) ? 'selected' : '' ?>>
              <?= ucfirst(htmlspecialchars($t)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <!--  Pulsanti -->
      <div class="filter-buttons">
        <button type="submit" class="btn-filtra">Applica</button>
        <a href="catalogo_maglie.php" class="btn-reset">Reset</a>
      </div>
    </div>
  </form>
  <!--  FINE FILTRI -->

  <?php if (!empty($maglie_filtrate)): ?>
    <section class="cards">
      <?php foreach ($maglie_filtrate as $group): ?>
        <?php
          $tipo     = ucfirst($group['tipo']);
          $stagione = htmlspecialchars($group['stagione']);
          $taglie = array_unique($group['taglie']);
          $taglie_ordinato = array_intersect(['S','M','L','XL'], $taglie);
          $taglie_str = implode(', ', $taglie_ordinato);
          $prezzi = $group['prezzi'];
          $prezzi_scontati = [];
          $sconto_maglia = 0.0;
          foreach ($group['prezzi'] as $idx => $prezzo_base) {
          if (array_key_exists(strtoupper($tipo), $tipiMagliaScontati)) {
          $sconto_maglia_tipo = $tipiMagliaScontati[strtoupper($tipo)] / 100.0;
          } else {
            $sconto_maglia_tipo = 0.0;
          }
          
          if(array_key_exists($stagione, $scontiRetro) && array_key_exists(strtoupper($tipo), $scontiRetro[$stagione])) {
              $sconto_maglia_retro = $scontiRetro[$stagione][strtoupper($tipo)] / 100.0;
          } else {
              $sconto_maglia_retro = 0.0;
          }

          $sconto_maglia = max($sconto_maglia_tipo, $sconto_maglia_retro);

          if ($sconto_maglia_retro > $sconto_maglia_tipo) {
              $tipo_sconto_applicato = "Promo Maglie Retrò {$scontiRetro[$stagione][strtoupper($tipo)]}%";
          } elseif ($sconto_maglia_tipo > 0) {
              $tipo_sconto_applicato = "Promo per Tipo Maglia {$tipiMagliaScontati[strtoupper($tipo)]}%";
          } else {
              $tipo_sconto_applicato = '';
          }
          $prezzi_scontati[] = $prezzo_base * (1 - $sconto_maglia);
        }
          $prezzo_min = min($group['prezzi']);
          $prezzo_min_scontato = min($prezzi_scontati);

          // Controlla se c'è uno sconto applicabile
          $maglia_scontata = $prezzo_min_scontato < $prezzo_min;

          if ($maglia_scontata) {
              // Mostra sia prezzo originale che scontato
                  $prezzo = '<span class="prezzo-originale">'.number_format($prezzo_min,2,',','.').' €</span> ';
                  $prezzo .= '<span class="prezzo-scontato">'.number_format($prezzo_min_scontato,2,',','.').' €</span>';
              } else {
              // Mostra solo prezzo base
                  $prezzo = number_format($prezzo_min, 2, ',', '.').' €' ;
              }
          $img = isset($group['immagini'][0]) ? $group['immagini'][0] : '';
          $img_abs = $img ? (__DIR__ . '/' . str_replace('\\','/',$img)) : '';
          $has_img = $img && is_file($img_abs);
        ?>
        <article class="card">
          <div class="card__media">
            <?php
              //  Salva in sessione tipo e percentuale di sconto per la maglia
              $_SESSION['Tipo_sconto'] = ($sconto_maglia_retro > $sconto_maglia_tipo) ? 'RETRO' : 
                                        (($sconto_maglia_tipo > 0) ? 'TIPO_MAGLIA' : '');
              $_SESSION['Percentuale_sconto'] = $sconto_maglia;
              ?>
            <?php if ($has_img): ?>
              <form action="compra_maglia.php" method="post" style="display:inline;">
                  <input type="hidden" name="tipo" value="<?= htmlspecialchars($group['tipo']) ?>">
                  <input type="hidden" name="stagione" value="<?= htmlspecialchars($stagione) ?>">
                  <input type="hidden" name="Tipo_sconto" 
                        value="<?= ($sconto_maglia_retro > $sconto_maglia_tipo) 
                                    ? 'RETRO' 
                                    : (($sconto_maglia_tipo > 0) ? 'TIPO_MAGLIA' : '') ?>">
                  <input type="hidden" name="Percentuale_sconto" value="<?= htmlspecialchars($sconto_maglia) ?>">
                  <button type="submit" style="border:none; background:none; padding:0;">
                      <img src="<?= htmlspecialchars($img) ?>" alt="Maglia <?= htmlspecialchars($tipo) ?> <?= htmlspecialchars($stagione) ?>">
                  </button>
              </form>
            <?php else: ?>
              <div class="placeholder"><span>Immagine non disponibile</span></div>
            <?php endif; ?>
            <div class="badge badge--tipo"><?= $tipo ?></div>
            <div class="badge badge--stagione"><?= $stagione ?></div>
          </div>

          <div class="card__body">
            <h3 class="card__title"><?= $tipo ?> • <?= $stagione ?></h3>

            <div class="field">
              <span class="field__label">Taglie:</span>
              <span class="chips"><?= $taglie_str ?: '-' ?></span>
            </div>
            <div class="field">
              <span class="field__label">Prezzo:</span>
              <span class="price"><?= $prezzo ?></span>
            </div>
            <?php if($tipo_sconto_applicato): ?>
            <div class="field">
              <span class="field__label">Tipo sconto applicato:</span>
              <span class="chips"><?= $tipo_sconto_applicato ?: '-' ?></span>
            </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php else: ?>
    <p class="empty">Nessuna maglia trovata con i filtri selezionati.</p>
  <?php endif; ?>
</main>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
  <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
</footer>
</body>
</html>