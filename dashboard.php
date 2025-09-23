<?php
session_start();

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: entering.html");
    exit();
}

if (!isset($_SESSION['Username'])) {
    header("Location: entering.html");
    exit();
}

$homepage_link = 'homepage_admin.php';

// Connessione al database (usa il nome corretto del tuo DB)
$servername  = "localhost";
$username_db = "root";
$password_db = "";
$dbname      = "playerbase2"; // <-- cambia in "playerbase" se serve

/* ================= DB ================= */
require_once __DIR__ . '/connect.php';

try {
    $conn = db();   // usa la funzione definita in connect.php
} catch (Throwable $e) {
    die("Errore DB: " . $e->getMessage());
}

/* Preleva i dati dalla tabella Utenti */
$sql = "SELECT ID, cf, username, Password_Utente, ruolo, crediti, status
        FROM Utenti
        ORDER BY ruolo DESC, username ASC";
$result = $conn->query($sql);

/* Helper per badge status */
function badge_class($status) {
    $s = strtolower(trim((string)$status));
    return match ($s) {
        'attivo'        => 'badge-success',
        'bannato'       => 'badge-danger',
        'disattivato'   => 'badge-muted',
        default         => 'badge-muted'
    };
}
?>
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="it" lang="it">
<head>
    <meta charset="utf-8" />
    <title>Dashboard Utenti - PLAYERBASE</title>
    <link rel="stylesheet" href="styles/style_dashboard.css" />
</head>
<body>
<header>
    <a href="<?php echo $homepage_link; ?>" class="header-link">
        <div class="logo-container">
            <img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo AS Roma" class="logo" />
        </div>
    </a>
    <h1><a href="<?php echo $homepage_link; ?>" style="color: inherit; text-decoration: none;">PLAYERBASE</a></h1>
    <div class="utente-container">
        <div class="logout"><a href="?logout=true">Logout</a></div>
    </div>
</header>

<div class="testo-iniziale">
    <h2>Dashboard degli Utenti</h2>
</div>

<div class="main-container" style="flex-direction: column; padding: 20px;">
    <?php if ($result && $result->num_rows > 0): ?>
        <table class="tbl">
            <thead>
                <tr>
                    <th>Codice Fiscale</th>
                    <th>Ruolo</th>
                    <th>Username</th>
                    <th>Password Utente</th>
                    <th>Crediti</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <?php
                    $cf       = htmlspecialchars($row['cf']);
                    $ruolo    = strtoupper(htmlspecialchars($row['ruolo']));
                    $user     = htmlspecialchars($row['username']);
                    $pwd      = htmlspecialchars($row['Password_Utente']);
                    $crediti  = is_null($row['crediti']) ? '—' : htmlspecialchars($row['crediti']);
                    $status   = htmlspecialchars($row['status']);
                    $badgeCls = badge_class($row['status']);
                ?>
                <tr>
                    <td><?php echo $cf; ?></td>
                    <td><?php echo $ruolo; ?></td>
                    <td><?php echo $user; ?></td>
                    <td><?php echo $pwd; ?></td>
                    <td><?php echo $crediti; ?></td>
                    <td><span class="badge <?php echo $badgeCls; ?>"><?php echo strtoupper($status ?: '—'); ?></span></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center;">Nessun dato disponibile nella tabella <strong>Utenti</strong>.</p>
    <?php endif; ?>

    <?php $conn->close(); ?>
</div>

<footer>
    <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
</footer>
</body>
</html>