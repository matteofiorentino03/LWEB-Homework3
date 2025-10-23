<?php
declare(strict_types=1);
require_once __DIR__ . '/connect.php';

$connMsg = "";

/**  Validazione XSD */
function validateXSD(string $xmlPath, string $xsdPath): string {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->load($xmlPath)) return "❌ Errore caricamento $xmlPath";
    if ($dom->schemaValidate($xsdPath)) return "✅ $xmlPath valido contro $xsdPath";
    $err = libxml_get_errors()[0]->message ?? 'Errore sconosciuto';
    libxml_clear_errors();
    return "❌ $xmlPath NON valido<br>Errore: " . htmlspecialchars($err);
}

/**  Validazione DTD */
function validateDTD(string $xmlPath): string {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->load($xmlPath)) return "❌ Errore caricamento $xmlPath";
    if ($dom->validate()) return "✅ $xmlPath valido contro DTD interno";
    $err = libxml_get_errors()[0]->message ?? 'Errore sconosciuto';
    libxml_clear_errors();
    return "❌ $xmlPath NON valido contro DTD<br>Errore: " . htmlspecialchars($err);
}

try {
    $conn = server();
    $connMsg = "✅ Connessione al server MySQL effettuata.";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $DB_NAME = APP_DB_NAME;

        // Crea DB se non esiste
        if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"))
            throw new Exception("Errore creazione database: " . $conn->error);
        if (!$conn->select_db($DB_NAME))
            throw new Exception("Errore selezione database: " . $conn->error);

        // Drop + creazione tabella Utenti
        $conn->begin_transaction();
        $conn->query("DROP TABLE IF EXISTS Utenti");
        $sql = "
            CREATE TABLE Utenti (
                ID INT AUTO_INCREMENT PRIMARY KEY,
                cf VARCHAR(16) NOT NULL,
                username VARCHAR(30) NOT NULL,
                ruolo ENUM('Cliente', 'Gestore','Amministratore'),
                status ENUM('Attivo','Bannato') DEFAULT 'Attivo',
                Password_Utente VARCHAR(35) NOT NULL,
                crediti DECIMAL(8,2),
                reputazione DECIMAL(4,2),
                data_registrazione DATE NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($sql))
            throw new Exception("Errore creazione tabella Utenti: " . $conn->error);

        // Inserimento utenti di default
        $insert = "
            INSERT INTO Utenti (cf, username, ruolo, status, Password_Utente, crediti, reputazione, data_registrazione) VALUES
            ('ADM1N', 'admin', 'Amministratore', 'Attivo', 'cri1234!', NULL, NULL, '2023-09-03'),
            ('US3R1', 'user1', 'Cliente', 'Attivo', 'Us3Er1!', 9710.69, 87.00, '2023-09-04'),
            ('US3R2', 'user2', 'Cliente', 'Attivo', 'Us3Er2!', 3333.69, 69.00, '2025-10-19'),
            ('G3ST1', 'gest1', 'Gestore', 'Attivo', 'G3eSt1!', NULL, NULL, '2023-09-03'),
            ('US3RBAN', 'banned', 'Cliente', 'Bannato', 'B4nned!', 5678.90, 23.00, '2023-09-03');
        ";
        if (!$conn->query($insert))
            throw new Exception("Errore inserimento utenti: " . $conn->error);

        $conn->commit();

        // Scrittura config.php
        $config = "<?php\nreturn [\n" .
            "  'host' => 'localhost',\n" .
            "  'user' => 'root',\n" .
            "  'pass' => '',\n" .
            "  'name' => '$DB_NAME',\n" .
            "];\n";
        file_put_contents(__DIR__ . '/config.php', $config);

        echo "<script>alert('Installazione completata con successo!');window.location='entering.html';</script>";
        exit;
    }

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    $connMsg = "❌ Installazione annullata: " . $e->getMessage();
} finally {
    if (isset($conn)) $conn->close();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Installazione PlayerBase2</title>
<style>
body { font-family: Arial, sans-serif; padding: 20px; }
h3 { margin-top: 30px; }
div { background: #f8f8f8; padding: 10px; border-radius: 8px; }
</style>
</head>
<body>
    <p><?= $connMsg ?></p>
    <form method="post">
        <button type="submit">Avvia installazione del database</button>
    </form>

    <h3>Validazione File XML</h3>
    <div style="font-family:monospace;">
        <?php
        $xml = __DIR__ . "/xml/";

        //  DTD
        echo validateDTD($xml . "agisce.xml") . "<br><br>";

        //  XSD multipli
        $filesXSD = [
            'attaccanti','bonus','carrelli','centrocampisti','compra','contributi',
            'crediti_richieste','difensori','giocatori','maglie','maglie_giocatore',
            'maglie_personalizzate','portieri','sconti'
        ];

        foreach ($filesXSD as $f) {
            $xmlPath = "$xml$f.xml";
            $xsdPath = "$xml$f.xsd";
            echo validateXSD($xmlPath, $xsdPath) . "<br>";
        }
        ?>
    </div>
</body>
</html>