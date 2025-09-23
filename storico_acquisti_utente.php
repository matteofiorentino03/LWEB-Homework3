<?php
session_start();

/* Solo utente loggato */
if (!isset($_SESSION['Username'])) {
    header("Location: entering.html");
    exit();
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: homepage_user.php");
    exit();
}

require_once __DIR__ . '/connect.php';
try {
    $conn = db();
} catch (Throwable $e) {
    die("Errore DB: " . $e->getMessage());
}

$sqlUser = "SELECT ID, username FROM Utenti WHERE username = ?";
$stmt = $conn->prepare($sqlUser);
$stmt->bind_param("s", $_SESSION['Username']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) die("Utente non trovato.");
$userId = (int)$user['ID'];
$username = $user['username'];
$homepage_link = (isset($_SESSION['Ruolo']) && strtolower($_SESSION['Ruolo']) === 'admin')
    ? 'homepage_admin.php'
    : 'homepage_user.php';

// === CARICA I FILE XML ===
$xmlCompra = new DOMDocument();
$xmlCompra->load("xml/compra.xml");

$xmlMaglie = new DOMDocument();
$xmlMaglie->load("xml/maglie.xml");

$xmlPers = new DOMDocument();
$xmlPers->load("xml/maglie_personalizzate.xml");

$xmlGioc = new DOMDocument();
$xmlGioc->load("xml/maglie_giocatore.xml");

$xmlGiocatori = new DOMDocument();
$xmlGiocatori->load("xml/giocatori.xml");

// === INDICIZZA I DATI ===
function indicizzaPerID($xml, $tag, $idTag) {
    $diz = [];
    foreach ($xml->getElementsByTagName($tag) as $nodo) {
        $id = $nodo->getElementsByTagName($idTag)[0]->nodeValue;
        $diz[$id] = $nodo;
    }
    return $diz;
}

$maglie = indicizzaPerID($xmlMaglie, 'maglia', 'ID');
$pers = indicizzaPerID($xmlPers, 'maglia', 'ID');
$gioc = $xmlGioc->getElementsByTagName("personalizzazione");
$giocatori = indicizzaPerID($xmlGiocatori, 'giocatore', 'ID');

// === ESTRAI ORDINI UTENTE ===
$acquisti = [];
foreach ($xmlCompra->getElementsByTagName("ordine") as $ordine) {
    $idUtente = $ordine->getElementsByTagName("ID_Utente")[0]->nodeValue;
    if ((int)$idUtente !== $userId) continue;

    $idOrdine = $ordine->getElementsByTagName("ID")[0]->nodeValue;
    $idMaglia = $ordine->getElementsByTagName("ID_Maglia")[0]->nodeValue;
    $pagamento = $ordine->getElementsByTagName("pagamento_finale")[0]->nodeValue ?? 0;
    $indirizzo = $ordine->getElementsByTagName("indirizzo_consegna")[0]->nodeValue;
    $data = $ordine->getElementsByTagName("data_compra")[0]->nodeValue;

    $maglia = $maglie[$idMaglia] ?? null;
    if (!$maglia) continue;

    $tipo = $maglia->getElementsByTagName("tipo")[0]->nodeValue;
    $stagione = $maglia->getElementsByTagName("stagione")[0]->nodeValue;
    $taglia = $maglia->getElementsByTagName("taglia")[0]->nodeValue;

    // CONTROLLA se è personalizzata
    $pers_item = null;
    foreach ($pers as $p) {
        $id_m = $p->getElementsByTagName("ID_Maglia")[0]->nodeValue;
        if ($id_m == $idMaglia) {
            $pers_item = $p;
            break;
        }
    }

    // CONTROLLA se è maglia giocatore
    $gioc_item = null;
    foreach ($gioc as $g) {
        $id_m = $g->getElementsByTagName("ID_Maglia")[0]->nodeValue;
        if ($id_m == $idMaglia) {
            $gioc_item = $g;
            break;
        }
    }

    $descrizione = "$tipo • $stagione • $taglia";
    if ($pers_item) {
        $logo = $pers_item->getElementsByTagName("Logo")->length
              ? $pers_item->getElementsByTagName("Logo")[0]->nodeValue
              : '';
        $nome = $pers_item->getElementsByTagName("nome")[0]->nodeValue ?? '';
        $num = $pers_item->getElementsByTagName("num_maglia")[0]->nodeValue ?? '';
        $descrizione .= ($logo ? " • $logo" : "") . " • Personalizzata: $nome #$num";
    } elseif ($gioc_item) {
        $logo = $gioc_item->getElementsByTagName("Logo")->length
              ? $gioc_item->getElementsByTagName("Logo")[0]->nodeValue
              : '';
        $id_gioc = $gioc_item->getElementsByTagName("ID_Giocatore")[0]->nodeValue;
        $g = $giocatori[$id_gioc] ?? null;
        $nome = $g ? $g->getElementsByTagName("nome")[0]->nodeValue : '';
        $cognome = $g ? $g->getElementsByTagName("cognome")[0]->nodeValue : '';
        $descrizione .= ($logo ? " • $logo" : "") . " • $nome $cognome";
    }

    $acquisti[] = [
        'id' => $idOrdine,
        'descrizione' => $descrizione,
        'pagamento' => number_format((float)$pagamento, 2, ',', '.'),
        'indirizzo' => $indirizzo,
        'data' => $data
    ];
}
usort($acquisti, fn($a, $b) => strcmp($b['data'], $a['data']) ?: $b['id'] - $a['id']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>I miei acquisti</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="styles/style_storico_acquisti_u.css">
</head>
<body>
<header>
  <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
    <div class="logo-container">
      <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo">
    </div>
  </a>
  <h1><a href="<?= htmlspecialchars($homepage_link) ?>" style="color:inherit;text-decoration:none;">PLAYERBASE</a></h1>
  <div class="utente-container">
      <div class="logout"><a href="?logout=true"><p>Logout</p></a></div>
  </div>
</header>

<div class="main-container">
  <h2>I miei acquisti</h2>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Dettaglio Maglia</th>
          <th>Pagamento (€)</th>
          <th>Indirizzo</th>
          <th>Data acquisto</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
      <?php if (count($acquisti)): ?>
        <?php foreach ($acquisti as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['descrizione']) ?></td>
            <td><?= $r['pagamento'] ?></td>
            <td><?= htmlspecialchars($r['indirizzo']) ?></td>
            <td><?= htmlspecialchars($r['data']) ?></td>
            <td>
              <a class="btn-print" href="stampa_ordine.php?id=<?= (int)$r['id'] ?>" target="_blank">Stampa PDF</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="5" style="text-align:center;color:#666;font-style:italic;">Nessun acquisto effettuato.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
</footer>
</body>
</html>