<?php
session_start();

/* ==========================
    Controllo Accesso
========================== */
if (!isset($_SESSION['Username']) || !isset($_SESSION['Ruolo'])) {
    header("Location: entering.html");
    exit();
}

$ruolo = strtolower($_SESSION['Ruolo']);

// Solo Gestore o Amministratore
if ($ruolo !== 'gestore' && $ruolo !== 'amministratore') {
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
   CONNESSIONE DB
========================== */
require_once __DIR__ . '/connect.php';

try {
    $conn = db();            
    $mysqli = $conn;         
} catch (Throwable $e) {
    die("Errore DB: " . $e->getMessage());
}

/* ==========================
   VARIABILI
========================== */
$errore = "";
$successo = "";
$utente  = null;

/* HOMEPAGE LINK IN BASE AL RUOLO */
$homepage_link = ($ruolo === 'gestore') ? 'homepage_gestore.php' : 'homepage_admin.php';

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

    if (!$utente) {
        $errore = "Utente non trovato.";
    } else {
        // Calcolo reputazione se mancante o fuori range
        if (!isset($utente['reputazione']) || $utente['reputazione'] < 0) {
            $utente['reputazione'] = 0;
        } elseif ($utente['reputazione'] > 100) {
            $utente['reputazione'] = 100;
        }
    }
}

/* Salva modifiche */
if (isset($_POST['update']) && isset($_POST['ID'])) {
    $id       = (int)$_POST['ID'];
    $cf       = trim($_POST['CF'] ?? '');
    $username = trim($_POST['Username'] ?? '');
    $password = trim($_POST['Password_Utente'] ?? '');
    $ruoloU   = $_POST['Ruolo'] ?? '';
    $status   = $_POST['Status'] ?? '';  

    if ($cf === '' || $username === '' || $password === '') {
        $errore = "Compila tutti i campi obbligatori.";
    }

    if (!in_array($ruoloU, ['Cliente','Amministratore','Gestore'], true)) {
        $errore = "Ruolo non valido.";
    }

    if (!in_array($status, ['attivo','bannato'], true)) {
        $errore = "Status non valido.";
    }

    // Crediti solo per Cliente
    if ($ruoloU === 'Cliente') {
        if ($_POST['Crediti'] === '' || $_POST['Crediti'] === null) {
            $crediti = null;
        } elseif (!is_numeric(str_replace(',', '.', $_POST['Crediti']))) {
            $errore = "Crediti deve essere numerico.";
        } else {
            $crediti = round((float)str_replace(',', '.', $_POST['Crediti']), 2);
        }
    } else {
        $crediti = null; 
    }

    if ($errore === "") {
        if ($crediti === null) {
            $stmt = $mysqli->prepare("
                UPDATE Utenti
                   SET cf=?, username=?, Password_Utente=?, ruolo=?, status=?, crediti=NULL
                 WHERE ID=?");
            $stmt->bind_param("sssssi", $cf, $username, $password, $ruoloU, $status, $id);
        } else {
            $stmt = $mysqli->prepare("
                UPDATE Utenti
                   SET cf=?, username=?, Password_Utente=?, ruolo=?, status=?, crediti=?
                 WHERE ID=?");
            $stmt->bind_param("sssssdi", $cf, $username, $password, $ruoloU, $status, $crediti, $id);
        }

        if ($stmt->execute()) {
            $successo = "Utente aggiornato con successo.";
            $stmt = $mysqli->prepare("SELECT * FROM Utenti WHERE ID=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $utente = $stmt->get_result()->fetch_assoc();

            // Assicura che la reputazione sia coerente anche dopo update
            if (!isset($utente['reputazione']) || $utente['reputazione'] < 0) {
                $utente['reputazione'] = 0;
            } elseif ($utente['reputazione'] > 100) {
                $utente['reputazione'] = 100;
            }

        } else {
            $errore = "Errore durante l'aggiornamento: " . $stmt->error;
        }
    }
}

/* ==========================
   FUNZIONE COLORE REPUTAZIONE
========================== */
function getReputationColor($val) {
    if ($val < 35) return 'rgb(141, 8, 8)';
    if ($val < 69) return 'rgba(163, 126, 2, 0.88)';
    return 'rgba(70, 179, 7, 0.88)';
}
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
        <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
            <div class='logo-container'>
                <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo">
            </div>
        </a>
        <h1><a href="<?= htmlspecialchars($homepage_link) ?>" style="color:inherit;text-decoration:none;">PLAYERBASE</a></h1>
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
                    <select class="input" id="Ruolo" name="Ruolo" onchange="toggleExtra()" required>
                        <option value="Cliente" <?= $utente['ruolo']==='Cliente' ? 'selected':'' ?>>CLIENTE</option>
                        <option value="Amministratore" <?= $utente['ruolo']==='Amministratore' ? 'selected':'' ?>>AMMINISTRATORE</option>
                        <option value="Gestore" <?= $utente['ruolo']==='Gestore' ? 'selected':'' ?>>GESTORE</option>
                    </select>
                </div>

                <div class="col">
                    <label class="label" for="Status">Status</label>
                    <div class="status-row">
                        <select class="input" id="Status" name="Status" onchange="paintBadge()" required>
                            <?php $st = strtolower($utente['status'] ?? 'attivo'); ?>
                            <option value="attivo"       <?= $st==='attivo' ? 'selected':'' ?>>ATTIVO</option>
                            <option value="bannato"      <?= $st==='bannato' ? 'selected':'' ?>>BANNATO</option>
                        </select>
                        <span id="statusBadge" class="badge">ATTIVO</span>
                    </div>
                </div>

                <!-- Crediti -->
                <div class="col" id="creditiCol">
                    <label class="label" for="Crediti">Crediti (facoltativo)</label>
                    <input class="input" type="number" step="0.01" id="Crediti" name="Crediti"
                        value="<?= $utente['crediti']===null ? '' : htmlspecialchars($utente['crediti']) ?>"
                        placeholder="es. 25.50">
                </div>

                <?php if ($utente['ruolo'] === 'Cliente'): ?>
                <div class="col" id="reputazioneCol">
                    <label class="label">Reputazione</label>
                    <div class="reputation-box" style="background-color: <?= getReputationColor($utente['reputazione']) ?>;">
                        <?= htmlspecialchars($utente['reputazione']) ?> / 100
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <button type="submit" name="update" class="btn-submit">Salva Modifiche</button>
        </form>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
        <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
    </footer>

    <script>
    function toggleExtra() {
        const ruolo = document.getElementById('Ruolo').value;
        const creditiCol = document.getElementById('creditiCol');
        const reputazioneCol = document.getElementById('reputazioneCol');
        const inpCrediti = document.getElementById('Crediti');

        if (ruolo === 'Cliente') {
            if (creditiCol) creditiCol.style.display = '';
            if (reputazioneCol) reputazioneCol.style.display = '';
        } else {
            if (creditiCol) creditiCol.style.display = 'none';
            if (reputazioneCol) reputazioneCol.style.display = 'none';
            if (inpCrediti) inpCrediti.value = '';
        }
    }

    function paintBadge() {
        const sel   = document.getElementById('Status');
        const badge = document.getElementById('statusBadge');
        const val   = sel.value;
        badge.textContent = val.toUpperCase();
        badge.className = 'badge'; 
        if (val === 'attivo')       badge.classList.add('badge--attivo');
        else if (val === 'bannato') badge.classList.add('badge--bannato');
    }

    // init on load
    toggleExtra();
    paintBadge();
    </script>
</body>
</html>