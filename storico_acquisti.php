<?php
session_start();
require_once __DIR__ . '/connect.php';

//  Solo gestore
if (!isset($_SESSION['Username']) || strtolower($_SESSION['Ruolo'] ?? '') !== 'gestore') {
    header("Location: entering.html");
    exit();
}

//  Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

$homepage_link = "homepage_gestore.php";
$conn = db();

/* === CARICAMENTO XML === */
$xmlCompra = new DOMDocument(); $xmlCompra->load("xml/compra.xml");
$xmlMaglie = new DOMDocument(); $xmlMaglie->load("xml/maglie.xml");
$xmlPers = new DOMDocument(); $xmlPers->load("xml/maglie_personalizzate.xml");
$xmlGioc = new DOMDocument(); $xmlGioc->load("xml/maglie_giocatore.xml");
$xmlGiocatori = new DOMDocument(); $xmlGiocatori->load("xml/giocatori.xml");

/* === ELENCO UTENTI CLIENTI === */
$utenti = [];
$stmt = $conn->prepare("SELECT ID, username FROM Utenti WHERE LOWER(ruolo) = 'cliente' ORDER BY username ASC");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $utenti[$row['ID']] = $row['username'];
}
$stmt->close();

/* === FILTRO UTENTE === */
$id_utente_filtro = $_GET['utente'] ?? '';
$id_utente_filtro = ctype_digit($id_utente_filtro) ? (int)$id_utente_filtro : '';

/* === FUNZIONE TROVA MAGLIA === */
function trovaMaglia($xmlMaglie, $id) {
    foreach ($xmlMaglie->getElementsByTagName("maglia") as $m) {
        if ((int)$m->getElementsByTagName("ID")[0]->nodeValue === (int)$id) {
            return [
                'tipo' => $m->getElementsByTagName("tipo")[0]->nodeValue ?? '',
                'stagione' => $m->getElementsByTagName("stagione")[0]->nodeValue ?? '',
                'taglia' => $m->getElementsByTagName("taglia")[0]->nodeValue ?? '',
            ];
        }
    }
    return null;
}

/* === COSTRUZIONE LISTA ACQUISTI === */
$acquisti = [];

foreach ($xmlCompra->getElementsByTagName("ordine") as $ordine) {
    $idUtente = (int)$ordine->getElementsByTagName("ID_Utente")[0]->nodeValue;
    if ($id_utente_filtro && $idUtente !== $id_utente_filtro) continue;

    $idOrdine = (int)$ordine->getElementsByTagName("ID")[0]->nodeValue;
    $idMaglia = (int)$ordine->getElementsByTagName("ID_Maglia")[0]->nodeValue;
    $pagamento = (float)($ordine->getElementsByTagName("pagamento_finale")[0]->nodeValue ?? 0);
    $indirizzo = $ordine->getElementsByTagName("indirizzo_consegna")[0]->nodeValue ?? '';
    $data = $ordine->getElementsByTagName("data_compra")[0]->nodeValue ?? '';

    // Username dal DB
    $stmt = $conn->prepare("SELECT username FROM Utenti WHERE ID = ?");
    $stmt->bind_param("i", $idUtente);
    $stmt->execute();
    $res = $stmt->get_result();
    $username = ($r = $res->fetch_assoc()) ? $r['username'] : 'N/D';
    $stmt->close();

    // Trova maglia base
    $maglia = trovaMaglia($xmlMaglie, $idMaglia);
    if (!$maglia) continue;

    $descrizione = "{$maglia['tipo']} • {$maglia['stagione']} • {$maglia['taglia']}";

    /* --- Maglia Personalizzata --- */
    foreach ($xmlPers->getElementsByTagName("maglia") as $p) {
        $idM = (int)$p->getElementsByTagName("ID_Maglia")[0]->nodeValue ?? 0;
        $idOrd = (int)$p->getElementsByTagName("ID")[0]->nodeValue ?? 0;
        if ($idM === $idMaglia && $idOrd === $idOrdine) {
            $nome = $p->getElementsByTagName("nome")[0]->nodeValue ?? '';
            $num = $p->getElementsByTagName("num_maglia")[0]->nodeValue ?? '';
            $logo = $p->getElementsByTagName("Logo")->length ? $p->getElementsByTagName("Logo")[0]->nodeValue : '';
            if ($logo) $descrizione .= " • $logo";
            $descrizione .= " • Personalizzata: $nome #$num";
        }
    }

    /* --- Maglia Giocatore --- */
    foreach ($xmlGioc->getElementsByTagName("personalizzazione") as $p) {
        $idM = (int)$p->getElementsByTagName("ID_Maglia")[0]->nodeValue ?? 0;
        $idOrd = (int)$p->getElementsByTagName("ID")[0]->nodeValue ?? 0;
        if ($idM === $idMaglia && $idOrd === $idOrdine) {
            $idG = (int)$p->getElementsByTagName("ID_Giocatore")[0]->nodeValue ?? 0;
            $logo = $p->getElementsByTagName("Logo")->length ? $p->getElementsByTagName("Logo")[0]->nodeValue : '';
            if ($logo) $descrizione .= " • $logo";

            foreach ($xmlGiocatori->getElementsByTagName("giocatore") as $g) {
                if ((int)$g->getElementsByTagName("ID")[0]->nodeValue === $idG) {
                    $nome = $g->getElementsByTagName("nome")[0]->nodeValue;
                    $cognome = $g->getElementsByTagName("cognome")[0]->nodeValue;
                    $descrizione .= " • $cognome $nome";
                    break;
                }
            }
        }
    }

    $acquisti[] = [
        'username' => $username,
        'descrizione' => $descrizione,
        'pagamento' => number_format($pagamento, 2, ',', '.'),
        'indirizzo' => $indirizzo,
        'data' => $data
    ];
}

/* === ORDINA PER DATA DECRESCENTE === */
usort($acquisti, fn($a, $b) => strcmp($b['data'], $a['data']));
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Storico acquisti</title>
  <link rel="stylesheet" href="styles/style_visualizzazione_g.css">
</head>
<body>
<header>
  <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
    <div class="logo-container"><img src="img/AS_Roma_Logo_2017.svg.png" class="logo" alt="Logo AS Roma"></div>
  </a>
  <h1><a href="<?= htmlspecialchars($homepage_link) ?>" style="color:inherit;text-decoration:none;">PLAYERBASE</a></h1>
  <div class="utente-container"><div class="logout"><a href="?logout=true">Logout</a></div></div>
</header>

<main class="main-container">
  <h2>Storico acquisti</h2>

  <form method="get" class="filter-form">
    <label for="utente">Filtro per utente:</label>
    <select name="utente" id="utente" onchange="this.form.submit()" style="padding: 6px 12px; margin-left: 8px;">
      <option value="">-- Tutti --</option>
      <?php foreach ($utenti as $id => $uname): ?>
        <option value="<?= $id ?>" <?= ($id_utente_filtro == $id) ? 'selected' : '' ?>>
          <?= htmlspecialchars($uname) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <div class="table-wrapper">
    <?php if ($acquisti): ?>
    <table>
      <thead>
        <tr>
          <th>Username</th>
          <th>Dettaglio Maglia</th>
          <th>Pagamento (€)</th>
          <th>Indirizzo</th>
          <th>Data acquisto</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($acquisti as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['username']) ?></td>
          <td><?= htmlspecialchars($r['descrizione']) ?></td>
          <td><?= htmlspecialchars($r['pagamento']) ?></td>
          <td><?= htmlspecialchars($r['indirizzo']) ?></td>
          <td><?= htmlspecialchars($r['data']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p style="text-align:center;">Nessun acquisto registrato.</p>
    <?php endif; ?>
  </div>
</main>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
  <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
</footer>
</body>
</html>