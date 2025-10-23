<?php
session_start();

/* ===================== CONTROLLO ACCESSO ===================== */
$is_logged = isset($_SESSION['Username']);
$ruolo = $is_logged && isset($_SESSION['Ruolo']) ? strtolower($_SESSION['Ruolo']) : null;

//  Se è amministratore (ed è loggato), blocca accesso
if ($is_logged && $ruolo === 'amministratore') {
    header("Location: entering.html");
    exit();
}

/* ===================== LOGOUT ===================== */
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

/* ===================== HOMEPAGE LINK ===================== */
if ($is_logged) {
    if ($ruolo === 'cliente') {
        $homepage_link = 'homepage_user.php';
    } 
} else {
    $homepage_link = 'homepage_user.php';
}


/* ==================== FUNZIONI UTILI ==================== */
function caricaGiocatori($path) {
    $giocatori = [];
    if (!file_exists($path)) return $giocatori;

    $xml = simplexml_load_file($path);
    foreach ($xml->giocatore as $g) {
        $id = (int)$g->ID;
        $giocatori[$id] = [
            'nome' => (string)$g->nome,
            'cognome' => (string)$g->cognome,
            'maglia' => (string)$g->num_maglia,
            'presenze' => (int)$g->presenze,
        ];
    }
    return $giocatori;
}

function caricaGolRuolo($path, $ruolo_nome) {
    $gol = [];
    if (!file_exists($path)) return $gol;

    $xml = simplexml_load_file($path);
    foreach ($xml->{$ruolo_nome} as $r) {
        $id = (int)$r->ID_giocatore;
        $gol[$id] = [
            'gol_fatti' => (int)$r->gol_fatti,
            'ruolo' => ucfirst($ruolo_nome)
        ];
    }
    return $gol;
}

/* ==================== LETTURA DATI ==================== */
$giocatori = caricaGiocatori("xml/giocatori.xml");
$portieri = caricaGolRuolo("xml/portieri.xml", "portiere");
$difensori = caricaGolRuolo("xml/difensori.xml", "difensore");
$centrocampisti = caricaGolRuolo("xml/centrocampisti.xml", "centrocampista");
$attaccanti = caricaGolRuolo("xml/attaccanti.xml", "attaccante");

/* ==================== COSTRUZIONE CLASSIFICA ==================== */
$classifica = [];

foreach ($giocatori as $id => $info) {
    $ruolo = "—";
    $gol_fatti = 0;

    if (isset($portieri[$id])) {
        $ruolo = "Portiere";
        $gol_fatti = $portieri[$id]['gol_fatti'];
    } elseif (isset($difensori[$id])) {
        $ruolo = "Difensore";
        $gol_fatti = $difensori[$id]['gol_fatti'];
    } elseif (isset($centrocampisti[$id])) {
        $ruolo = "Centrocampista";
        $gol_fatti = $centrocampisti[$id]['gol_fatti'];
    } elseif (isset($attaccanti[$id])) {
        $ruolo = "Attaccante";
        $gol_fatti = $attaccanti[$id]['gol_fatti'];
    }

    $classifica[] = [
        'nome' => $info['nome'],
        'cognome' => $info['cognome'],
        'maglia' => $info['maglia'],
        'presenze' => $info['presenze'],
        'ruolo' => $ruolo,
        'gol_fatti' => $gol_fatti
    ];
}

/* ==================== ORDINA PER GOL ==================== */
usort($classifica, function ($a, $b) {
    if ($b['gol_fatti'] !== $a['gol_fatti']) {
        return $b['gol_fatti'] - $a['gol_fatti'];
    }
    return strcmp($a['cognome'], $b['cognome']);
});

/* ==================== EXPORT CSV ==================== */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=classifica_marcatori.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Nome', 'Cognome', 'Numero Maglia', 'Presenze', 'Ruolo', 'Gol Fatti']);

    foreach ($classifica as $riga) {
        fputcsv($output, [
            $riga['nome'],
            $riga['cognome'],
            $riga['maglia'],
            $riga['presenze'],
            $riga['ruolo'],
            $riga['gol_fatti']
        ]);
    }

    fclose($output);
    exit();
}
?>

<!-- ==================== HTML ==================== -->
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Classifica Marcatori</title>
    <link rel="stylesheet" href="styles/style_visualizzazione_cm.css">
    <style>
        td:nth-child(2) {
            text-align: left !important;
        }
    </style>
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
        <div class="logout"><?php if ($is_logged): ?>
                    <a href="?logout=true"><p>Logout</p></a>
                <?php else: ?>
                    <a href="entering.html"><p>Login/Registrati</p></a>
                <?php endif; ?></div>
    </div>
</header>

<div class="main-container">
    <h2>Classifica Marcatori</h2>

    <div class="controls no-print">
        <button onclick="window.print()" class="btn red">Stampa in PDF</button>
        <a href="?export=csv" class="btn orange">Esporta CSV</a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Cognome</th>
                    <th>Numero Maglia</th>
                    <th>Presenze</th>
                    <th>Ruolo</th>
                    <th>Gol Fatti</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classifica as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nome']) ?></td>
                    <td style="text-align:left"><?= htmlspecialchars($row['cognome']) ?></td>
                    <td><?= htmlspecialchars($row['maglia']) ?></td>
                    <td><?= htmlspecialchars($row['presenze']) ?></td>
                    <td><?= htmlspecialchars($row['ruolo']) ?></td>
                    <td><?= htmlspecialchars($row['gol_fatti']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati. </p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>
</body>
</html>
