<?php
session_start();

/* Solo admin */
if (!isset($_SESSION['Username']) || ($_SESSION['Ruolo'] ?? '') !== 'admin') {
    header("Location: entering.html");
    exit();
}

/* Logout */
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

require_once __DIR__ . '/connect.php';

try {
    $conn = db();            // connessione centralizzata
    $mysqli = $conn;         // <-- alias, così non devi toccare altro codice che usa $mysqli
} catch (Throwable $e) {
    die("Errore DB: " . $e->getMessage());
}

$errore = "";
$successo = "";
$utente  = null;

/* Elenco utenti */
$utenti = [];
if ($res = $mysqli->query("SELECT ID, username, cf FROM Utenti ORDER BY username ASC")) {
    while ($r = $res->fetch_assoc()) $utenti[] = $r;
}

/* Carica utente selezionato */
if (!empty($_POST['select_user'])) {
    $id = (int)$_POST['select_user'];
    $stmt = $mysqli->prepare("SELECT * FROM Utenti WHERE ID=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $utente = $stmt->get_result()->fetch_assoc();
    if (!$utente) $errore = "Utente non trovato.";
}

/* Salva modifiche */
if (isset($_POST['update']) && isset($_POST['ID'])) {
    $id       = (int)$_POST['ID'];
    $cf       = trim($_POST['CF'] ?? '');
    $username = trim($_POST['Username'] ?? '');
    $password = trim($_POST['Password_Utente'] ?? '');
    $ruolo    = $_POST['Ruolo'] ?? '';
    $status   = $_POST['Status'] ?? '';   // 'attivo' | 'bannato' | 'disattivato'

    if ($cf==='' || $username==='' || $password==='')  $errore = "Compila tutti i campi obbligatori.";
    if (!in_array($ruolo, ['admin','utente'], true))   $errore = "Ruolo non valido.";
    if (!in_array($status,['attivo','bannato','disattivato'],true)) $errore = "Status non valido.";

    // Crediti: gestiti solo per ruolo 'utente'
    if ($ruolo === 'utente') {
        if ($_POST['Crediti'] === '' || $_POST['Crediti'] === null) {
            $crediti = null;
        } elseif (!is_numeric(str_replace(',', '.', $_POST['Crediti']))) {
            $errore = "Crediti deve essere numerico.";
        } else {
            // normalizza ed eventualmente arrotonda a 2 decimali
            $crediti = round((float)str_replace(',', '.', $_POST['Crediti']), 2);
        }
    } else {
        $crediti = null; // forza NULL per admin
    }

    if ($errore === "") {
        if ($crediti === null) {
            $stmt = $mysqli->prepare("
                UPDATE Utenti
                   SET cf=?, username=?, Password_Utente=?, ruolo=?, status=?, crediti=NULL
                 WHERE ID=?");
            $stmt->bind_param("sssssi", $cf, $username, $password, $ruolo, $status, $id);
        } else {
            $stmt = $mysqli->prepare("
                UPDATE Utenti
                   SET cf=?, username=?, Password_Utente=?, ruolo=?, status=?, crediti=?
                 WHERE ID=?");
            // TIPI CORRETTI: s s s s s d i
            $stmt->bind_param("sssssdi", $cf, $username, $password, $ruolo, $status, $crediti, $id);
        }

        if ($stmt->execute()) {
            $successo = "Utente aggiornato con successo.";
            // ricarico il record aggiornato
            $stmt = $mysqli->prepare("SELECT * FROM Utenti WHERE ID=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $utente = $stmt->get_result()->fetch_assoc();
        } else {
            $errore = "Errore durante l'aggiornamento: " . $stmt->error;
        }
    }
}

$homepage_link = 'homepage_admin.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Modifica Utente</title>
  <link rel="stylesheet" href="styles/style_modifica_utente.css">
</head>
<body>
    <header>
        <a href="homepage_admin.php" class="header-link">
            <div class='logo-container'>
                <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo">
            </div>
        </a>
        <h1><a href="homepage_admin.php">PLAYERBASE</a></h1>
        <div class="utente-container">
            <div class="logout"><a href="?logout=true">Logout</a></div>        
        </div>
    </header>

    <main class="page">
    <h2 class="page-title">Modifica Utente</h2>

    <?php if ($errore):   ?><div class="alert alert-error"><?= $errore ?></div><?php endif; ?>
    <?php if ($successo): ?><div class="alert alert-success"><?= $successo ?></div><?php endif; ?>

    <!-- Selettore utente -->
    <form method="post" class="card narrow">
        <label for="select_user" class="label">Seleziona Utente:</label>
        <select id="select_user" name="select_user" class="input" onchange="this.form.submit()" required>
        <option value="">-- Seleziona --</option>
        <?php foreach ($utenti as $u): ?>
            <option value="<?= (int)$u['ID'] ?>" <?= isset($utente['ID']) && (int)$utente['ID']===(int)$u['ID'] ? 'selected':'' ?>>
            <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['cf']) ?>)
            </option>
        <?php endforeach; ?>
        </select>
    </form>

    <?php if ($utente): ?>
    <form method="post" class="card">
        <input type="hidden" name="ID" value="<?= (int)$utente['ID'] ?>">

        <div class="grid">
        <div class="col">
            <label class="label" for="CF">Codice Fiscale</label>
            <input class="input" type="text" id="CF" name="CF" value="<?= htmlspecialchars($utente['cf']) ?>" required>
        </div>

        <div class="col">
            <label class="label" for="Username">Username</label>
            <input class="input" type="text" id="Username" name="Username" value="<?= htmlspecialchars($utente['username']) ?>" required>
        </div>

        <div class="col">
            <label class="label" for="Password_Utente">Password</label>
            <input class="input" type="text" id="Password_Utente" name="Password_Utente" value="<?= htmlspecialchars($utente['Password_Utente']) ?>" required>
        </div>

        <div class="col">
            <label class="label" for="Ruolo">Ruolo</label>
            <select class="input" id="Ruolo" name="Ruolo" onchange="toggleCrediti()" required>
            <option value="utente" <?= $utente['ruolo']==='utente' ? 'selected':'' ?>>UTENTE</option>
            <option value="admin"  <?= $utente['ruolo']==='admin'  ? 'selected':'' ?>>ADMIN</option>
            </select>
        </div>

        <div class="col">
            <label class="label" for="Status">Status</label>
            <div class="status-row">
            <select class="input" id="Status" name="Status" onchange="paintBadge()" required>
                <?php $st = strtolower($utente['status'] ?? 'attivo'); ?>
                <option value="attivo"       <?= $st==='attivo' ? 'selected':'' ?>>ATTIVO</option>
                <option value="bannato"      <?= $st==='bannato' ? 'selected':'' ?>>BANNATO</option>
                <option value="disattivato"  <?= $st==='disattivato' ? 'selected':'' ?>>DISATTIVATO</option>
            </select>
            <span id="statusBadge" class="badge">ATTIVO</span>
            </div>
        </div>

        <div class="col" id="creditiCol">
            <label class="label" for="Crediti">Crediti (facoltativo)</label>
            <input class="input" type="number" step="0.01" id="Crediti" name="Crediti"
                value="<?= $utente['crediti']===null ? '' : htmlspecialchars($utente['crediti']) ?>"
                placeholder="es. 25.50">
        </div>
        </div>

        <button type="submit" name="update" class="btn-submit">Salva Modifiche</button>
    </form>
    <?php endif; ?>
    </main>

    <footer>
    <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
    </footer>

    <script>
    function toggleCrediti() {
    const ruolo = document.getElementById('Ruolo').value;
    const col   = document.getElementById('creditiCol');
    const inp   = document.getElementById('Crediti');
    if (ruolo === 'utente') {
        col.style.display = '';
    } else {
        col.style.display = 'none';
        if (inp) inp.value = '';
    }
    }

    function paintBadge() {
    const sel   = document.getElementById('Status');
    const badge = document.getElementById('statusBadge');
    const val   = sel.value;
    badge.textContent = val.toUpperCase();
    badge.className = 'badge'; // reset
    if (val === 'attivo')       badge.classList.add('badge--attivo');
    else if (val === 'bannato') badge.classList.add('badge--bannato');
    else                        badge.classList.add('badge--disattivato');
    }

    // init on load
    toggleCrediti();
    paintBadge();
    </script>
</body>
</html>