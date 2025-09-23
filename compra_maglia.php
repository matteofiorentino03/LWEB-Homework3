<?php
session_start();
if (!isset($_SESSION['Username'])) {
    header("Location: entering.html");
    exit();
}

require_once __DIR__ . '/connect.php';
try {
    $conn = db();
} catch (Throwable $e) {
    die("Errore DB: " . $e->getMessage());
}

$sqlUser = "SELECT ID, username, crediti, ruolo FROM Utenti WHERE username = ?";
$stmt = $conn->prepare($sqlUser);
$stmt->bind_param("s", $_SESSION['Username']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) die("Utente non trovato.");

$userId = (int)$user['ID'];
$username = $user['username'];
$crediti = (float)$user['crediti'];
$is_admin = (strtolower($user['ruolo']) === 'admin');
$homepage_link = $is_admin ? 'homepage_admin.php' : 'homepage_user.php';

$tipo = $_REQUEST['tipo'] ?? null;
$stagione = $_REQUEST['stagione'] ?? null;
if (!$tipo || !$stagione) {
    echo "<p style='padding:20px'>Errore: dati mancanti. Torna al <a href='catalogo_maglie.php'>catalogo</a>.</p>";
    exit();
}

$xmlMaglie = new DOMDocument();
$xmlMaglie->load("xml/maglie.xml");
$xpath = new DOMXPath($xmlMaglie);
$maglie = [];
$taglie_disponibili = [];

foreach ($xpath->query("/maglie/maglia[tipo='$tipo' and stagione='$stagione']") as $node) {
    $id = $node->getElementsByTagName("ID")[0]->nodeValue;
    $taglia = $node->getElementsByTagName("taglia")[0]->nodeValue;
    $magliaData = [
        'ID' => $id,
        'tipo' => $tipo,
        'stagione' => $stagione,
        'taglia' => $taglia,
        'Sponsor' => $node->getElementsByTagName("Sponsor")[0]->nodeValue ?? '',
        'descrizione_maglia' => $node->getElementsByTagName("descrizione_maglia")[0]->nodeValue ?? '',
        'costo_fisso' => $node->getElementsByTagName("costo_fisso")[0]->nodeValue,
        'path_immagine' => $node->getElementsByTagName("path_immagine")[0]->nodeValue ?? ''
    ];
    $maglie[] = $magliaData;
    $taglie_disponibili[$taglia] = $id;
}

$ordine = ['XS','S','M','L','XL','XXL','XXXL'];
uksort($taglie_disponibili, fn($a, $b) => array_search($a, $ordine) <=> array_search($b, $ordine));

if (!$maglie) {
    echo "<p style='padding:20px'>Nessuna maglia trovata. Torna al <a href='catalogo_maglie.php'>catalogo</a>.</p>";
    exit();
}

$xmlGiocatori = new DOMDocument();
$xmlGiocatori->load("xml/giocatori.xml");
$giocatori = [];
foreach ($xmlGiocatori->getElementsByTagName("giocatore") as $g) {
    $giocatori[] = [
        'ID' => $g->getElementsByTagName("ID")[0]->nodeValue,
        'nome' => $g->getElementsByTagName("nome")[0]->nodeValue,
        'cognome' => $g->getElementsByTagName("cognome")[0]->nodeValue,
        'num_maglia' => $g->getElementsByTagName("num_maglia")[0]->nodeValue
    ];
}

// === LOGICA DI ACQUISTO ===
$msg = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'acquista') {
    $ID_Maglia_selezionata = $_POST['maglia_id'] ?? '';
    $taglia_sel = $_POST['taglia'] ?? '';
    $indirizzo = trim($_POST['indirizzo'] ?? '');
    $mod_tipo = $_POST['mod_tipo'] ?? 'none';

    $logo = $_POST['logo'] ?? '';
    $id_g = $_POST['id_giocatore'] ?? '';
    $nome_p = trim($_POST['nome_pers'] ?? '');
    $num_p = trim($_POST['num_pers'] ?? '');

    $magliaScelta = null;
    foreach ($maglie as $m) {
        if ($m['ID'] === $ID_Maglia_selezionata && $m['taglia'] === $taglia_sel) {
            $magliaScelta = $m;
            break;
        }
    }

    if (!$magliaScelta) {
        $msg = "Maglia non valida.";
    } elseif ($indirizzo === '') {
        $msg = "Inserisci l'indirizzo di consegna.";
    } else {
        $supplemento = 0;
        if ($mod_tipo === 'giocatore') {
            if (!$id_g) {
                $msg = "Seleziona il giocatore.";
            } else {
                $supplemento = $logo ? 15 : 10;
            }
        } elseif ($mod_tipo === 'personalizzata') {
            if ($nome_p === '' || $num_p === '') {
                $msg = "Per la personalizzata servono nome e numero.";
            } else {
                $supplemento = $logo ? 20 : 15;
            }
        }

        if ($msg === '') {
            $base = (float)$magliaScelta['costo_fisso'];
            $totale = $base + $supplemento;

            if ($crediti < $totale) {
                $msg = "Crediti insufficienti. Disponibili: " . number_format($crediti, 2, ',', '.') . " €";
            } else {
                $oggi = date('Y-m-d');

                // === Scrittura in compra.xml ===
                $xmlCompra = new DOMDocument();
                $xmlCompra->preserveWhiteSpace = false;
                $xmlCompra->formatOutput = true;
                $xmlCompra->load("xml/compra.xml");
                $root = $xmlCompra->documentElement;

                $newId = $xmlCompra->getElementsByTagName("ordine")->length + 1;

                $n = $xmlCompra->createElement("ordine");
                $n->appendChild($xmlCompra->createElement("ID", $newId));
                $n->appendChild($xmlCompra->createElement("ID_Utente", $userId));
                $n->appendChild($xmlCompra->createElement("ID_Maglia", $ID_Maglia_selezionata));
                $n->appendChild($xmlCompra->createElement("pagamento_finale", $totale));
                $n->appendChild($xmlCompra->createElement("indirizzo_consegna", $indirizzo));
                $n->appendChild($xmlCompra->createElement("data_compra", $oggi));
                $root->appendChild($n);
                $xmlCompra->save("xml/compra.xml");

                // === Maglia giocatore ===
                if ($mod_tipo === 'giocatore') {
                    $xmlG = new DOMDocument();
                    $xmlG->preserveWhiteSpace = false;
                    $xmlG->formatOutput = true;
                    $xmlG->load("xml/maglie_giocatore.xml");
                    $rootG = $xmlG->documentElement;

                    $newId = $xmlG->getElementsByTagName("personalizzazione")->length + 1;
                    $e = $xmlG->createElement("personalizzazione");
                    $e->appendChild($xmlG->createElement("ID", $newId));
                    $e->appendChild($xmlG->createElement("Supplemento", $supplemento));
                    if (!empty($logo)) {
                        $e->appendChild($xmlG->createElement("Logo", $logo));
                    }
                    $e->appendChild($xmlG->createElement("ID_Giocatore", $id_g));
                    $e->appendChild($xmlG->createElement("ID_Maglia", $ID_Maglia_selezionata));
                    $rootG->appendChild($e);
                    $xmlG->save("xml/maglie_giocatore.xml");

                } elseif ($mod_tipo === 'personalizzata') {
                    $xmlP = new DOMDocument();
                    $xmlP->preserveWhiteSpace = false;
                    $xmlP->formatOutput = true;
                    $xmlP->load("xml/maglie_personalizzate.xml");
                    $rootP = $xmlP->documentElement;

                    $newId = $xmlP->getElementsByTagName("maglia")->length + 1;
                    $e = $xmlP->createElement("maglia");
                    $e->appendChild($xmlP->createElement("ID", $newId));
                    $e->appendChild($xmlP->createElement("ID_Maglia", $ID_Maglia_selezionata));
                    if (!empty($logo)) {
                        $e->appendChild($xmlP->createElement("Logo", $logo));
                    }
                    $e->appendChild($xmlP->createElement("supplemento", $supplemento));
                    $e->appendChild($xmlP->createElement("nome", $nome_p));
                    $e->appendChild($xmlP->createElement("num_maglia", $num_p));
                    $rootP->appendChild($e);
                    $xmlP->save("xml/maglie_personalizzate.xml");
                }

                // === Aggiorna crediti ===
                $upd = $conn->prepare("UPDATE Utenti SET crediti = crediti - ? WHERE ID=?");
                $upd->bind_param("di", $totale, $userId);
                $upd->execute();
                $upd->close();

                $ok = true;
                $msg = "Acquisto completato! Totale: " . number_format($totale, 2, ',', '.') . " €.";
                $crediti -= $totale;
            }
        }
    }
}

$prima = $maglie[0];
$descrizione = $prima['descrizione_maglia'];
$sponsor = $prima['Sponsor'];
$img_default = $prima['path_immagine'];
$base_default = (float)$prima['costo_fisso'];
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars(ucfirst($tipo)." • ".$stagione) ?> | Acquisto</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="styles/style_compra_maglia.css" />
</head>
<body>
<header>
  <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
    <div class="logo-container"><img src="img/AS_Roma_Logo_2017.svg.png" class="logo" alt="Logo"></div>
  </a>
  <h1><a href="<?= htmlspecialchars($homepage_link) ?>" style="color:inherit;text-decoration:none;">PLAYERBASE</a></h1>
  <div class="utente-container">
    <div class="logout"><a href="?logout=true">Logout</a></div>
  </div>
</header>

<div class="page">
  <h2 class="title"><?= htmlspecialchars(ucfirst($tipo)." • ".$stagione) ?></h2>

  <?php if ($msg): ?>
    <div class="alert <?= $ok ? 'alert-ok':'alert-err' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="post" id="acquistoForm">
    <input type="hidden" name="azione" value="acquista">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
    <input type="hidden" name="stagione" value="<?= htmlspecialchars($stagione) ?>">
    <input type="hidden" name="maglia_id" id="maglia_id" value="<?= (int)$prima['ID'] ?>">
    <input type="hidden" name="base_price" id="base_price" value="<?= (float)$prima['costo_fisso'] ?>">

    <div class="grid">
      <!-- Immagine -->
      <div class="figure">
        <?php if ($img_default && is_file($img_default)): ?>
          <img id="imgMaglia" src="<?= htmlspecialchars($img_default) ?>" alt="Maglia">
        <?php else: ?>
          <img id="imgMaglia" src="img/placeholder.png" alt="Maglia">
        <?php endif; ?>
      </div>

      <!-- Dettagli -->
      <div class="details">
        <p class="lead"><?= htmlspecialchars($descrizione) ?></p>
        <?php if ($sponsor): ?><p class="meta"><strong>Sponsor:</strong> <?= htmlspecialchars($sponsor) ?></p><?php endif; ?>

        <div class="row">
          <div class="price">Prezzo: <span id="basePriceTxt"><?= number_format($base_default,2,',','.') ?> €</span></div>
          <div style="margin-left:auto; min-width:220px">
            <label for="taglia"><strong>Taglia</strong></label>
            <select name="taglia" id="taglia" required>
              <option value="" hidden>Seleziona taglia</option>
              <?php foreach ($maglie as $v): ?>
                <option 
                  value="<?= htmlspecialchars($v['taglia']) ?>"
                  data-id="<?= (int)$v['ID'] ?>"
                  data-price="<?= (float)$v['costo_fisso'] ?>"
                  data-img="<?= htmlspecialchars($v['path_immagine']) ?>"
                  <?= $v['ID']==$prima['ID'] ? 'selected':'' ?>
                ><?= htmlspecialchars($v['taglia']) ?> — € <?= number_format($v['costo_fisso'],2,',','.') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Personalizzazione -->
        <div class="card">
          <div class="opt-row">
            <span class="badge">Personalizzazione</span>
            <label><input type="radio" name="mod_tipo" value="none" checked> Nessuna</label>
            <label><input type="radio" name="mod_tipo" value="giocatore"> Maglia giocatore</label>
            <label><input type="radio" name="mod_tipo" value="personalizzata"> Personalizzata</label>
          </div>

          <!-- GIOCATORE -->
          <div id="box_giocatore" style="display:none; margin-top:8px">
            <div class="opt-row">
              <div style="flex:1">
                <label for="id_giocatore"><strong>Giocatore</strong></label>
                <select name="id_giocatore" id="id_giocatore">
                  <option value="">Seleziona giocatore</option>
                  <?php foreach ($giocatori as $g): ?>
                    <option value="<?= (int)$g['ID'] ?>">
                      <?= htmlspecialchars($g['cognome']." ".$g['nome']." #".$g['num_maglia']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div style="flex:1">
                <label for="logo_g"><strong>Logo (opzionale)</strong></label>
                <select name="logo" id="logo_g">
                  <option value="">Nessuno</option>
                  <option>SERIE A</option>
                  <option>CHAMPIONS LEAGUE</option>
                  <option>EUROPA LEAGUE</option>
                  <option>COPPA ITALIA</option>
                  <option>CONFERENCE LEAGUE</option>
                </select>
              </div>
            </div>
            <p class="helper">Supplemento: <strong>+10€</strong> (solo giocatore) • <strong>+15€</strong> (con logo).</p>
          </div>

          <!-- PERSONALIZZATA -->
          <div id="box_pers" style="display:none; margin-top:8px">
            <div class="opt-row">
              <div style="flex:1">
                <label for="nome_pers"><strong>Nome</strong></label>
                <input type="text" name="nome_pers" id="nome_pers" maxlength="50" placeholder="Es. ROSSI">
              </div>
              <div style="flex:1">
                <label for="num_pers"><strong>Numero maglia</strong></label>
                <input type="number" name="num_pers" id="num_pers" min="1" max="99" placeholder="1-99">
              </div>
            </div>
            <div class="opt-row">
              <div style="flex:1">
                <label for="logo_p"><strong>Logo (opzionale)</strong></label>
                <select name="logo" id="logo_p">
                  <option value="">Nessuno</option>
                  <option>SERIE A</option>
                  <option>CHAMPIONS LEAGUE</option>
                  <option>EUROPA LEAGUE</option>
                  <option>COPPA ITALIA</option>
                  <option>CONFERENCE LEAGUE</option>
                </select>
              </div>
            </div>
            <p class="helper">Supplemento: <strong>+15€</strong> (nome+numero) • <strong>+20€</strong> (con logo).</p>
          </div>
        </div>

        <!-- Totale -->
        <div class="total">
          <div class="line">
            <span>Totale</span>
            <span id="totaleTxt"><?= number_format($base_default,2,',','.') ?> €</span>
          </div>
          <input class="addr" type="text" name="indirizzo" id="indirizzo" placeholder="Indirizzo di consegna" required>
          <p class="note">Crediti disponibili: <strong><?= number_format($crediti,2,',','.') ?> €</strong></p>
          <br>
          <button class="btn" type="submit">Acquista</button>
        </div>
      </div>
    </div>
  </form>
</div>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
</footer>

<script>
// ===== Gestione cambio taglia
const selectTaglia = document.getElementById('taglia');
const img = document.getElementById('imgMaglia');
const basePriceTxt = document.getElementById('basePriceTxt');
const basePriceInp = document.getElementById('base_price');
const magliaIdInp  = document.getElementById('maglia_id');
const totaleTxt    = document.getElementById('totaleTxt');

function getModTipo(){ 
  const r = document.querySelector('input[name="mod_tipo"]:checked'); 
  return r ? r.value : 'none';
}
function getLogoSelected(){
  const lgG = document.getElementById('logo_g');
  const lgP = document.getElementById('logo_p');
  if (!lgG || !lgP) return '';
  return (getModTipo()==='giocatore') ? lgG.value : (getModTipo()==='personalizzata' ? lgP.value : '');
}
function calcSupplemento(){
  const mod = getModTipo();
  if (mod==='giocatore'){
    const g = document.getElementById('id_giocatore').value;
    if(!g) return 0;
    return getLogoSelected() ? 15 : 10;
  } else if (mod==='personalizzata'){
    const n = document.getElementById('nome_pers').value.trim();
    const m = document.getElementById('num_pers').value.trim();
    if(!n || !m) return 0;
    return getLogoSelected() ? 20 : 15;
  }
  return 0;
}
function recalc(){
  const base = parseFloat(basePriceInp.value||'0');
  const sup  = calcSupplemento();
  const tot  = base + sup;
  basePriceTxt.textContent = base.toLocaleString('it-IT',{minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
  totaleTxt.textContent    = tot.toLocaleString('it-IT',{minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
}
selectTaglia.addEventListener('change', e=>{
  const opt = selectTaglia.options[selectTaglia.selectedIndex];
  magliaIdInp.value   = opt.getAttribute('data-id');
  basePriceInp.value  = opt.getAttribute('data-price');
  const imgPath       = opt.getAttribute('data-img') || '';
  if (imgPath) img.src = imgPath;
  recalc();
});

// toggle box
const boxG = document.getElementById('box_giocatore');
const boxP = document.getElementById('box_pers');
document.querySelectorAll('input[name="mod_tipo"]').forEach(r=>{
  r.addEventListener('change', ()=>{
    boxG.style.display = (r.value==='giocatore' && r.checked) ? 'block' : 'none';
    boxP.style.display = (r.value==='personalizzata' && r.checked) ? 'block' : 'none';
    recalc();
  });
});

// aggiornamenti dinamici
['id_giocatore','logo_g','nome_pers','num_pers','logo_p'].forEach(id=>{
  const el = document.getElementById(id);
  if (el) el.addEventListener('input', recalc);
  if (el) el.addEventListener('change', recalc);
});
recalc();
</script>
</body>
</html>