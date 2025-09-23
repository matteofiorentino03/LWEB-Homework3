<?php
/**
 * connect.php
 * Punto unico di accesso al DB.
 *
 * - Usa come default root senza password.
 * - Fornisce:
 *     • server() → connessione al solo server MySQL (senza DB).
 *     • db()     → connessione al database applicativo (pbf o quello definito in config.php).
 *
 * L'unica tabella gestita nel DB è 'Utenti'; tutte le altre entità sono gestite via file XML + DOM.
 */

declare(strict_types=1);

// === Nome database di default ===
define('APP_DB_DEFAULT_NAME', 'pbdef');

// Credenziali di default per sviluppo
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = APP_DB_DEFAULT_NAME;

// Sovrascrive con config.php, se esiste
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
define('APP_DB_NAME', $DB_NAME); // verrà usato in install.php

// === Alias per l'unica tabella ===
define('TB_UTENTI', 'Utenti');

/**
 * Connessione al database MySQL (con selezione DB).
 * Lancia eccezione se fallisce.
 */
function db(): mysqli {
    $conn = @new mysqli(APP_DB_HOST, APP_DB_USER, APP_DB_PASS, APP_DB_NAME);
    if ($conn->connect_error) {
        throw new RuntimeException('Connessione DB fallita: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * Connessione al solo server MySQL (senza selezionare il DB).
 */
function server(): mysqli {
    $conn = @new mysqli(APP_DB_HOST, APP_DB_USER, APP_DB_PASS);
    if ($conn->connect_error) {
        throw new RuntimeException('Connessione server fallita: ' . $conn->connect_error);
    }
    return $conn;
}