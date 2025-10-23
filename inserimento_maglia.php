<?php
session_start();

/* ========================
    Controllo Accesso
======================== */
if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo'])) {
    header("Location: entering.html");
    exit();
}

if (strtolower($_SESSION['Ruolo']) !== 'gestore') {
    // Se non è gestore → torna alla login
    header("Location: entering.html");
    exit();
}

/* ========================
    Logout
======================== */
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

/* ========================
   VARIABILI SESSIONE
======================== */
$username = $_SESSION['Username'];
$ruolo = $_SESSION['Ruolo'];
$homepage_link = 'homepage_gestore.php'; // homepage coerente
$errore = "";
$successo = "";

// Invio del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo        = $_POST['Tipo']        ?? '';
    $taglia      = $_POST['Taglia']      ?? '';
    $sponsor     = trim($_POST['Sponsor'] ?? '');
    $descrizione = trim($_POST['Descrizione'] ?? '');
    $stagione    = trim($_POST['Stagione']    ?? '');
    $costo       = isset($_POST['Costo']) ? intval($_POST['Costo']) : -1;
    $immagine_path = null;

    // Validazioni
    if (!in_array($tipo, ['casa','fuori','terza','portiere'], true)) $errore .= "Tipo non valido.<br>";
    if (!in_array($taglia, ['S','M','L','XL'], true))                $errore .= "Taglia non valida.<br>";
    if ($descrizione === '' || $stagione === '')                    $errore .= "Descrizione e stagione sono obbligatorie.<br>";
    if ($costo < 0)                                                 $errore .= "Il costo non può essere negativo.<br>";
    if (mb_strlen($sponsor) > 40)                                   $errore .= "Sponsor troppo lungo (max 40 caratteri).<br>";

    // Upload immagine
    if (isset($_FILES['immagine'])) {
        $f = $_FILES['immagine'];
        if ($f['error'] === UPLOAD_ERR_OK) {
            $cartella = __DIR__ . "/img/maglie/";
            if (!is_dir($cartella)) mkdir($cartella, 0777, true);
            $est = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $ok_ext = ['jpg','jpeg','png','webp','gif'];
            if (!in_array($est, $ok_ext, true)) {
                $errore .= "Formato immagine non valido.<br>";
            } else {
                $nome_sicuro = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($f['name']));
                $dest_rel = "img/maglie/" . time() . "_" . $nome_sicuro;
                $dest_abs = __DIR__ . "/" . $dest_rel;
                if (!move_uploaded_file($f['tmp_name'], $dest_abs)) {
                    $errore .= "Errore salvataggio immagine.<br>";
                } else {
                    $immagine_path = $dest_rel;
                }
            }
        } else {
            $errore .= "Errore nell'upload immagine.<br>";
        }
    } else {
        $errore .= "Immagine obbligatoria.<br>";
    }

    // Scrittura su maglie.xml
    if ($errore === "") {
        $xmlFile = __DIR__ . "/xml/maglie.xml";
        $dom = new DOMDocument("1.0", "UTF-8");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (file_exists($xmlFile)) {
            $dom->load($xmlFile);
            $root = $dom->documentElement;
        } else {
            $root = $dom->createElement("maglie");
            $dom->appendChild($root);
        }

        // Calcolo ID progressivo
        $id = 1;
        foreach ($dom->getElementsByTagName("maglia") as $m) {
            $idMaglia = $m->getElementsByTagName("ID")[0]->nodeValue ?? 0;
            if ((int)$idMaglia >= $id) {
                $id = (int)$idMaglia + 1;
            }
        }

        $maglia = $dom->createElement("maglia");

        $maglia->appendChild($dom->createElement("ID", $id));
        $maglia->appendChild($dom->createElement("tipo", $tipo));
        $maglia->appendChild($dom->createElement("taglia", $taglia));
        $maglia->appendChild($dom->createElement("Sponsor", $sponsor));
        $maglia->appendChild($dom->createElement("stagione", $stagione));
        $maglia->appendChild($dom->createElement("descrizione_maglia", $descrizione));
        $maglia->appendChild($dom->createElement("costo_fisso", (int)$costo));
        $maglia->appendChild($dom->createElement("path_immagine", $immagine_path));

        $root->appendChild($maglia);
        $dom->save($xmlFile);

        $successo = "Maglia inserita correttamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Inserisci Maglia</title>
  <link rel="stylesheet" href="styles/style_inserimenti_g.css">
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
  <div class="table">
    <h2>Inserisci una Maglia</h2>
    <?php if ($errore)   echo "<p style='color:red;'>$errore</p>"; ?>
    <?php if ($successo) echo "<p style='color:green;'>$successo</p>"; ?>

    <form method="post" enctype="multipart/form-data">
      <label><strong>Tipo:</strong></label><br>
      <select name="Tipo" required>
        <option value="casa">Casa</option>
        <option value="fuori">Fuori</option>
        <option value="terza">Terza</option>
        <option value="portiere">Portiere</option>
      </select><br><br>

      <label><strong>Taglia:</strong></label><br>
      <select name="Taglia" required>
        <option value="S">S</option>
        <option value="M">M</option>
        <option value="L">L</option>
        <option value="XL">XL</option>
      </select><br><br>

      <input type="text" name="Sponsor" placeholder="Sponsor (opzionale, max 40)" maxlength="40"><br><br>
      <input type="text" name="Stagione" placeholder="Stagione (es: 2025/26)" required><br><br>
      <input type="text" name="Descrizione" placeholder="Descrizione Maglia" required><br><br>
      <input type="number" name="Costo" placeholder="Costo Fisso (€)" min="0" step="0.01" required><br><br>

      <label for="upload_immagine" class="custom-file-upload"><strong>Carica Immagine:</strong></label>
      <input id="upload_immagine" type="file" name="immagine" accept="image/*" required><br><br>

      <button type="submit">Salva Maglia</button>
    </form>
  </div>
</div>

<footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>
</body>
</html>
