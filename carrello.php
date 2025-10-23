<?php
session_start();
require_once __DIR__ . '/connect.php';

if (!isset($_SESSION['ID_Utente'])) {
    header("Location: login.php");
    exit;
}

$utente_id = $_SESSION['ID_Utente'];
$checkout_msg = "";
$dataUpd = date('d/m/Y H:i');

// Percorsi file XML
$carrelli_path = "xml/carrelli.xml";
$maglie_path = "xml/maglie.xml";
$compra_path = "xml/compra.xml";
$maglie_giocatore_path = "xml/maglie_giocatore.xml";
$maglie_personalizzate_path = "xml/maglie_personalizzate.xml";

// === Carica maglie.xml per tipo e stagione ===
$maglieXML = file_exists($maglie_path) ? simplexml_load_file($maglie_path) : null;
function getMagliaInfo($idMaglia, $maglieXML) {
    if (!$maglieXML) return ['tipo' => 'Sconosciuto', 'stagione' => '-'];
    foreach ($maglieXML->maglia as $m) {
        if ((string)$m->ID == (string)$idMaglia) {
            return [
                'tipo' => (string)$m->tipo,
                'stagione' => (string)$m->stagione
            ];
        }
    }
    return ['tipo' => 'Sconosciuto', 'stagione' => '-'];
}

// Caricamento carrello
$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
if (file_exists($carrelli_path)) {
    $dom->load($carrelli_path);
    $carrelli = $dom->getElementsByTagName("carrello");
    $carrelloNode = null;
    foreach ($carrelli as $c) {
        if ($c->getElementsByTagName("ID_utente")->item(0)->nodeValue == $utente_id) {
            $carrelloNode = $c;
            break;
        }
    }
} else {
    $carrelloNode = null;
}

/* ===== RIMOZIONE ARTICOLO ===== */
if (isset($_POST['rimuovi_articolo'])) {
    $id_da_rimuovere = $_POST['id_carrello'] ?? '';
    if ($carrelloNode) {
        foreach ($carrelloNode->getElementsByTagName("articolo") as $articolo) {
            $id_carrello = $articolo->getElementsByTagName("ID_carrello")->item(0)->nodeValue;
            if ($id_carrello == $id_da_rimuovere) {
                $articolo->parentNode->removeChild($articolo);
                break;
            }
        }
        $dom->save($carrelli_path);
        header("Location: carrello.php");
        exit;
    }
}

/* ===== PROCEDURA DI PAGAMENTO ===== */
if (isset($_POST['procedi_pagamento'])) {
    $indirizzo = trim($_POST['indirizzo_consegna'] ?? '');
    if ($indirizzo === '') {
        $checkout_msg = " Inserisci un indirizzo di consegna valido.";
    } elseif ($carrelloNode) {
        $data_compra = date('Y-m-d');

        // === Crea o carica i file XML di destinazione
        $dom_compra = new DOMDocument(); $dom_compra->preserveWhiteSpace = false; $dom_compra->formatOutput = true;
        if (file_exists($compra_path)) $dom_compra->load($compra_path);
        else $dom_compra->appendChild($dom_compra->createElement("compra"));
        $rootCompra = $dom_compra->getElementsByTagName("compra")->item(0);

        $dom_gioc = new DOMDocument(); $dom_gioc->preserveWhiteSpace = false; $dom_gioc->formatOutput = true;
        if (file_exists($maglie_giocatore_path)) $dom_gioc->load($maglie_giocatore_path);
        else $dom_gioc->appendChild($dom_gioc->createElement("maglie_giocatore"));
        $rootGioc = $dom_gioc->getElementsByTagName("maglie_giocatore")->item(0);

        $dom_pers = new DOMDocument(); $dom_pers->preserveWhiteSpace = false; $dom_pers->formatOutput = true;
        if (file_exists($maglie_personalizzate_path)) $dom_pers->load($maglie_personalizzate_path);
        else $dom_pers->appendChild($dom_pers->createElement("maglie_personalizzate"));
        $rootPers = $dom_pers->getElementsByTagName("maglie_personalizzate")->item(0);

        $netto = 0; 
        $bonus = 0;

        foreach ($carrelloNode->getElementsByTagName("articolo") as $articolo) {
            $tipo = strtolower($articolo->getElementsByTagName("tipo_maglia")->item(0)->nodeValue);
            $id_maglia = intval($articolo->getElementsByTagName("ID_maglia")->item(0)->nodeValue);
            $prezzo_netto = floatval($articolo->getElementsByTagName("prezzo_netto_riga")->item(0)->nodeValue);
            $bonus_prev = floatval($articolo->getElementsByTagName("bonus_previsto")->item(0)->nodeValue ?? 0);
            $supplemento = intval($articolo->getElementsByTagName("supplemento")->item(0)->nodeValue ?? 0);

            $netto += $prezzo_netto; 
            $bonus += $bonus_prev;

            $ordini = $rootCompra->getElementsByTagName("ordine");
            $new_id = ($ordini->length > 0)
                ? intval($ordini->item($ordini->length - 1)->getElementsByTagName("ID")->item(0)->nodeValue) + 1
                : 1;

            // === Aggiungi ordine a compra.xml
            $ordine = $dom_compra->createElement("ordine");
            $ordine->appendChild($dom_compra->createElement("ID", $new_id));
            $ordine->appendChild($dom_compra->createElement("ID_Utente", $utente_id));
            $ordine->appendChild($dom_compra->createElement("ID_Maglia", $id_maglia));
            $ordine->appendChild($dom_compra->createElement("pagamento_finale", number_format($prezzo_netto, 2, '.', '')));
            $ordine->appendChild($dom_compra->createElement("indirizzo_consegna", htmlspecialchars($indirizzo)));
            $ordine->appendChild($dom_compra->createElement("data_compra", $data_compra));
            $rootCompra->appendChild($ordine);

            // === MAGLIA GIOCATORE
            if ($tipo === 'giocatore') {
                $id_gioc = $articolo->getElementsByTagName("ID_giocatore")->length ? $articolo->getElementsByTagName("ID_giocatore")->item(0)->nodeValue : '';
                $logo = $articolo->getElementsByTagName("Logo")->length ? $articolo->getElementsByTagName("Logo")->item(0)->nodeValue : '';

                $personal = $dom_gioc->createElement("personalizzazione");
                $personal->appendChild($dom_gioc->createElement("ID", $new_id));
                $personal->appendChild($dom_gioc->createElement("Supplemento", $supplemento));
                if ($logo) $personal->appendChild($dom_gioc->createElement("Logo", $logo));
                if ($id_gioc) $personal->appendChild($dom_gioc->createElement("ID_Giocatore", $id_gioc));
                $personal->appendChild($dom_gioc->createElement("ID_Maglia", $id_maglia));
                $rootGioc->appendChild($personal);
            }

            // === MAGLIA PERSONALIZZATA
            elseif ($tipo === 'personalizzata') {
                $nome = $articolo->getElementsByTagName("nome_pers")->length ? $articolo->getElementsByTagName("nome_pers")->item(0)->nodeValue : '';
                $num = $articolo->getElementsByTagName("num_pers")->length ? $articolo->getElementsByTagName("num_pers")->item(0)->nodeValue : '';
                $logo = $articolo->getElementsByTagName("Logo")->length ? $articolo->getElementsByTagName("Logo")->item(0)->nodeValue : '';

                $maglia = $dom_pers->createElement("maglia");
                $maglia->appendChild($dom_pers->createElement("ID", $new_id));
                $maglia->appendChild($dom_pers->createElement("ID_Maglia", $id_maglia));
                $maglia->appendChild($dom_pers->createElement("supplemento", $supplemento));
                if ($logo) $maglia->appendChild($dom_pers->createElement("Logo", $logo));
                if ($nome) $maglia->appendChild($dom_pers->createElement("nome", $nome));
                if ($num) $maglia->appendChild($dom_pers->createElement("num_maglia", $num));
                $rootPers->appendChild($maglia);
            }
        }

        // Salvataggio
        $dom_compra->save($compra_path);
        $dom_gioc->save($maglie_giocatore_path);
        $dom_pers->save($maglie_personalizzate_path);
        $carrelloNode->parentNode->removeChild($carrelloNode);
        $dom->save($carrelli_path);

        // Aggiornamento crediti
        $conn = db();
        $stmt = $conn->prepare("UPDATE Utenti SET crediti = crediti - ? + ? WHERE ID = ?");
        $stmt->bind_param("ddi", $netto, $bonus, $utente_id);
        $stmt->execute();

        $checkout_msg = " Acquisto completato con successo! Hai speso €" .
            number_format($netto, 2, ',', '.') . " e guadagnato un bonus di €" .
            number_format($bonus, 2, ',', '.');
        $carrelloNode = null;
    }
}

/* === Calcolo riepilogo === */
$totale_lordo = 0;
$totale_netto = 0;
$totale_sconti = 0;
$bonus_totale = 0;
if ($carrelloNode) {
    foreach ($carrelloNode->getElementsByTagName("articolo") as $articolo) {
        $prezzo = floatval($articolo->getElementsByTagName("prezzo_unitario")->item(0)->nodeValue);
        $netto = floatval($articolo->getElementsByTagName("prezzo_netto_riga")->item(0)->nodeValue);
        $bonus = floatval($articolo->getElementsByTagName("bonus_previsto")->item(0)->nodeValue ?? 0);
        $totale_lordo += $prezzo;
        $totale_netto += $netto;
        $totale_sconti += $prezzo - $netto;
        $bonus_totale += $bonus;
    }
}

$homepage_link = ($_SESSION['Ruolo'] === 'Gestore') ? 'homepage_gestore.php' :
                 (($_SESSION['Ruolo'] === 'Amministratore') ? 'homepage_admin.php' : 'homepage_user.php');
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Carrello — Playerbase</title>
  <link rel="stylesheet" href="styles/style_carrello.css">
</head>
<body>
<header>
  <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
    <div class="logo-container"><img src="img/AS_Roma_Logo_2017.svg.png" alt="Logo" class="logo" /></div>
  </a>
  <h1><a href="<?= htmlspecialchars($homepage_link) ?>" style="color:inherit;text-decoration:none;">PLAYERBASE</a></h1>
  <div class="utente-container"><div class="logout"><a href="?logout=true">Logout</a></div></div>
</header>

<main class="page">
  <h2 class="page-title">Carrello</h2>
  <p class="data-carrello">Ultimo aggiornamento: <strong><?= $dataUpd ?></strong></p>

  <?php if ($carrelloNode): ?>
  <section class="cart-section">
    <table class="cart-table">
      <thead>
        <tr>
          <th>Tipo Maglia</th>
          <th>Descrizione</th>
          <th>Prezzo (€)</th>
          <th>Sconto (%)</th>
          <th>Netto (€)</th>
          <th>Bonus (€)</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($carrelloNode->getElementsByTagName("articolo") as $articolo): ?>
          <?php
            $tipo = strtolower($articolo->getElementsByTagName("tipo_maglia")->item(0)->nodeValue);
            $id_maglia = $articolo->getElementsByTagName("ID_maglia")->item(0)->nodeValue;
            $info = getMagliaInfo($id_maglia, $maglieXML);
            $descrizione = ucfirst($info['tipo']) . " • " . $info['stagione'];

            $prezzo = $articolo->getElementsByTagName("prezzo_unitario")->item(0)->nodeValue;
            $sconto = $articolo->getElementsByTagName("percentuale_sconto")->item(0)->nodeValue ?? 0;
            $netto = $articolo->getElementsByTagName("prezzo_netto_riga")->item(0)->nodeValue;
            $bonus = $articolo->getElementsByTagName("bonus_previsto")->item(0)->nodeValue ?? 0;
            $id = $articolo->getElementsByTagName("ID_carrello")->item(0)->nodeValue;

            $taglia = $articolo->getElementsByTagName("taglia")->length ? $articolo->getElementsByTagName("taglia")->item(0)->nodeValue : '';
            $logo = $articolo->getElementsByTagName("Logo")->length ? $articolo->getElementsByTagName("Logo")->item(0)->nodeValue : '';
            if ($taglia) $descrizione .= " • $taglia";
            if ($logo) $descrizione .= " • $logo";

            if ($tipo === 'personalizzata') {
                $nome = $articolo->getElementsByTagName("nome_pers")->length ? $articolo->getElementsByTagName("nome_pers")->item(0)->nodeValue : '';
                $num = $articolo->getElementsByTagName("num_pers")->length ? $articolo->getElementsByTagName("num_pers")->item(0)->nodeValue : '';
                if ($nome || $num) $descrizione .= " • Personalizzata: $nome #$num";
            } elseif ($tipo === 'giocatore') {
                $nome_gioc = $articolo->getElementsByTagName("nome_giocatore")->length ? $articolo->getElementsByTagName("nome_giocatore")->item(0)->nodeValue : '';
                if ($nome_gioc) $descrizione .= " • $nome_gioc";
            }
          ?>
          <tr>
            <td><?= htmlspecialchars(ucfirst($tipo)) ?></td>
            <td><?= htmlspecialchars($descrizione) ?></td>
            <td><?= number_format($prezzo, 2, ',', '.') ?></td>
            <td><?= $sconto ?>%</td>
            <td><?= number_format($netto, 2, ',', '.') ?></td>
            <td><?= $bonus ?></td>
            <td>
              <form method="post" style="display:inline;">
                <input type="hidden" name="id_carrello" value="<?= htmlspecialchars($id) ?>">
                <button type="submit" name="rimuovi_articolo" class="pill-remove">🗑️</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- === RIEPILOGO FINALE === -->
    <section class="riepilogo-carrello" style="margin-top:30px; text-align:center;">
      <h3 style="margin-bottom:15px; font-size:1.4em; color:#800000;">Riepilogo Carrello</h3>
      <table class="summary-table" style="margin:0 auto; border-collapse:collapse; min-width:320px;">
        <tr><th style="text-align:left; padding:6px 10px;">Totale Lordo</th><td style="text-align:right; padding:6px 10px;"><?= number_format($totale_lordo, 2, ',', '.') ?> €</td></tr>
        <tr><th style="text-align:left; padding:6px 10px;">Totale Sconti</th><td style="text-align:right; padding:6px 10px; color:#008000;">-<?= number_format($totale_sconti, 2, ',', '.') ?> €</td></tr>
        <tr><th style="text-align:left; padding:6px 10px;">Totale Netto</th><td style="text-align:right; padding:6px 10px; font-weight:bold;"><?= number_format($totale_netto, 2, ',', '.') ?> €</td></tr>
        <tr><th style="text-align:left; padding:6px 10px;">Bonus Totale</th><td style="text-align:right; padding:6px 10px;"><?= number_format($bonus_totale, 2, ',', '.') ?> €</td></tr>
      </table>
    </section>

    <form method="post" class="checkout" style="margin-top:25px; text-align:center;">
      <label for="indirizzo_consegna">Indirizzo di consegna:</label><br>
      <input type="text" id="indirizzo_consegna" name="indirizzo_consegna" required placeholder="Inserisci indirizzo completo..." style="margin:10px; width:70%; padding:5px;" />
      <br>
      <button type="submit" name="procedi_pagamento" class="btn primary">Procedi al pagamento</button>
    </form>

    <div style="text-align:center; margin-top:20px;">
      <a href="catalogo_maglie.php" class="btn secondary">Torna al catalogo</a>
    </div>
  </section>

  <?php else: ?>
    <p class="note" style="text-align:center;margin-top:20px;"><em>Il tuo carrello è vuoto.</em></p>
  <?php endif; ?>

  <?php if ($checkout_msg): ?>
    <p class="ok" style="text-align:center;margin-top:20px;"><?= $checkout_msg ?></p>
  <?php endif; ?>
</main>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
  <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
</footer>
</body>
</html>