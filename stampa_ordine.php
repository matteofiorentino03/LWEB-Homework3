<?php
session_start();

/* ==== Verifica login ==== */
if (!isset($_SESSION['Username'])) {
    header("Location: entering.html");
    exit();
}

$loggedUser = $_SESSION['Username'];
$ruolo = isset($_SESSION['Ruolo']) ? strtolower($_SESSION['Ruolo']) : null;
$isAdmin = ($ruolo === 'admin');

/* ==== ID ordine ==== */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Ordine non valido.");
}
$ordineId = (int)$_GET['id'];

/* ==== Connessione DB (per recuperare username) ==== */
require_once __DIR__ . '/connect.php';
try {
    $conn = db();
} catch (Throwable $e) {
    die("Errore DB: " . $e->getMessage());
}

/* ==== Carica XML ==== */
$compra     = new DOMDocument();
$maglie     = new DOMDocument();
$giocatori  = new DOMDocument();
$magliePers = new DOMDocument();
$maglieGioc = new DOMDocument();

$compra->load("xml/compra.xml");
$maglie->load("xml/maglie.xml");
$giocatori->load("xml/giocatori.xml");
$magliePers->load("xml/maglie_personalizzate.xml");
$maglieGioc->load("xml/maglie_giocatore.xml");

/* ==== Trova l’ordine ==== */
$xpath = new DOMXPath($compra);
$ordineNode = $xpath->query("//ordine[ID = $ordineId]")->item(0);
if (!$ordineNode) die("Ordine non trovato.");

$idUtente = (int)$ordineNode->getElementsByTagName("ID_Utente")->item(0)->nodeValue;
$idMaglia = (int)$ordineNode->getElementsByTagName("ID_Maglia")->item(0)->nodeValue;
$pagamentoFinale = (float)($ordineNode->getElementsByTagName("pagamento_finale")->item(0)->nodeValue ?? 0);
$indirizzo = $ordineNode->getElementsByTagName("indirizzo_consegna")->item(0)->nodeValue;
$data = $ordineNode->getElementsByTagName("data_compra")->item(0)->nodeValue;

/* ==== Recupera username da SQL ==== */
$stmt = $conn->prepare("SELECT username FROM utenti WHERE id = ?");
$stmt->bind_param("i", $idUtente);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$username = $row['username'] ?? null;
$stmt->close();

if (!$isAdmin && $username !== $loggedUser) {
    http_response_code(403);
    die("Non sei autorizzato a visualizzare questo ordine.");
}

/* ==== Ricava info maglia ==== */
$xpathMaglia = new DOMXPath($maglie);
$mnode = $xpathMaglia->query("//maglia[ID = $idMaglia]")->item(0);
if (!$mnode) die("Maglia non trovata.");

$tipo     = $mnode->getElementsByTagName("tipo")->item(0)->nodeValue;
$stagione = $mnode->getElementsByTagName("stagione")->item(0)->nodeValue;
$taglia   = $mnode->getElementsByTagName("taglia")->item(0)->nodeValue;
$costo    = (float)$mnode->getElementsByTagName("costo_fisso")->item(0)->nodeValue;

/* ==== Prova a trovare personalizzazione ==== */
$logo = $supp = $persNome = $persNum = $nomeG = $cognomeG = "";
$trovata = false;

/* Maglie Giocatore */
foreach ($maglieGioc->getElementsByTagName("personalizzazione") as $p) {
    $idMagliaGioc = (int)$p->getElementsByTagName("ID_Maglia")->item(0)->nodeValue;
    if ($idMagliaGioc === $idMaglia) {
        $supp = (float)$p->getElementsByTagName("Supplemento")->item(0)->nodeValue;
        $logo = $p->getElementsByTagName("Logo")->item(0)->nodeValue ?? "";
        $idGioc = (int)$p->getElementsByTagName("ID_Giocatore")->item(0)->nodeValue;

        // Nome Giocatore
        foreach ($giocatori->getElementsByTagName("giocatore") as $g) {
            $gid = (int)$g->getElementsByTagName("ID")->item(0)->nodeValue;
            if ($gid === $idGioc) {
                $nomeG = $g->getElementsByTagName("nome")->item(0)->nodeValue;
                $cognomeG = $g->getElementsByTagName("cognome")->item(0)->nodeValue;
                break;
            }
        }
        $trovata = true;
        break;
    }
}

/* Maglie Personalizzate */
if (!$trovata) {
    foreach ($magliePers->getElementsByTagName("maglia") as $p) {
        $idMagliaPers = (int)$p->getElementsByTagName("ID_Maglia")->item(0)->nodeValue;
        if ($idMagliaPers === $idMaglia) {
            $supp = (float)$p->getElementsByTagName("supplemento")->item(0)->nodeValue;
            $logo = $p->getElementsByTagName("Logo")->item(0)->nodeValue ?? "";
            $persNome = $p->getElementsByTagName("nome")->item(0)->nodeValue;
            $persNum  = $p->getElementsByTagName("num_maglia")->item(0)->nodeValue;
            break;
        }
    }
}

/* ==== Calcolo totale ==== */
$totale = ($pagamentoFinale > 0) ? $pagamentoFinale : ($costo + $supp);

/* ==== Descrizione maglia ==== */
$base = "$tipo • $stagione • $taglia";
if ($persNome) {
    $descr = $base . ($logo ? " • $logo" : "") . " • Personalizzata: $persNome #$persNum";
} elseif ($nomeG) {
    $descr = $base . ($logo ? " • $logo" : "") . " • $nomeG $cognomeG";
} else {
    $descr = $base . ($logo ? " • $logo" : "");
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Stampa Ordine #<?= htmlspecialchars($ordineId) ?></title>
  <link rel="stylesheet" href="styles/style_stampa_ordine.css">
</head>
<body>
  <header>
    <a href="<?= $isAdmin ? 'homepage_admin.php' : 'homepage_user.php' ?>" class="header-link">
      <div class="logo-container">
        <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo">
      </div>
    </a>
    <h1><a href="<?= $isAdmin ? 'homepage_admin.php' : 'homepage_user.php' ?>" style="color: inherit; text-decoration: none;">PLAYERBASE</a></h1>
  </header>

  <div class="invoice">
    <h2 class="order-title">Riepilogo Ordine #<?= htmlspecialchars($ordineId) ?></h2>
    <table class="table">
      <tr><th>Utente</th><td><?= htmlspecialchars($username) ?></td></tr>
      <tr><th>Dettaglio Maglia</th><td><?= htmlspecialchars($descr) ?></td></tr>
      <tr><th>Pagamento</th><td class="money"><?= number_format($totale, 2, ',', '.') ?> €</td></tr>
      <tr><th>Indirizzo di consegna</th><td><?= htmlspecialchars($indirizzo) ?></td></tr>
      <tr><th>Data acquisto</th><td><?= htmlspecialchars($data) ?></td></tr>
    </table>
    <div class="actions no-print">
      <button class="btn" onclick="window.print()">Stampa PDF</button>
    </div>
  </div>

  <footer class="no-print">
    <p>&copy; 2025 Playerbase - Tutti i diritti riservati</p>
  </footer>
</body>
</html>