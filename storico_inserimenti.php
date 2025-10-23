<?php
session_start();

/* ==========================
    CONTROLLO ACCESSO
========================== */
if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo'])) {
    header("Location: entering.html");
    exit();
}

if (strtolower($_SESSION['Ruolo']) !== 'gestore') {
    // Solo i Gestori possono accedere
    header("Location: entering.html");
    exit();
}

/* ==========================
    LOGOUT
========================== */
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

$ruolo = $_SESSION['Ruolo'];
$homepage_link = 'homepage_gestore.php';

require_once __DIR__ . '/connect.php';

try {
    $conn = db(); // connessione DB per utenti
} catch (Throwable $e) {
    die("Errore connessione DB: " . $e->getMessage());
}

// === UTENTI SQL ===
$utenti = [];
$res = $conn->query("SELECT ID, username FROM Utenti");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $utenti[$row['ID']] = $row['username'];
    }
}

// === GIOCATORI XML ===
$giocatoriXML = new DOMDocument();
$giocatoriXML->load("xml/giocatori.xml");
$lista_giocatori = $giocatoriXML->getElementsByTagName("giocatore");

// === MAGLIE XML ===
$maglieXML = new DOMDocument();
$maglieXML->load("xml/maglie.xml");
$lista_maglie = $maglieXML->getElementsByTagName("maglia");

// === Aggrega maglie per (tipo + stagione) ===
$maglieAggregate = [];

foreach ($lista_maglie as $maglia) {
    $tipo = strtolower(trim($maglia->getElementsByTagName("tipo")->item(0)->nodeValue));
    $stagione = trim($maglia->getElementsByTagName("stagione")->item(0)->nodeValue);
    $taglia = strtoupper(trim($maglia->getElementsByTagName("taglia")->item(0)->nodeValue));
    $sponsor = trim($maglia->getElementsByTagName("Sponsor")->item(0)->nodeValue);
    $img = trim($maglia->getElementsByTagName("path_immagine")->item(0)->nodeValue);

    $key = $tipo . '_' . $stagione;

    if (!isset($maglieAggregate[$key])) {
        $maglieAggregate[$key] = [
            "tipo" => ucfirst($tipo),
            "stagione" => $stagione,
            "taglie" => [],
            "sponsor" => [],
            "img" => $img
        ];
    }

    $maglieAggregate[$key]['taglie'][] = $taglia;
    if ($sponsor !== "") {
        $maglieAggregate[$key]['sponsor'][] = $sponsor;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Storico Inserimenti</title>
  <link rel="stylesheet" href="styles/style_storico.css">
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
    <div class="logout"><a href="?logout=true">Logout</a></div>
  </div>
</header>

<main class="main-container">

    <!-- === GIOCATORI === -->
    <h2>Storico Inserimenti (Giocatori)</h2>
    <div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>CF Giocatore</th>
            <th>Nome e Cognome</th>
            <th>Utente</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($lista_giocatori as $giocatore): ?>
            <?php
                $cf = $giocatore->getElementsByTagName("cf")->item(0)->nodeValue;
                $nome = $giocatore->getElementsByTagName("nome")->item(0)->nodeValue;
                $cognome = $giocatore->getElementsByTagName("cognome")->item(0)->nodeValue;
                $id_utente = $giocatore->getElementsByTagName("ID_utenti")->item(0)->nodeValue ?? null;
                $username = isset($utenti[$id_utente]) ? $utenti[$id_utente] : "Sconosciuto";
            ?>
            <tr>
                <td><?= htmlspecialchars($cf) ?></td>
                <td><?= htmlspecialchars($nome . ' ' . $cognome) ?></td>
                <td><?= htmlspecialchars($username) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
            <br>
    <!-- === MAGLIE === -->
    <h2>Maglie inserite</h2>
    <div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>Immagine</th>
            <th>Tipo</th>
            <th>Taglie</th>
            <th>Stagione</th>
            <th>Sponsor</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($maglieAggregate as $group): ?>
            <tr>
                <td>
                <?php
                    $rel = $group['img'];
                    $abs = __DIR__ . '/' . $rel;
                    if ($rel && is_file($abs)):
                ?>
                    <img src="<?= htmlspecialchars($rel) ?>" alt="Maglia" style="width:50px; height:auto;">
                <?php else: ?>
                    <span style="color:grey;">—</span>
                <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($group['tipo']) ?></td>
                <td><?= htmlspecialchars(implode(", ", array_unique($group['taglie']))) ?></td>
                <td><?= htmlspecialchars($group['stagione']) ?></td>
                <td><?= $group['sponsor'] ? htmlspecialchars(implode(", ", array_unique($group['sponsor']))) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
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
