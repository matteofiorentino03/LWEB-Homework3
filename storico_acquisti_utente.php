<?php
session_start();

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
$conn = db();

/* === DATI UTENTE === */
$sqlUser = "SELECT ID, username FROM Utenti WHERE username = ?";
$stmt = $conn->prepare($sqlUser);
$stmt->bind_param("s", $_SESSION['Username']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) die("Utente non trovato.");

$userId = (int)$user['ID'];
$username = $user['username'];
$homepage_link = 'homepage_user.php';

/* === CARICAMENTO XML === */
$xmlCompra = new DOMDocument(); $xmlCompra->load("xml/compra.xml");
$xmlMaglie = new DOMDocument(); $xmlMaglie->load("xml/maglie.xml");
$xmlPers = new DOMDocument(); $xmlPers->load("xml/maglie_personalizzate.xml");
$xmlGioc = new DOMDocument(); $xmlGioc->load("xml/maglie_giocatore.xml");
$xmlGiocatori = new DOMDocument(); $xmlGiocatori->load("xml/giocatori.xml");

/* === Funzione per trovare una maglia per ID === */
function trovaMaglia($xmlMaglie, $id) {
    foreach ($xmlMaglie->getElementsByTagName("maglia") as $m) {
        if ($m->getElementsByTagName("ID")[0]->nodeValue == $id) {
            return [
                'tipo' => $m->getElementsByTagName("tipo")[0]->nodeValue ?? '',
                'stagione' => $m->getElementsByTagName("stagione")[0]->nodeValue ?? '',
                'taglia' => $m->getElementsByTagName("taglia")[0]->nodeValue ?? '',
            ];
        }
    }
    return null;
}

/* === COSTRUISCI LISTA ACQUISTI === */
$acquisti = [];

foreach ($xmlCompra->getElementsByTagName("ordine") as $ordine) {
    $idUtente = $ordine->getElementsByTagName("ID_Utente")[0]->nodeValue;
    if ((int)$idUtente !== $userId) continue;

    $idOrdine = $ordine->getElementsByTagName("ID")[0]->nodeValue;
    $idMaglia = $ordine->getElementsByTagName("ID_Maglia")[0]->nodeValue;
    $pagamento = $ordine->getElementsByTagName("pagamento_finale")[0]->nodeValue ?? 0;
    $indirizzo = $ordine->getElementsByTagName("indirizzo_consegna")[0]->nodeValue ?? '';
    $data = $ordine->getElementsByTagName("data_compra")[0]->nodeValue ?? '';

    // Trova maglia base
    $maglia = trovaMaglia($xmlMaglie, $idMaglia);
    if (!$maglia) continue;

    $descrizione = "{$maglia['tipo']} • {$maglia['stagione']} • {$maglia['taglia']}";

    /* --- Se è una maglia personalizzata --- */
    $isPersonalizzata = false;
    foreach ($xmlPers->getElementsByTagName("maglia") as $p) {
        $idPers = $p->getElementsByTagName("ID")[0]->nodeValue ?? '';
        if ($idPers == $idOrdine) {
            $nome = $p->getElementsByTagName("nome")->length ? $p->getElementsByTagName("nome")[0]->nodeValue : '';
            $num = $p->getElementsByTagName("num_maglia")->length ? $p->getElementsByTagName("num_maglia")[0]->nodeValue : '';
            $logo = $p->getElementsByTagName("Logo")->length ? $p->getElementsByTagName("Logo")[0]->nodeValue : '';

            if ($logo) $descrizione .= " • $logo";
            if ($nome || $num) $descrizione .= " • Personalizzata: $nome #$num";
            $isPersonalizzata = true;
            break; // evita che venga letta anche come maglia giocatore
        }
    }

    /* --- Se è una maglia giocatore --- */
    if (!$isPersonalizzata) {
        foreach ($xmlGioc->getElementsByTagName("personalizzazione") as $p) {
            $idPers = $p->getElementsByTagName("ID")[0]->nodeValue ?? '';
            if ($idPers == $idOrdine) {
                $idG = $p->getElementsByTagName("ID_Giocatore")[0]->nodeValue ?? '';
                $logo = $p->getElementsByTagName("Logo")->length ? $p->getElementsByTagName("Logo")[0]->nodeValue : '';
                if ($logo) $descrizione .= " • $logo";

                foreach ($xmlGiocatori->getElementsByTagName("giocatore") as $g) {
                    if ($g->getElementsByTagName("ID")[0]->nodeValue == $idG) {
                        $nome = $g->getElementsByTagName("nome")[0]->nodeValue;
                        $cognome = $g->getElementsByTagName("cognome")[0]->nodeValue;
                        $descrizione .= " • $cognome $nome";
                        break;
                    }
                }
                break;
            }
        }
    }

    $acquisti[] = [
        'id' => $idOrdine,
        'descrizione' => $descrizione,
        'pagamento' => number_format((float)$pagamento, 2, ',', '.'),
        'indirizzo' => $indirizzo,
        'data' => $data
    ];
}

/* === Ordina per data più recente === */
usort($acquisti, fn($a, $b) => strcmp($b['data'], $a['data']));
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>I miei acquisti — Playerbase</title>
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
  <div class="utente-container"><div class="logout"><a href="?logout=true">Logout</a></div></div>
</header>

<main class="main-container">
  <h2>Storico acquisti</h2>

  <div class="table-wrapper">
    <?php if ($acquisti): ?>
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
        <?php foreach ($acquisti as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['descrizione']) ?></td>
          <td><?= htmlspecialchars($a['pagamento']) ?></td>
          <td><?= htmlspecialchars($a['indirizzo']) ?></td>
          <td><?= htmlspecialchars($a['data']) ?></td>
          <td>
            <a class="btn-print" href="stampa_ordine.php?id=<?= (int)$a['id'] ?>" target="_blank">
              Stampa PDF
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p style="text-align:center;">Nessun acquisto effettuato.</p>
    <?php endif; ?>
  </div>
</main>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
  <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
</footer>
</body>
</html>