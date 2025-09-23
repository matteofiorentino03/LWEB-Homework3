<?php
session_start();
$homepage_link = "homepage_admin.php";
$errore = '';
$successo = '';

// Caricamento dati da giocatori.xml
$giocatori = [];
$giocatoriPath = __DIR__ . "/xml/giocatori.xml";
if (file_exists($giocatoriPath)) {
    $xml = new DOMDocument();
    $xml->load($giocatoriPath);
    foreach ($xml->getElementsByTagName("giocatore") as $giocatore) {
        $giocatori[] = [
            "ID" => $giocatore->getElementsByTagName("ID")[0]->nodeValue,
            "cf" => $giocatore->getElementsByTagName("cf")[0]->nodeValue,
            "nome" => $giocatore->getElementsByTagName("nome")[0]->nodeValue,
            "cognome" => $giocatore->getElementsByTagName("cognome")[0]->nodeValue,
            "nazionalita" => $giocatore->getElementsByTagName("nazionalita")[0]->nodeValue,
            "datanascita" => $giocatore->getElementsByTagName("datanascita")[0]->nodeValue,
            "num_maglia" => $giocatore->getElementsByTagName("num_maglia")[0]->nodeValue,
            "altezza" => $giocatore->getElementsByTagName("altezza")[0]->nodeValue,
            "market_value" => $giocatore->getElementsByTagName("market_value")[0]->nodeValue,
            "presenze" => $giocatore->getElementsByTagName("presenze")[0]->nodeValue,
            "cod_contratto" => $giocatore->getElementsByTagName("cod_contratto")[0]->nodeValue,
            "Tipo_Contratto" => $giocatore->getElementsByTagName("Tipo_Contratto")[0]->nodeValue,
            "stipendio" => $giocatore->getElementsByTagName("stipendio")[0]->nodeValue,
            "Data_inizio" => $giocatore->getElementsByTagName("Data_inizio")[0]->nodeValue,
            "Data_scadenza" => $giocatore->getElementsByTagName("Data_scadenza")[0]->nodeValue,
        ];
    }
}

$record = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['select_id'])) {
    $id = $_POST['select_id'];
    foreach ($giocatori as $g) {
        if ($g["ID"] == $id) {
            $record = $g;
            break;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update'])) {
    $id = $_POST["ID"];
    $xml = new DOMDocument();
    $xml->preserveWhiteSpace = false;
    $xml->formatOutput = true;
    $xml->load($giocatoriPath);
    foreach ($xml->getElementsByTagName("giocatore") as $giocatore) {
        if ($giocatore->getElementsByTagName("ID")[0]->nodeValue == $id) {
            $giocatore->getElementsByTagName("cf")[0]->nodeValue = $_POST["cf"];
            $giocatore->getElementsByTagName("nome")[0]->nodeValue = $_POST["nome"];
            $giocatore->getElementsByTagName("cognome")[0]->nodeValue = $_POST["cognome"];
            $giocatore->getElementsByTagName("nazionalita")[0]->nodeValue = $_POST["nazionalita"];
            $giocatore->getElementsByTagName("datanascita")[0]->nodeValue = $_POST["datanascita"];
            $giocatore->getElementsByTagName("num_maglia")[0]->nodeValue = $_POST["num_maglia"];
            $giocatore->getElementsByTagName("altezza")[0]->nodeValue = $_POST["altezza"];
            $giocatore->getElementsByTagName("market_value")[0]->nodeValue = $_POST["market_value"];
            $giocatore->getElementsByTagName("presenze")[0]->nodeValue = $_POST["presenze"];
            $giocatore->getElementsByTagName("cod_contratto")[0]->nodeValue = $_POST["cod_contratto"];
            $giocatore->getElementsByTagName("Tipo_Contratto")[0]->nodeValue = $_POST["Tipo_Contratto"];
            $giocatore->getElementsByTagName("stipendio")[0]->nodeValue = $_POST["stipendio"];
            $giocatore->getElementsByTagName("Data_inizio")[0]->nodeValue = $_POST["Data_inizio"];
            $giocatore->getElementsByTagName("Data_scadenza")[0]->nodeValue = $_POST["Data_scadenza"];
            break;
        }
    }
    $xml->save($giocatoriPath);
    $successo = "Modifica completata con successo.";
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Modifica Giocatore</title>
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
    <h2>Modifica Giocatore</h2>

    <?php if ($record): ?>
    <!-- Bottone in alto -->
    <form method="get" action="modifica_statistiche.php">
        <input type="hidden" name="ID" value="<?= htmlspecialchars($record['ID']) ?>">
        <button type="submit" style="margin-bottom: 20px;">Modifica Statistiche</button>
    </form>
    <?php endif; ?>

    <!-- Messaggi -->
    <?php if ($errore): ?>
      <p class="errore"><?= $errore ?></p>
    <?php elseif ($successo): ?>
      <p class="successo" style="color: green;"><?= $successo ?></p>
    <?php endif; ?>

    <!-- Selezione Giocatore -->
    <form method="post">
      <label><strong>Seleziona Giocatore:</strong></label>
      <select name="select_id" onchange="this.form.submit()">
        <option value="">-- Seleziona --</option>
        <?php foreach ($giocatori as $g): ?>
          <option value="<?= $g['ID'] ?>" <?= (isset($record['ID']) && $record['ID'] == $g['ID']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($g['nome'] . ' ' . $g['cognome']) ?> (<?= htmlspecialchars($g['cf']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <!-- Modifica Form -->
    <?php if ($record): ?>
    <form method="post">
      <input type="hidden" name="ID" value="<?= htmlspecialchars($record['ID']) ?>">
      <div class="form-grid">
        <label>CF:</label><input type="text" name="cf" value="<?= htmlspecialchars($record['cf']) ?>" required>
        <label>Nome:</label><input type="text" name="nome" value="<?= htmlspecialchars($record['nome']) ?>" required>
        <label>Cognome:</label><input type="text" name="cognome" value="<?= htmlspecialchars($record['cognome']) ?>" required>
        <label>Nazionalità:</label><input type="text" name="nazionalita" value="<?= htmlspecialchars($record['nazionalita']) ?>" required>
        <label>Data Nascita:</label><input type="date" name="datanascita" value="<?= htmlspecialchars($record['datanascita']) ?>" required>
        <label>Numero Maglia:</label><input type="number" name="num_maglia" value="<?= htmlspecialchars($record['num_maglia']) ?>" required>
        <label>Altezza (m):</label><input type="number" step="0.01" name="altezza" value="<?= htmlspecialchars($record['altezza']) ?>" required>
        <label>Market Value (€):</label><input type="number" step="0.01" name="market_value" value="<?= htmlspecialchars($record['market_value']) ?>" required>
        <label>Presenze:</label><input type="number" name="presenze" value="<?= htmlspecialchars($record['presenze']) ?>" required>
        <label>Codice Contratto:</label><input type="text" name="cod_contratto" value="<?= htmlspecialchars($record['cod_contratto']) ?>" required>
        <label>Tipo Contratto:</label>
        <select name="Tipo_Contratto" required>
          <?php
          $tipi_contratto = ['TRASFERIMENTO TEMPORANEO', 'TRASFERIMENTO DEFINITIVO', 'PROMOSSO DALLA PRIMAVERA', 'RINNOVATO'];
          foreach ($tipi_contratto as $tc): ?>
            <option value="<?= $tc ?>" <?= ($record['Tipo_Contratto'] == $tc) ? 'selected' : '' ?>><?= $tc ?></option>
          <?php endforeach; ?>
        </select>
        <label>Stipendio (€):</label><input type="number" step="0.01" name="stipendio" value="<?= htmlspecialchars($record['stipendio']) ?>" required>
        <label>Data Inizio:</label><input type="date" name="Data_inizio" value="<?= htmlspecialchars($record['Data_inizio']) ?>" required>
        <label>Data Scadenza:</label><input type="date" name="Data_scadenza" value="<?= htmlspecialchars($record['Data_scadenza']) ?>" required>
      </div>
      <br>
      <button type="submit" name="update">Salva Modifiche</button>
    </form>
    <?php endif; ?>
  </div>

  <footer><p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p></footer>
</body>
</html>