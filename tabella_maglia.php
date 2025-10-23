<?php
session_start();

/* ==========================
    CONTROLLO ACCESSO
========================== */
if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo'])) {
    header("Location: entering.html");
    exit();
}

$ruolo = strtolower($_SESSION['Ruolo']);

// Solo Gestore o Cliente
if ($ruolo !== 'gestore' && $ruolo !== 'cliente') {
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

/* ==========================
   HOMEPAGE LINK
========================== */
$homepage_link = ($ruolo === 'gestore') ? 'homepage_gestore.php' : 'homepage_user.php';

// Carica il file XML delle maglie
$maglie_xml = file_exists("xml/maglie.xml") ? simplexml_load_file("xml/maglie.xml") : null;
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <title>Visualizza Maglie</title>
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
    <div class="logout"><a href="?logout=true">Logout</a></div>
  </div>
</header>

<div class="main-container">
  <h1>Tutte le Maglie</h1>
  <div class="table-wrapper">
  <?php if ($maglie_xml && count($maglie_xml->maglia) > 0): ?>
    <table>
      <thead>
        <tr>
          <th>Immagine</th>
          <th>Tipo</th>
          <th>Stagione</th>
          <th>Taglia</th>
          <th>Costo Fisso (€)</th>
          <th>Sponsor</th>
          <th>Descrizione</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($maglie_xml->maglia as $m): ?>
        <tr>
          <td>
            <?php
              $rel = (string)($m->path_immagine ?? '');
              $abs = $rel ? __DIR__ . '/' . str_replace(['\\'], '/', $rel) : '';
              if ($rel && is_file($abs)):
            ?>
              <img src="<?= htmlspecialchars($rel) ?>" alt="Maglia" class="img-maglia">
            <?php else: ?>
              <span style="color:grey;">Nessuna immagine</span>
            <?php endif; ?>
          </td>
          <td><?= ucfirst(htmlspecialchars($m->tipo ?? '')) ?></td>
          <td><?= htmlspecialchars($m->stagione ?? '') ?></td>
          <td><?= htmlspecialchars($m->taglia ?? '') ?></td>
          <td><?= number_format((float)($m->costo_fisso ?? 0), 2, ',', '.') ?></td>
          <td><?= htmlspecialchars($m->Sponsor ?? '') ?></td>
          <td><?= htmlspecialchars($m->descrizione_maglia ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p style="text-align:center;">Nessuna maglia trovata.</p>
  <?php endif; ?>
  </div>
</div>

<footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
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
