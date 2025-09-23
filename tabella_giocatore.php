<?php
session_start();

/* Stato sessione */
$is_logged = isset($_SESSION['Username']);
$ruolo     = $is_logged && isset($_SESSION['Ruolo']) ? strtolower($_SESSION['Ruolo']) : null;
$is_admin  = ($ruolo === 'admin');

if ($ruolo === 'admin' && !$is_logged) {
    header("Location: entering.html");
    exit();
}

$homepage_link = ($is_admin ? 'homepage_admin.php' : 'homepage_user.php');

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

// Carica XML
function loadXml($path) {
    return file_exists($path) ? simplexml_load_file($path) : null;
}

$giocatori_xml     = loadXml("xml/giocatori.xml");
$portieri_xml      = loadXml("xml/portieri.xml");
$difensori_xml     = loadXml("xml/difensori.xml");
$centrocampisti_xml= loadXml("xml/centrocampisti.xml");
$attaccanti_xml    = loadXml("xml/attaccanti.xml");

// Funzione per trovare il ruolo + stats
function getRuoloEStatistiche($id) {
    global $portieri_xml, $difensori_xml, $centrocampisti_xml, $attaccanti_xml;

    foreach ($portieri_xml?->portiere ?? [] as $p) {
        if ((string)$p->ID_giocatore === $id) {
            return [
                "ruolo" => "Portiere",
                "stat"  => [
                    "Gol subiti"   => (string)$p->gol_subiti,
                    "Gol fatti"    => (string)$p->gol_fatti,
                    "Assist"       => (string)$p->assist,
                    "Clean sheet"  => (string)$p->clean_sheet,
                    "Ammonizioni"  => (string)$p->ammonizioni,
                    "Espulsioni"   => (string)$p->espulsioni
                ]
            ];
        }
    }

    foreach ($difensori_xml?->difensore ?? [] as $d) {
        if ((string)$d->ID_giocatore === $id) {
            return [
                "ruolo" => "Difensore",
                "stat"  => [
                    "Gol fatti"    => (string)$d->gol_fatti,
                    "Assist"       => (string)$d->assist,
                    "Ammonizioni"  => (string)$d->ammonizioni,
                    "Espulsioni"   => (string)$d->espulsioni
                ]
            ];
        }
    }

    foreach ($centrocampisti_xml?->centrocampista ?? [] as $c) {
        if ((string)$c->ID_giocatore === $id) {
            return [
                "ruolo" => "Centrocampista",
                "stat"  => [
                    "Gol fatti"    => (string)$c->gol_fatti,
                    "Assist"       => (string)$c->assist,
                    "Ammonizioni"  => (string)$c->ammonizioni,
                    "Espulsioni"   => (string)$c->espulsioni
                ]
            ];
        }
    }

    foreach ($attaccanti_xml?->attaccante ?? [] as $a) {
        if ((string)$a->ID_giocatore === $id) {
            return [
                "ruolo" => "Attaccante",
                "stat"  => [
                    "Gol fatti"    => (string)$a->gol_fatti,
                    "Assist"       => (string)$a->assist,
                    "Ammonizioni"  => (string)$a->ammonizioni,
                    "Espulsioni"   => (string)$a->espulsioni
                ]
            ];
        }
    }

    return [ "ruolo" => "-", "stat" => [] ];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <title>Tutti i Giocatori</title>
  <link rel="stylesheet" href="styles/style_visualizzazione_g.css" />
</head>
<body>
<header>
  <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
    <div class="logo-container">
      <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo" />
    </div>
  </a>
  <h1><a href="<?= htmlspecialchars($homepage_link) ?>" style="color:inherit;text-decoration:none;">PLAYERBASE</a></h1>
  <div class="utente-container">
    <?php if ($is_logged): ?>
      <div class="logout"><a href="?logout=true">Logout</a></div>
    <?php else: ?>
      <div class="logout"><a href="entering.html">Login/Registrati</a></div>
    <?php endif; ?>
  </div>
</header>

<div class="main-container">
  <h1>Tutti i Giocatori</h1>
  <div class="table-wrapper">
  <?php if ($giocatori_xml && count($giocatori_xml->giocatore) > 0): ?>
    <table>
      <thead>
        <tr>
          <th>Nome Cognome</th>
          <th>CF</th>
          <th>Altezza</th>
          <th>Numero Maglia</th>
          <th>Data Nascita</th>
          <th>Nazionalità</th>
          <th>Valore di Mercato</th>
          <th>Presenze</th>
          <th>Codice Contratto</th>
          <th>Data Inizio</th>
          <th>Tipo Contratto</th>
          <th>Scadenza</th>
          <th>Stipendio</th>
          <th>Ruolo</th>
          <th>Statistiche</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($giocatori_xml->giocatore as $g): 
        $id = (string)$g->ID;
        $ruolo_stat = getRuoloEStatistiche($id);
      ?>
        <tr>
          <td><?= htmlspecialchars($g->nome . ' ' . $g->cognome) ?></td>
          <td><?= htmlspecialchars($g->cf) ?></td>
          <td><?= htmlspecialchars($g->altezza) ?></td>
          <td><?= htmlspecialchars($g->num_maglia) ?></td>
          <td><?= htmlspecialchars($g->datanascita) ?></td>
          <td><?= htmlspecialchars($g->nazionalita) ?></td>
          <td><?= htmlspecialchars(number_format((float)$g->market_value, 2, ',', '.')) ?></td>
          <td><?= htmlspecialchars($g->presenze) ?></td>
          <td><?= htmlspecialchars($g->cod_contratto) ?></td>
          <td><?= htmlspecialchars($g->Data_inizio) ?></td>
          <td><?= htmlspecialchars($g->Tipo_Contratto) ?></td>
          <td><?= htmlspecialchars($g->Data_scadenza) ?></td>
          <td><?= htmlspecialchars(number_format((float)$g->stipendio, 2, ',', '.')) ?></td>
          <td><?= htmlspecialchars($ruolo_stat['ruolo']) ?></td>
          <td class="role-data">
            <?php foreach ($ruolo_stat['stat'] as $label => $value): ?>
              <strong><?= $label ?>:</strong> <?= htmlspecialchars($value) ?><br>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p style="text-align:center;">Nessun giocatore trovato.</p>
  <?php endif; ?>
  </div>
</div>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const wrapper = document.querySelector('.table-wrapper');
  if (!wrapper) return;
  wrapper.addEventListener('wheel', function(e){
    if (e.deltaY !== 0) {
      e.preventDefault();
      wrapper.scrollLeft += e.deltaX + e.deltaY;
    }
  }, { passive: false });
});
</script>
</body>
</html>