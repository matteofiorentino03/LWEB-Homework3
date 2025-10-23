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
$homepage_link = 'homepage_gestore.php';
$errore = "";
$record = null;
/* Caricamento lista maglie */
$maglie = [];
$xml = new DOMDocument();
$xml->load("xml/maglie.xml");
foreach ($xml->getElementsByTagName("maglia") as $nodo) {
    $id = $nodo->getElementsByTagName("ID")[0]->nodeValue;
    $tipo = $nodo->getElementsByTagName("tipo")[0]->nodeValue;
    $taglia = $nodo->getElementsByTagName("taglia")[0]->nodeValue;
    $stagione = $nodo->getElementsByTagName("stagione")[0]->nodeValue;
    $maglie[] = ["ID" => $id, "tipo" => $tipo, "taglia" => $taglia, "stagione" => $stagione];
}

/* Caricamento maglia da modificare */
if (isset($_POST['select_id']) && $_POST['select_id'] !== "") {
    $id_sel = $_POST['select_id'];
    foreach ($xml->getElementsByTagName("maglia") as $nodo) {
        if ($nodo->getElementsByTagName("ID")[0]->nodeValue == $id_sel) {
            $record = $nodo;
            break;
        }
    }
}

/* Salvataggio modifiche */
if (isset($_POST['update']) && isset($_POST['ID'])) {
    $id = $_POST['ID'];
    $tipo = $_POST['tipo'] ?? '';
    $taglia = $_POST['taglia'] ?? '';
    $sponsor = trim($_POST['sponsor'] ?? '');
    $descrizione = trim($_POST['descrizione_maglia'] ?? '');
    $stagione = trim($_POST['stagione'] ?? '');
    $costo = $_POST['costo_fisso'] ?? '';
    $old_path = $_POST['old_path'] ?? '';
    $nuovo_path = $old_path;

    // Validazione
    $tipi_validi = ['casa','fuori','terza','portiere'];
    $taglie_valide = ['S','M','L','XL'];
    if (!in_array($tipo, $tipi_validi)) $errore .= "Tipo non valido.<br>";
    if (!in_array($taglia, $taglie_valide)) $errore .= "Taglia non valida.<br>";
    if (mb_strlen($sponsor) > 40) $errore .= "Sponsor troppo lungo (max 40).<br>";
    if ($descrizione === '') $errore .= "La descrizione è obbligatoria.<br>";
    if ($stagione === '') $errore .= "La stagione è obbligatoria.<br>";
    if (!is_numeric($costo) || (int)$costo < 0) $errore .= "Il costo fisso deve essere ≥ 0.<br>";

    // Upload immagine
    if ($errore === "" && isset($_FILES['immagine']) && $_FILES['immagine']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['immagine']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['immagine']['size'] <= 5 * 1024 * 1024) {
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                $tmp = $_FILES['immagine']['tmp_name'];
                $mime = mime_content_type($tmp);
                if (isset($allowed[$mime])) {
                    $ext = $allowed[$mime];
                    $uploadDirFs = __DIR__ . '/img/maglie/';
                    $uploadDirWeb = 'img/maglie/';
                    if (!is_dir($uploadDirFs)) mkdir($uploadDirFs, 0777, true);
                    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destFs = $uploadDirFs . $filename;
                    $destWeb = $uploadDirWeb . $filename;
                    if (move_uploaded_file($tmp, $destFs)) {
                        $nuovo_path = $destWeb;
                        // Elimina vecchia immagine
                        $oldFs = realpath(__DIR__ . '/' . str_replace(['\\'], '/', $old_path));
                        $rootUpload = realpath($uploadDirFs);
                        if ($oldFs && strpos($oldFs, $rootUpload) === 0 && is_file($oldFs)) {
                            @unlink($oldFs);
                        }
                    } else {
                        $errore .= "Impossibile spostare il file.<br>";
                    }
                } else {
                    $errore .= "Formato immagine non supportato.<br>";
                }
            } else {
                $errore .= "Immagine troppo grande.<br>";
            }
        } else {
            $errore .= "Errore upload immagine.<br>";
        }
    }

    if ($errore === "") {
        // Sovrascrittura nodo XML
        foreach ($xml->getElementsByTagName("maglia") as $nodo) {
            if ($nodo->getElementsByTagName("ID")[0]->nodeValue == $id) {
                $nodo->getElementsByTagName("tipo")[0]->nodeValue = $tipo;
                $nodo->getElementsByTagName("taglia")[0]->nodeValue = $taglia;
                $nodo->getElementsByTagName("Sponsor")[0]->nodeValue = $sponsor;
                $nodo->getElementsByTagName("descrizione_maglia")[0]->nodeValue = $descrizione;
                $nodo->getElementsByTagName("stagione")[0]->nodeValue = $stagione;
                $nodo->getElementsByTagName("costo_fisso")[0]->nodeValue = $costo;
                $nodo->getElementsByTagName("path_immagine")[0]->nodeValue = $nuovo_path;
                break;
            }
        }
        $xml->save("xml/maglie.xml");

        //  Successo: alert + redirect
        echo "<script>
            alert('Maglia aggiornata con successo!');
            window.location.href = 'modifica_maglia.php';
        </script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Modifica Maglia</title>
  <link rel="stylesheet" href="styles/style_modifica_m.css">
</head>
<body>
<header>
  <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
    <div class='logo-container'>
      <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo">
    </div>
  </a>
  <h1><a href="<?= htmlspecialchars($homepage_link) ?>" style="color: inherit; text-decoration: none;">PLAYERBASE</a></h1>
  <div class="utente-container">
    <div class="logout"><a href="?logout=true"><p>Logout</p></a></div>
  </div>
</header>

<main class="page">
  <h2 class="page-title">Modifica Maglia</h2>
  <?php if ($errore) echo '<div class="alert alert-error">'.$errore.'</div>'; ?>

  <!-- Selezione Maglia -->
  <form method="post" class="card narrow">
    <label for="select_id" class="label"><strong>Seleziona Maglia</strong></label>
    <select name="select_id" id="select_id" class="input" onchange="this.form.submit()">
      <option value="">-- Seleziona --</option>
      <?php foreach ($maglie as $m): ?>
        <option value="<?= (int)$m['ID']; ?>" <?= ($record && $m['ID'] == $record->getElementsByTagName("ID")[0]->nodeValue) ? 'selected' : '' ?>>
          <?= htmlspecialchars(ucfirst($m['tipo'])) ?> • <?= htmlspecialchars($m['taglia']) ?> • <?= htmlspecialchars($m['stagione']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($record): ?>
  <form method="post" enctype="multipart/form-data" class="card">
    <input type="hidden" name="ID" value="<?= $record->getElementsByTagName("ID")[0]->nodeValue ?>">
    <input type="hidden" name="old_path" value="<?= htmlspecialchars($record->getElementsByTagName("path_immagine")[0]->nodeValue) ?>">

    <div class="grid">
      <div class="col">
        <label class="label">Tipo</label>
        <select name="tipo" class="input">
          <?php foreach (['casa','fuori','terza','portiere'] as $val): ?>
            <option value="<?= $val ?>" <?= $record->getElementsByTagName("tipo")[0]->nodeValue == $val ? 'selected' : '' ?>><?= ucfirst($val) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col">
        <label class="label">Taglia</label>
        <select name="taglia" class="input">
          <?php foreach (['S','M','L','XL'] as $val): ?>
            <option value="<?= $val ?>" <?= $record->getElementsByTagName("taglia")[0]->nodeValue == $val ? 'selected' : '' ?>><?= $val ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col">
        <label class="label">Sponsor</label>
        <input type="text" name="sponsor" class="input" maxlength="40" value="<?= htmlspecialchars($record->getElementsByTagName("Sponsor")[0]->nodeValue) ?>">
      </div>

      <div class="col">
        <label class="label">Descrizione</label>
        <input type="text" name="descrizione_maglia" class="input" required value="<?= htmlspecialchars($record->getElementsByTagName("descrizione_maglia")[0]->nodeValue) ?>">
      </div>

      <div class="col">
        <label class="label">Stagione</label>
        <input type="text" name="stagione" class="input" required value="<?= htmlspecialchars($record->getElementsByTagName("stagione")[0]->nodeValue) ?>">
      </div>

      <div class="col">
        <label class="label">Costo fisso (€)</label>
        <input type="number" name="costo_fisso" class="input" min="0" required value="<?= (int)$record->getElementsByTagName("costo_fisso")[0]->nodeValue ?>">
      </div>

      <div class="col col-image">
        <label class="label">Immagine attuale</label>
        <img class="img-preview" id="preview" src="<?= htmlspecialchars($record->getElementsByTagName("path_immagine")[0]->nodeValue) ?>" alt="Immagine maglia">
        <input type="file" name="immagine" accept="image/*" class="file-control">
      </div>
    </div>

    <button type="submit" name="update" class="btn-submit">Salva Modifiche</button>
  </form>
  <?php endif; ?>
</main>

<footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>
</body>
</html>
