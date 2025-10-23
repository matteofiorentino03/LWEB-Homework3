<?php
session_start();

/* ==========================
    Controllo Accesso
========================== */
if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo'])) {
    header("Location: entering.html");
    exit();
}

if (strtolower($_SESSION['Ruolo']) !== 'gestore') {
    // Solo il Gestore può accedere
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
   VARIABILI BASE
========================== */
$homepage_link = "homepage_gestore.php";
$errore = '';
$statistiche = null;
$ruolo = '';
$id = $_GET['ID'] ?? '';

if (!$id) {
    $errore = "ID del giocatore non fornito.";
} else {
    $files = [
        'portieri.xml' => 'portiere',
        'difensori.xml' => 'difensore',
        'centrocampisti.xml' => 'centrocampista',
        'attaccanti.xml' => 'attaccante',
    ];

    foreach ($files as $file => $tag) {
        if (!file_exists("xml/$file")) continue;
        $xml = simplexml_load_file("xml/$file");

        foreach ($xml->$tag as $g) {
            if ((string)$g->ID_giocatore === $id) {
                $statistiche = $g;
                $ruolo = $tag;
                $fileRuolo = $file;
                break 2;
            }
        }
    }

    if (!$statistiche) {
        $errore = "Giocatore non trovato nei file delle statistiche.";
    }
}

// Salvataggio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stat']) && $statistiche) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->load("xml/$fileRuolo");

    $giocatori = $dom->getElementsByTagName($ruolo);
    foreach ($giocatori as $g) {
        $id_xml = $g->getElementsByTagName("ID_giocatore")[0]->nodeValue;
        if ($id_xml === $id) {
            $g->getElementsByTagName("gol_fatti")[0]->nodeValue = $_POST['gol_fatti'] ?? 0;
            $g->getElementsByTagName("assist")[0]->nodeValue = $_POST['assist'] ?? 0;
            $g->getElementsByTagName("ammonizioni")[0]->nodeValue = $_POST['ammonizioni'] ?? 0;
            $g->getElementsByTagName("espulsioni")[0]->nodeValue = $_POST['espulsioni'] ?? 0;

            if ($ruolo === 'portiere') {
                $g->getElementsByTagName("gol_subiti")[0]->nodeValue = $_POST['gol_subiti'] ?? 0;
                $g->getElementsByTagName("clean_sheet")[0]->nodeValue = $_POST['clean_sheet'] ?? 0;
            } else {
                $g->getElementsByTagName("ruolo")[0]->nodeValue = $_POST['ruolo'] ?? '';
            }
            break;
        }
    }

    $dom->save("xml/$fileRuolo");

    //  ALERT + REDIRECT
    echo "<script>
        alert('Statistiche aggiornate con successo!');
        window.location.href = 'modifica_giocatore.php';
    </script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Modifica Statistiche</title>
  <link rel="stylesheet" href="styles/style_modifica_g.css">
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

  <div class="main-container">
    <h2>Modifica Statistiche Giocatore</h2>

    <?php if ($errore): ?>
      <p class="errore"><?= $errore ?></p>
    <?php elseif ($statistiche): ?>
      <form method="post">
        <input type="hidden" name="ID" value="<?= htmlspecialchars($id) ?>">

        <?php if ($ruolo === 'portiere'): ?>
          <label>Gol Subiti:</label><input type="number" name="gol_subiti" value="<?= $statistiche->gol_subiti ?? 0 ?>" required>
          <label>Gol Fatti:</label><input type="number" name="gol_fatti" value="<?= $statistiche->gol_fatti ?? 0 ?>" required>
          <label>Assist:</label><input type="number" name="assist" value="<?= $statistiche->assist ?? 0 ?>" required>
          <label>Clean Sheet:</label><input type="number" name="clean_sheet" value="<?= $statistiche->clean_sheet ?? 0 ?>" required>
          <label>Ammonizioni:</label><input type="number" name="ammonizioni" value="<?= $statistiche->ammonizioni ?? 0 ?>" required>
          <label>Espulsioni:</label><input type="number" name="espulsioni" value="<?= $statistiche->espulsioni ?? 0 ?>" required>

        <?php else: ?>
          <label>Gol Fatti:</label><input type="number" name="gol_fatti" value="<?= $statistiche->gol_fatti ?? 0 ?>" required>
          <label>Assist:</label><input type="number" name="assist" value="<?= $statistiche->assist ?? 0 ?>" required>
          <label>Ammonizioni:</label><input type="number" name="ammonizioni" value="<?= $statistiche->ammonizioni ?? 0 ?>" required>
          <label>Espulsioni:</label><input type="number" name="espulsioni" value="<?= $statistiche->espulsioni ?? 0 ?>" required>
          <label>Ruolo:</label><input type="text" name="ruolo" value="<?= $statistiche->ruolo ?? '' ?>" required>
        <?php endif; ?>

        <br><br>
        <button type="submit" name="update_stat">Salva Statistiche</button>
      </form>
    <?php endif; ?>
  </div>

  <footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>
</body>

</html>
