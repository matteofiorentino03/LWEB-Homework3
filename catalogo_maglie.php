<?php
session_start();

/* ===== Header logic ===== */
$is_logged  = isset($_SESSION['Username']);
$ruolo      = $is_logged ? strtolower($_SESSION['Ruolo']) : '';
$is_admin   = ($ruolo === 'admin');
$homepage_link = $is_admin ? 'homepage_admin.php' : 'homepage_user.php';

/* Optional logout */
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

// === Caricamento XML ===
$xml_path = 'xml/maglie.xml';
if (!file_exists($xml_path)) {
    die("Errore: File XML non trovato.");
}
$xml = simplexml_load_file($xml_path);

// === Raggruppamento maglie per (tipo + stagione) ===
$maglie_raggruppate = [];

foreach ($xml->maglia as $maglia) {
    $tipo     = (string) $maglia->tipo;
    $stagione = (string) $maglia->stagione;
    $taglia   = (string) $maglia->taglia;
    $costo    = (float)  $maglia->costo_fisso;
    $img      = (string) $maglia->path_immagine;

    $key = $tipo . '|' . $stagione;

    if (!isset($maglie_raggruppate[$key])) {
        $maglie_raggruppate[$key] = [
            'tipo'     => $tipo,
            'stagione' => $stagione,
            'taglie'   => [],
            'prezzi'   => [],
            'immagini' => [],
        ];
    }

    $maglie_raggruppate[$key]['taglie'][]   = $taglia;
    $maglie_raggruppate[$key]['prezzi'][]   = $costo;
    if ($img !== '') {
        $maglie_raggruppate[$key]['immagini'][] = $img;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <title>Catalogo Maglie</title>
  <link rel="stylesheet" href="styles/style_catalogo_maglia.css" />
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

<main class="page">
  <h2 class="page-title">Catalogo maglie</h2>

  <?php if (!empty($maglie_raggruppate)): ?>
    <section class="cards">
      <?php foreach ($maglie_raggruppate as $group): ?>
        <?php
          $tipo     = ucfirst($group['tipo']);
          $stagione = htmlspecialchars($group['stagione']);

          $taglie = array_unique($group['taglie']);
          $taglie_ordinato = array_intersect(['XS','S','M','L','XL','XXL','XXXL'], $taglie);
          $taglie_str = implode(', ', $taglie_ordinato);

          $prezzi = $group['prezzi'];
          $prezzo_min = min($prezzi);
          $prezzo_max = max($prezzi);
          $prezzo = ($prezzo_min === $prezzo_max)
              ? number_format($prezzo_min, 2, ',', '.').' €'
              : number_format($prezzo_min, 2, ',', '.')."–".number_format($prezzo_max, 2, ',', '.').' €';

          $img = isset($group['immagini'][0]) ? $group['immagini'][0] : '';
          $img_abs = $img ? (__DIR__ . '/' . str_replace('\\','/',$img)) : '';
          $has_img = $img && is_file($img_abs);
        ?>
        <article class="card">
          <div class="card__media">
            <?php if ($has_img): ?>
              <form action="compra_maglia.php" method="post" style="display:inline;">
                <input type="hidden" name="tipo" value="<?= htmlspecialchars($group['tipo']) ?>">
                <input type="hidden" name="stagione" value="<?= $stagione ?>">
                <button type="submit" style="border:none; background:none; padding:0;">
                  <img src="<?= htmlspecialchars($img) ?>" alt="Maglia <?= $tipo ?> <?= $stagione ?>">
                </button>
              </form>
            <?php else: ?>
              <div class="placeholder"><span>Immagine non disponibile</span></div>
            <?php endif; ?>
            <div class="badge badge--tipo"><?= $tipo ?></div>
            <div class="badge badge--stagione"><?= $stagione ?></div>
          </div>

          <div class="card__body">
            <h3 class="card__title"><?= $tipo ?> • <?= $stagione ?></h3>

            <div class="field">
              <span class="field__label">Taglie:</span>
              <span class="chips"><?= $taglie_str ?: '-' ?></span>
            </div>

            <div class="field">
              <span class="field__label">Prezzo:</span>
              <span class="price"><?= $prezzo ?></span>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php else: ?>
    <p class="empty">Nessuna maglia presente a catalogo.</p>
  <?php endif; ?>
</main>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
</footer>
</body>
</html>