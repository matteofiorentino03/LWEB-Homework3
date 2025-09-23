<?php
session_start();

// Solo admin loggato può accedere
if (!isset($_SESSION['Username']) || strtolower($_SESSION['Ruolo']) !== 'admin') {
    header("Location: entering.html");
    exit();
}

// Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: homepage_user.php");
    exit();
}

$homepage_link = "homepage_admin.php";

// Connessione al DB per recuperare gli username
require_once 'connect.php';
$conn = db();

// === Percorsi XML ===
$path_compra         = 'xml/compra.xml';
$path_maglie         = 'xml/maglie.xml';
$path_giocatore      = 'xml/maglie_giocatore.xml';
$path_personalizzate = 'xml/maglie_personalizzate.xml';

// === Caricamento XML ===
$dom_compra = new DOMDocument();
$dom_maglie = new DOMDocument();
$dom_giocatore = new DOMDocument();
$dom_personalizzate = new DOMDocument();

libxml_use_internal_errors(true);
$dom_compra->load($path_compra);
$dom_maglie->load($path_maglie);
$dom_giocatore->load($path_giocatore);
$dom_personalizzate->load($path_personalizzate);
libxml_clear_errors();

// === XPath ===
$xp_compra = new DOMXPath($dom_compra);
$xp_maglie = new DOMXPath($dom_maglie);
$xp_giocatore = new DOMXPath($dom_giocatore);
$xp_personalizzate = new DOMXPath($dom_personalizzate);

// === Recupera tutti gli ordini ===
$ordini = $xp_compra->query("//ordine");
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
    <div class="logo-container">
      <img src="img/AS_Roma_Logo_2017.svg.png" class="logo" alt="Logo AS Roma">
    </div>
  </a>
  <h1><a href="<?= htmlspecialchars($homepage_link) ?>" style="color:inherit;text-decoration:none;">PLAYERBASE</a></h1>
  <div class="utente-container">
    <div class="logout"><a href="?logout=true">Logout</a></div>
  </div>
</header>

<main class="main-container">
  <h2>Storico acquisti</h2>
  <div class="table-wrapper">
    <?php if ($ordini->length > 0): ?>
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
        <?php foreach ($ordini as $ordine): 
            $id_utente  = (int)$ordine->getElementsByTagName('ID_Utente')->item(0)->nodeValue;
            $id_maglia  = (int)$ordine->getElementsByTagName('ID_Maglia')->item(0)->nodeValue;
            $pagamento  = (float)$ordine->getElementsByTagName('pagamento_finale')->item(0)->nodeValue;
            $indirizzo  = $ordine->getElementsByTagName('indirizzo_consegna')->item(0)->nodeValue;
            $data       = $ordine->getElementsByTagName('data_compra')->item(0)->nodeValue;

            // === Username ===
            $stmt = $conn->prepare("SELECT username FROM utenti WHERE id = ?");
            $stmt->bind_param("i", $id_utente);
            $stmt->execute();
            $stmt->bind_result($username_result);
            $username = $stmt->fetch() ? $username_result : 'N/D';
            $stmt->close();

            // === Maglia Dettaglio ===
            $magliaNode = null;
            foreach ([$xp_maglie, $xp_giocatore, $xp_personalizzate] as $xp) {
                $magliaNode = $xp->query("//maglia[ID='$id_maglia']")->item(0);
                if ($magliaNode) break;
            }

            $dettaglio = 'N/D';
            if ($magliaNode) {
                $tipo     = $magliaNode->getElementsByTagName('tipo')->item(0)->nodeValue ?? '';
                $stagione = $magliaNode->getElementsByTagName('stagione')->item(0)->nodeValue ?? '';
                $taglia   = $magliaNode->getElementsByTagName('taglia')->item(0)->nodeValue ?? '';
                $dettaglio = "$tipo · $stagione · $taglia";
            }

        ?>
        <tr>
          <td><?= htmlspecialchars($username) ?></td>
          <td><?= htmlspecialchars($dettaglio) ?></td>
          <td><?= number_format($pagamento, 2, ',', '.') ?></td>
          <td><?= htmlspecialchars($indirizzo) ?></td>
          <td><?= htmlspecialchars($data) ?></td>
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
</footer>
</body>
</html>