<?php
declare(strict_types=1);
require_once __DIR__ . '/connect.php';

$connMsg = "";

function validateXSD(string $xmlPath, string $xsdPath): string {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);

    if (!$dom->load($xmlPath)) {
        return "❌ Errore caricamento $xmlPath";
    }

    if ($dom->schemaValidate($xsdPath)) {
        return "✅ $xmlPath valido contro $xsdPath";
    } else {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        return "❌ $xmlPath NON valido contro $xsdPath<br>Errore: " . htmlspecialchars($errors[0]->message ?? 'Errore sconosciuto');
    }
}

function validateDTD(string $xmlPath): string {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);

    if (!$dom->load($xmlPath)) {
        return "❌ Errore caricamento $xmlPath";
    }

    if ($dom->validate()) {
        return "✅ $xmlPath valido contro DTD interno";
    } else {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        return "❌ $xmlPath NON valido contro DTD<br>Errore: " . htmlspecialchars($errors[0]->message ?? 'Errore sconosciuto');
    }
}

try {
    $conn = server();
    $connMsg = "✅ Connessione al server MySQL effettuata.";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $DB_NAME = APP_DB_NAME;

        // Crea e seleziona DB
        if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
            throw new Exception("Errore creazione database: " . $conn->error);
        }
        if (!$conn->select_db($DB_NAME)) {
            throw new Exception("Errore selezione database: " . $conn->error);
        }

        // Transazione
        $conn->begin_transaction();

        // Drop e creazione tabella
        $conn->query("DROP TABLE IF EXISTS Utenti");

        $query = "
            CREATE TABLE Utenti (
                ID INT AUTO_INCREMENT PRIMARY KEY,
                cf VARCHAR(16) NOT NULL,
                username VARCHAR(30) NOT NULL,
                ruolo SET('admin','utente'),
                status SET('attivo','bannato','disattivato') DEFAULT 'attivo',
                Password_Utente VARCHAR(35) NOT NULL,
                crediti DECIMAL(6,2)
            );
        ";
        if (!$conn->query($query)) {
            throw new Exception("Errore creazione tabella Utenti: " . $conn->error);
        }

        // Dati di default
        $defaultUsers = "
            INSERT INTO Utenti (cf, username, ruolo, status, Password_Utente, crediti) VALUES
            ('ADM1N', 'admin', 'admin', 'attivo', 'cri1234!', NULL),
            ('US3R1', 'user1', 'utente', 'attivo', 'Us3Er1!', 9710.69),
            ('US3R2', 'user2', 'utente', 'attivo', 'Us3Er2!', 3333.69);
        ";
        if (!$conn->query($defaultUsers)) {
            throw new Exception("Errore inserimento utenti default: " . $conn->error);
        }

        // Commit finale
        $conn->commit();

        // Scrittura config.php
        $configContent = "<?php\nreturn [\n" .
            "    'host' => 'localhost',\n" .
            "    'user' => 'root',\n" .
            "    'pass' => '',\n" .
            "    'name' => '" . addslashes($DB_NAME) . "',\n" .
            "];\n";

        if (file_put_contents(__DIR__ . '/config.php', $configContent) === false) {
            throw new Exception("Impossibile scrivere config.php");
        }

        echo "<script>
            alert('Installazione completata con successo!');
            window.location.href = 'entering.html';
        </script>";
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
<html lang="it" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8" />
    <title>Installazione PlayerBase2</title>
</head>
<body style="font-family:Arial, sans-serif;">

    <?php if (!empty($connMsg)): ?>
        <p style="padding:10px;background:#e8ffe8;border:1px solid #9fd09f;">
            <?php echo $connMsg; ?>
        </p>
    <?php endif; ?>

    <form method="post">
        <button type="submit">Avvia installazione del database</button>
    </form>

    <h3>Validazione File XML</h3>
    <div style="font-family:monospace;">
        <?php
        $basePath = __DIR__ . "/xml/";

        echo validateDTD($basePath . "agisce.xml") . "<br>";

        echo validateXSD($basePath . "attaccanti.xml", $basePath . "attaccanti.xsd") . "<br>";
        echo validateXSD($basePath . "centrocampisti.xml", $basePath . "centrocampisti.xsd") . "<br>";
        echo validateXSD($basePath . "compra.xml", $basePath . "compra.xsd") . "<br>";
        echo validateXSD($basePath . "crediti_richieste.xml", $basePath . "crediti_richieste.xsd") . "<br>";
        echo validateXSD($basePath . "difensori.xml", $basePath . "difensori.xsd") . "<br>";
        echo validateXSD($basePath . "giocatori.xml", $basePath . "giocatori.xsd") . "<br>";
        echo validateXSD($basePath . "maglie.xml", $basePath . "maglie.xsd") . "<br>";
        echo validateXSD($basePath . "maglie_giocatore.xml", $basePath . "maglie_giocatore.xsd") . "<br>";
        echo validateXSD($basePath . "maglie_personalizzate.xml", $basePath . "maglie_personalizzate.xsd") . "<br>";
        echo validateXSD($basePath . "portieri.xml", $basePath . "portieri.xsd") . "<br>";
        ?>
    </div>
</body>
</html>