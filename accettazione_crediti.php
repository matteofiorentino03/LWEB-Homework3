<?php
session_start();
require_once __DIR__ . '/connect.php';

//  Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

//  Accesso consentito solo agli amministratori
if (!isset($_SESSION['Username']) || strtolower($_SESSION['Ruolo'] ?? '') !== 'amministratore') {
    // se non è loggato o non è amministratore → torna alla login
    header("Location: entering.html");
    exit();
}

//  Homepage di riferimento per il logo e i link
$homepage_link = 'homepage_admin.php';

// Connessione DB
try {
    $conn = db();
} catch (Throwable $e) {
    die("Errore DB: " . $e->getMessage());
}

// === Carica richieste dal file XML ===
$xmlPath = __DIR__ . '/xml/crediti_richieste.xml';
if (!file_exists($xmlPath)) {
    die("❌ File XML non trovato: crediti_richieste.xml");
}

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->load($xmlPath);
$xpath = new DOMXPath($dom);

// === Estrai solo richieste 'In attesa' ===
$richieste = [];
foreach ($xpath->query("//richiesta[translate(stato,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='in attesa']") as $r) {
    $id = (int)$r->getElementsByTagName('ID')->item(0)->nodeValue;
    $user_id = (int)$r->getElementsByTagName('user_id')->item(0)->nodeValue;
    $importo = (float)$r->getElementsByTagName('importo')->item(0)->nodeValue;
    $created_at = $r->getElementsByTagName('created_at')->item(0)->nodeValue;

    // Dati utente dal DB
    $stmt = $conn->prepare("SELECT username, status, COALESCE(crediti,0) AS crediti FROM Utenti WHERE ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $utente = $res->fetch_assoc();
    $stmt->close();

    if ($utente) {
        $richieste[] = [
            'id' => $id,
            'user_id' => $user_id,
            'importo' => $importo,
            'created_at' => $created_at,
            'username' => $utente['username'],
            'status' => $utente['status'],
            'crediti' => $utente['crediti']
        ];
    }
}

// === Gestione accettazione / rifiuto ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_richiesta'])) {
    $id_richiesta = (int)$_POST['id_richiesta'];
    $richiestaNode = $xpath->query("//richiesta[ID=$id_richiesta]")->item(0);

    if (!$richiestaNode) {
        echo "<script>alert('Richiesta non trovata.'); window.location='accettazione_crediti.php';</script>";
        exit;
    }

    $stato = strtolower(trim($richiestaNode->getElementsByTagName('stato')->item(0)->nodeValue));
    $user_id = (int)$richiestaNode->getElementsByTagName('user_id')->item(0)->nodeValue;
    $importo = (float)$richiestaNode->getElementsByTagName('importo')->item(0)->nodeValue;

    if ($stato !== 'in attesa') {
        echo "<script>alert('Richiesta già processata.'); window.location='accettazione_crediti.php';</script>";
        exit;
    }

    if (isset($_POST['accetta'])) {
        $stmt = $conn->prepare("UPDATE Utenti SET crediti = COALESCE(crediti,0) + ? WHERE ID = ?");
        $stmt->bind_param("di", $importo, $user_id);
        $stmt->execute();
        $stmt->close();

        $richiestaNode->getElementsByTagName('stato')->item(0)->nodeValue = "Approvata";
        $dom->save($xmlPath);

        echo "<script>alert('Richiesta #$id_richiesta approvata.'); window.location='accettazione_crediti.php';</script>";
        exit;
    }

    if (isset($_POST['rifiuta'])) {
        $richiestaNode->getElementsByTagName('stato')->item(0)->nodeValue = "Rifiutata";
        $dom->save($xmlPath);

        echo "<script>alert('Richiesta #$id_richiesta rifiutata.'); window.location='accettazione_crediti.php';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Accettazione Crediti</title>
  <link rel="stylesheet" href="styles/style_acc_crediti.css">
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
  <h2>Richieste crediti in attesa</h2>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Username</th>
          <th>Status</th>
          <th>Crediti attuali</th>
          <th>Importo richiesto</th>
          <th>Data richiesta</th>
          <th>Azione</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($richieste): foreach ($richieste as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['username']) ?></td>
          <td><?= htmlspecialchars($r['status']) ?></td>
          <td><?= number_format((float)$r['crediti'], 2, ',', '.') ?></td>
          <td><strong><?= number_format((float)$r['importo'], 2, ',', '.') ?></strong></td>
          <td>
            <?php
              $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $r['created_at']);
              echo $dt ? $dt->format('d/m/Y H:i') : htmlspecialchars($r['created_at']);
            ?>
          </td>
          <td class="actions">
            <form method="post" class="inline">
              <input type="hidden" name="id_richiesta" value="<?= (int)$r['id'] ?>">
              <button type="submit" name="accetta" class="btn-accept">Accetta</button>
              <button type="submit" name="rifiuta" class="btn-reject">Rifiuta</button>
            </form>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" style="text-align:center;">Nessuna richiesta in attesa.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>
</body>
</html>
