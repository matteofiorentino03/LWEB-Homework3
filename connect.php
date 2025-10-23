<?php
/**
 * connect.php
 * Gestisce la connessione al database MySQL per PlayerBase.
 * 
 * - server() → connessione al server MySQL (senza DB)
 * - db()     → connessione al DB principale (APP_DB_NAME)
 */

declare(strict_types=1);

// === Nome database principale ===
define('APP_DB_DEFAULT_NAME', 'pbdef');

// Credenziali di default
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = APP_DB_DEFAULT_NAME;

// Se esiste config.php, lo carica
$configPath = __DIR__ . '/config.php';
if (is_file($configPath)) {
    $cfg = require $configPath;
    if (is_array($cfg)) {
        $DB_HOST = $cfg['host'] ?? $DB_HOST;
        $DB_USER = $cfg['user'] ?? $DB_USER;
        $DB_PASS = $cfg['pass'] ?? $DB_PASS;
        $DB_NAME = $cfg['name'] ?? $DB_NAME;
    }
}

// Esporta le costanti globali
define('APP_DB_HOST', $DB_HOST);
define('APP_DB_USER', $DB_USER);
define('APP_DB_PASS', $DB_PASS);
define('APP_DB_NAME', $DB_NAME);
define('TB_UTENTI', 'Utenti');

/**
 * Connessione al database (con selezione DB)
 */
function db(): mysqli {
    $conn = new mysqli(APP_DB_HOST, APP_DB_USER, APP_DB_PASS, APP_DB_NAME);
    if ($conn->connect_error) {
        die('Errore DB: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * Connessione solo al server MySQL
 */
function server(): mysqli {
    $conn = new mysqli(APP_DB_HOST, APP_DB_USER, APP_DB_PASS);
    if ($conn->connect_error) {
        die('Errore connessione server: ' . $conn->connect_error);
    }
    return $conn;
}
?>