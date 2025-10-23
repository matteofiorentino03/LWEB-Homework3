<?php
session_start();
require_once __DIR__ . '/connect.php';

//  Controllo ruolo: solo "Gestore"
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

$errore = "";
$successo = "";
$giocatori = [];

//  Homepage coerente con il ruolo
$homepage_link = 'homepage_gestore.php';

// Caricamento giocatori da giocatori.xml
$giocatoriXML = simplexml_load_file("xml/giocatori.xml");
foreach ($giocatoriXML->giocatore as $g) {
    $giocatori[] = [
        'ID' => (string)$g->ID,
        'nome' => (string)$g->nome,
        'cognome' => (string)$g->cognome,
        'cf' => (string)$g->cf
    ];
}

// Eliminazione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ID']) && $_POST['ID'] !== "") {
    $id = $_POST['ID'];

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->load("xml/giocatori.xml");

    $found = false;
    foreach ($dom->getElementsByTagName("giocatore") as $g) {
        $idNode = $g->getElementsByTagName("ID")[0];
        if ($idNode && $idNode->nodeValue == $id) {
            $g->parentNode->removeChild($g);
            $found = true;
            break;
        }
    }

    if ($found) {
        $dom->save("xml/giocatori.xml");

        // Elimina anche dalle statistiche dei ruoli
        $ruoli = [
            'portieri.xml' => 'portiere',
            'difensori.xml' => 'difensore',
            'centrocampisti.xml' => 'centrocampista',
            'attaccanti.xml' => 'attaccante',
        ];

        foreach ($ruoli as $file => $tag) {
            $path = "xml/$file";
            if (!file_exists($path)) continue;

            $domRuolo = new DOMDocument();
            $domRuolo->preserveWhiteSpace = false;
            $domRuolo->formatOutput = true;
            $domRuolo->load($path);

            $giocatoriRuolo = $domRuolo->getElementsByTagName($tag);
            foreach ($giocatoriRuolo as $gr) {
                $idRuolo = $gr->getElementsByTagName("ID_giocatore")[0];
                if ($idRuolo && $idRuolo->nodeValue == $id) {
                    $gr->parentNode->removeChild($gr);
                    break;
                }
            }

            $domRuolo->save($path);
        }

        $successo = "Giocatore eliminato con successo.";
    } else {
        $errore = "Giocatore non trovato.";
    }

    // Ricarica elenco aggiornato
    $giocatori = [];
    $giocatoriXML = simplexml_load_file("xml/giocatori.xml");
    foreach ($giocatoriXML->giocatore as $g) {
        $giocatori[] = [
            'ID' => (string)$g->ID,
            'nome' => (string)$g->nome,
            'cognome' => (string)$g->cognome,
            'cf' => (string)$g->cf
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Elimina Giocatore</title>
    <link rel="stylesheet" href="styles/style_elimina_g.css">
</head>
<body>
<header>
  <div class="header-left">
    <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
      <div class="logo-container">
        <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo">
      </div>
    </a>
  </div>

  <div class="header-center">
    <h1><a href="<?= htmlspecialchars($homepage_link) ?>" class="brand">PLAYERBASE</a></h1>
  </div>

  <div class="header-right utente-container">
    <div class="logout"><a href="?logout=true">Logout</a></div>
  </div>
</header>

<main class="page">
    <section class="card">
        <h2 class="page-title">Elimina un Giocatore</h2>

        <?php if ($errore):   ?><p class="alert alert-error"><?= $errore ?></p><?php endif; ?>
        <?php if ($successo): ?><p class="alert alert-success"><?= $successo ?></p><?php endif; ?>

        <form method="post" class="narrow" onsubmit="return confermaEliminazione();">
            <label class="label" for="ID">Seleziona Giocatore:</label>
            <select name="ID" id="ID" required>
                <option value="">-- Seleziona --</option>
                <?php foreach ($giocatori as $g): ?>
                    <option value="<?= htmlspecialchars($g['ID']) ?>">
                        <?= htmlspecialchars($g['cognome'] . ' ' . $g['nome']) ?> (<?= htmlspecialchars($g['cf']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-danger">Elimina Giocatore</button>
        </form>
    </section>
</main>

<footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>

<script>
function confermaEliminazione() {
    const sel = document.getElementById('ID');
    const txt = sel.options[sel.selectedIndex]?.text || 'questo giocatore';
    return confirm(`Confermi l'eliminazione di ${txt} e di tutti i relativi dati?`);
}
</script>
</body>
</html>
