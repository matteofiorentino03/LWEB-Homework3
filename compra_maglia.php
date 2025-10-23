<?php
session_start();

if (!isset($_SESSION['Username'])) {
    header("Location: entering.html");
    exit();
}

require_once __DIR__ . '/connect.php';
try {
    $conn = db();
} catch (Throwable $e) {
    die("Errore DB: " . $e->getMessage());
}

// Gestione logout
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    session_destroy();
    header("Location: entering.html");
    exit();
}
if($_POST['Tipo_sconto'] != '')
$_SESSION['Tipo_sconto']=$_POST['Tipo_sconto'];

/* === DATI UTENTE === */
$sqlUser = "SELECT ID, username, crediti, ruolo, data_registrazione  FROM Utenti WHERE username = ?";
$stmt = $conn->prepare($sqlUser);
$stmt->bind_param("s", $_SESSION['Username']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) die("Utente non trovato.");

$_SESSION['DataIscrizione']= (string)$user['data_registrazione'];
$userId = (int)$user['ID'];
$username = $user['username'];
$crediti = (float)$user['crediti'];
$is_admin = (strtolower($user['ruolo']) === 'amministratore');
$homepage_link = $is_admin ? 'homepage_admin.php' : 'homepage_user.php';


$xmlFile = 'xml/compra.xml'; // percorso del file
$totale_spesa = 0.0;

$doc = new DOMDocument();
$doc->load($xmlFile);

foreach ($doc->getElementsByTagName("ordine") as $ordine) {
    $idUtente = (int)$ordine->getElementsByTagName("ID_Utente")[0]->nodeValue;
    $pagamento = (float)$ordine->getElementsByTagName("pagamento_finale")[0]->nodeValue;

    if ($idUtente === $userId) {
        $totale_spesa += $pagamento;
    }
}

$reputazione = isset($_SESSION['Reputazione']) ? (int)$_SESSION['Reputazione'] : 0;


/* === DATI MAGLIA === */
$tipo = $_REQUEST['tipo'] ?? null;
$stagione = $_REQUEST['stagione'] ?? null;
if (!$tipo || !$stagione) {
    echo "<p style='padding:20px'>Errore: dati mancanti. Torna al <a href='catalogo_maglie.php'>catalogo</a>.</p>";
    exit();
}

/* === CARICAMENTO MAGLIE === */
$xmlMaglie = new DOMDocument();
$xmlMaglie->load("xml/maglie.xml");
$xpath = new DOMXPath($xmlMaglie);
$maglie = [];
foreach ($xpath->query("/maglie/maglia[tipo='$tipo' and stagione='$stagione']") as $node) {
    $maglie[] = [
        'ID' => $node->getElementsByTagName("ID")[0]->nodeValue,
        'tipo' => $tipo,
        'stagione' => $stagione,
        'taglia' => $node->getElementsByTagName("taglia")[0]->nodeValue,
        'Sponsor' => $node->getElementsByTagName("Sponsor")[0]->nodeValue ?? '',
        'descrizione_maglia' => $node->getElementsByTagName("descrizione_maglia")[0]->nodeValue ?? '',
        'costo_fisso' => (float)$node->getElementsByTagName("costo_fisso")[0]->nodeValue,
        'path_immagine' => $node->getElementsByTagName("path_immagine")[0]->nodeValue ?? ''
    ];
}
if (!$maglie) {
    echo "<p style='padding:20px'>Nessuna maglia trovata. Torna al <a href='catalogo_maglie.php'>catalogo</a>.</p>";
    exit();
}
$magliaNode = $xpath->query("/maglie/maglia[tipo='$tipo' and stagione='$stagione']")->item(0);
$sconto_totale = $_POST['Percentuale_sconto'] ?? 0.0;//sconto dal catalogo

$xmlSconti = new DOMDocument();
$xmlSconti->load("xml/sconti.xml");
$xpathSconti = new DOMXPath($xmlSconti);
$xmlCompra = new DOMDocument();
$xmlCompra->load("xml/compra.xml");
$xpathCompra = new DOMXPath($xmlCompra);
$xmlCrediti_Rischiesti = new DOMDocument();
$xmlCrediti_Rischiesti->load("xml/crediti_richieste.xml");
$xpathCrediti_Rischiesti = new DOMXPath($xmlCrediti_Rischiesti);
$xmlCarrello = new DOMDocument();
$xmlCarrello->load("xml/carrelli.xml");
$xpathCarrello = new DOMXPath($xmlCarrello);
$xmlBonus = new DOMDocument();
$xmlBonus->load("xml/bonus.xml");
$xpathBonus = new DOMXPath($xmlBonus);

function confrontaMagliaMultipla(DOMElement $magliaNode, array $stagioniAttese, array $tipiAttesi): bool {
    $stagioneNode = $magliaNode->getElementsByTagName("stagione")->item(0);
    $tipoNode = $magliaNode->getElementsByTagName("tipo")->item(0);

    if (!$stagioneNode || !$tipoNode) {
        return false; // Elementi mancanti
    }

    $stagione = trim($stagioneNode->nodeValue);
    $tipo = trim($tipoNode->nodeValue);

    return in_array($stagione, $stagioniAttese) && in_array($tipo, $tipiAttesi);
}

function isScontoAttivoNelPeriodo(DOMElement $scontoNode): bool {
    $dataInizioNode = $scontoNode->getElementsByTagName("data_inizio")->item(0);
    $dataFineNode   = $scontoNode->getElementsByTagName("data_fine")->item(0);

    $oggi = new DateTime();

    if ($dataInizioNode && $dataFineNode) {
        $inizio = new DateTime($dataInizioNode->nodeValue);
        $fine   = new DateTime($dataFineNode->nodeValue);
        return ($oggi >= $inizio && $oggi <= $fine);
    }

    if ($dataInizioNode && !$dataFineNode) {
        $inizio = new DateTime($dataInizioNode->nodeValue);
        return ($oggi >= $inizio);
    }

    if (!$dataInizioNode && $dataFineNode) {
        $fine = new DateTime($dataFineNode->nodeValue);
        return ($oggi <= $fine);
    }

    // Se entrambe le date mancano, consideriamo lo sconto sempre valido
    return true;
}


function monthsBetween(string $start, string $end): int {
    $d1 = new DateTime($start);
    $d2 = new DateTime($end);
    $diff = $d2->diff($d1);
    return ($diff->y * 12) + $diff->m;
}
// calcolo sconti
function calcolaScontoReputazione(int $reputazione, DOMXPath $xpathSconti): int {
    $risultato = 0;

    // Seleziona tutti gli sconti attivi di tipo REPUTAZIONE
    $scontiNodes = $xpathSconti->query("//sconto[tipo='REPUTAZIONE' and attivo='true']");

    foreach ($scontiNodes as $scontoNode) {
        if (!isScontoAttivoNelPeriodo($scontoNode)) {
            continue;
        }

        // Estrai soglie di reputazione
        $soglieNodes = $scontoNode->getElementsByTagName("soglia");
        $soglie = [];

        foreach ($soglieNodes as $sogliaNode) {
            $maxRepNode = $sogliaNode->getElementsByTagName("maxReputazione")->item(0);
            $percNode   = $sogliaNode->getElementsByTagName("percentualeSconto")->item(0);

            if ($maxRepNode && $percNode) {
                $soglie[] = [
                    'MaxReputazione' => (float)$maxRepNode->nodeValue,
                    'PercentualeSconto' => (float)$percNode->nodeValue
                ];
            }
        }

        // Ordina le soglie per reputazione crescente
        usort($soglie, fn($a, $b) => $a['MaxReputazione'] <=> $b['MaxReputazione']);

        // Trova la prima soglia valida
        foreach ($soglie as $soglia) {
            if ($reputazione <= $soglia['MaxReputazione']) {
                $risultato = $soglia['PercentualeSconto'];
                break;
            }
        }
        // Se abbiamo trovato uno sconto valido, possiamo uscire
        if ($risultato > 0) {
            break;
        }
    }

    return $risultato;
}


function calcolaScontoAnzianita(string $dataIscrizione, DOMXPath $xpathSconti, DOMElement $magliaNode): int {
    $risultato = 0;

    $oggi = date('Y-m-d');
    $scontiNodes = $xpathSconti->query("//sconto[tipo='ANZIANITA' and attivo='true']");

    $scontoSelezionato = null;

    //  Cerca sconti con condizioni maglia compatibili
    foreach ($scontiNodes as $scontoNode) {
        if (!isScontoAttivoNelPeriodo($scontoNode)) continue;

        $condizioniNode = $scontoNode->getElementsByTagName("condizioniMaglia")->item(0);
        if ($condizioniNode) {
            $stagioni = [];
            $tipi = [];

            foreach ($condizioniNode->getElementsByTagName("stagione") as $stagioneNode) {
                $stagioni[] = trim($stagioneNode->nodeValue);
            }

            foreach ($condizioniNode->getElementsByTagName("tipo") as $tipoNode) {
                $tipi[] = trim($tipoNode->nodeValue);
            }

            if (confrontaMagliaMultipla($magliaNode, $stagioni, $tipi)) {
                $scontoSelezionato = $scontoNode;
                break;
            }
        }
    }

    //  Se non trovato, cerca sconto generico (senza condizioni maglia)
        if (!$scontoSelezionato) {
            foreach ($scontiNodes as $scontoNode) {
                if (!isScontoAttivoNelPeriodo($scontoNode)) continue;

                $condizioniNode = $scontoNode->getElementsByTagName("condizioniMaglia")->item(0);
                if (!$condizioniNode) {
                    $scontoSelezionato = $scontoNode;
                    break;
                }
            }
        }

        //  Se nessuno sconto valido trovato, esci
        if (!$scontoSelezionato) return $risultato;

        //  Calcola lo sconto
            $mesiIscrizione = monthsBetween($dataIscrizione, $oggi);

    // Recupera il periodo dinamico dal nodo XML
    $periodoNode = $scontoSelezionato->getElementsByTagName("periodoMensilita")->item(0);
    $periodoMensilita = $periodoNode ? floatval($periodoNode->nodeValue) : 1.0; // default 1 mese se non specificato

    $periodi = floor($mesiIscrizione / $periodoMensilita);

    // Recupera incremento e massimo
    $incrementoNode = $scontoSelezionato->getElementsByTagName("incrementoPercentuale")->item(0);
    $percentualeMaxNode = $scontoSelezionato->getElementsByTagName("percentualeMax")->item(0);

    $incremento = $incrementoNode ? floatval($incrementoNode->nodeValue) : 0.0;
    $percentualeMax = $percentualeMaxNode ? floatval($percentualeMaxNode->nodeValue) : 0.0;

    // Calcola lo sconto
    $percentuale = $incremento * $periodi;
    if ($percentuale > $percentualeMax) {
        $percentuale = $percentualeMax;
    }

    $risultato = $percentuale;

    return $risultato;
}



function calcoloScontoFedelizzazione(DOMXPath $xpathSconti, ?DOMXPath $compraDoc, ?DOMXPath $carrelloDoc, int $idUtente, DOMElement $magliaNode): array {
    $risultato = [
        'percentuale' => 0.0,
        'codice' => ''
    ];
    
    $oggi = new DateTime();
    $scontiNodes = $xpathSconti->query("//sconto[tipo='FEDELISSIMO' and attivo='true']");

    $scontoSelezionato = null;

    //  Cerca sconti con condizioni maglia compatibili
    foreach ($scontiNodes as $scontoNode) {
        if (!isScontoAttivoNelPeriodo($scontoNode)) continue;
        
        $condizioniNode = $scontoNode->getElementsByTagName("condizioniMaglia")->item(0);
        if ($condizioniNode) {
            $stagioni = [];
            $tipi = [];
            
            foreach ($condizioniNode->getElementsByTagName("stagione") as $stagioneNode) {
                $stagioni[] = trim($stagioneNode->nodeValue);
            }

            foreach ($condizioniNode->getElementsByTagName("tipo") as $tipoNode) {
                $tipi[] = trim($tipoNode->nodeValue);
            }

            if (confrontaMagliaMultipla($magliaNode, $stagioni, $tipi)) {
                $scontoSelezionato = $scontoNode;
                break;
            }
        }
    }
    //  Se non trovato, cerca sconto generico
    if (!$scontoSelezionato) {
        foreach ($scontiNodes as $scontoNode) {
            if (!isScontoAttivoNelPeriodo($scontoNode)) continue;
            $condizioniNode = $scontoNode->getElementsByTagName("condizioniMaglia")->item(0);

            if (!$condizioniNode) {
                $scontoSelezionato = $scontoNode;
                break;
            }
        }
    }   

    //  Se nessuno sconto valido trovato, esci
    if (!$scontoSelezionato) return $risultato;

    //  Controlla se l’utente ha già usato lo sconto
    $scontoGiaUtilizzato = false;

    if ($compraDoc && $idUtente != -1) {
        $usati = $compraDoc->query("//ordine[ID_Utente='$idUtente' and sconto_utilizzato='FEDELISSIMO']");
        if ($usati->length > 0) $scontoGiaUtilizzato = true;
    }

    if (!$scontoGiaUtilizzato && $carrelloDoc && $idUtente != -1) {
        $usati = $carrelloDoc->query("//carrello[ID_utente='$idUtente']//sconto_utilizzato[.='FEDELISSIMO']");
        if ($usati->length > 0) $scontoGiaUtilizzato = true;
    }
    echo $scontoGiaUtilizzato;
    if ($scontoGiaUtilizzato) return $risultato;

    // Calcola il totale speso escludendo ordini con FEDELISSIMO
    $totaleSpeso = 0.0;
    if ($compraDoc && $idUtente != -1) {
        $ordini = $compraDoc->query("//ordine[ID_Utente='$idUtente']");
        foreach ($ordini as $ordine) {
                $pagamento = $ordine->getElementsByTagName("pagamento_finale")->item(0);
                if ($pagamento) {
                    $totaleSpeso += floatval($pagamento->nodeValue);
                }
            }
        }

    // Verifica soglia e percentuale
    $sogliaNode = $scontoSelezionato->getElementsByTagName("sogliaCreditiStep")->item(0);
    $percentualeNode = $scontoSelezionato->getElementsByTagName("percentualeFissa")->item(0);

    $soglia = $sogliaNode ? floatval($sogliaNode->nodeValue) : 0.0;
    $percentuale = $percentualeNode ? floatval($percentualeNode->nodeValue) : 0.0;

    if ($totaleSpeso >= $soglia) {
        $risultato['percentuale'] = $percentuale;
        $codiceNode = $scontoSelezionato->getElementsByTagName("patternCodice")->item(0);
        if ($codiceNode) {
            $risultato['codice'] = trim($codiceNode->nodeValue);
        }
    }

    return $risultato;
}


function calcoloScontoBenvenuto(DOMXPath $xpathSconti, DOMXPath $xpathRichieste, DOMXPath $xpathCrediti, DOMXPath $xpathCarrelli, int $idUtente): int {
    $risultato = 0;

    //  Controlla che l’utente abbia una sola richiesta approvata
    $richieste = $xpathRichieste->query("//richiesta[user_id='$idUtente' and stato='Approvata']");
    if ($richieste->length > 1) {
        return $risultato;
    }

    //  Controlla se l’utente ha già usato lo sconto
    $usatoInOrdini = $xpathCrediti->query("//ordine[ID_Utente='$idUtente' and sconto_utilizzato='BENVENUTOLUPETTO']");
    $usatoInCarrello = $xpathCarrelli->query("//carrello[ID_utente='$idUtente' and sconto_utilizzato='BENVENUTOLUPETTO']");

    if ($usatoInOrdini->length > 0 || $usatoInCarrello->length > 0) {
        return $risultato;
    }

    //  Recupera lo sconto benvenuto attivo
    $scontoNode = $xpathSconti->query("//sconto[tipo='BENVENUTOLUPETTO' and attivo='true']")->item(0);
    if (!$scontoNode || !isScontoAttivoNelPeriodo($scontoNode)) {
        return $risultato;
    }

    //  Estrai percentuale massima e codice
    $percentualeMaxNode = $scontoNode->getElementsByTagName("percentualeMax")->item(0);
    $codiceNode = $scontoNode->getElementsByTagName("patternCodice")->item(0);

    $percentualeMax = $percentualeMaxNode ? floatval($percentualeMaxNode->nodeValue) : 0.0;
    $codice = $codiceNode ? trim($codiceNode->nodeValue) : '';

    //  Genera percentuale randomica tra 1 e percentualeMax
    if ($percentualeMax >= 1) {
        $risultato = random_int(1, (int)$percentualeMax);
    }

    return $risultato;
}


function BonusReputazione(int $reputazione, string $dataIscrizione, DOMXPath $bonusDoc, DOMXPath $carrelliDoc, DOMXPath $compraDoc, int $idUtente): int {
    $oggi = new DateTime();
    $dataIscrizioneDT = new DateTime($dataIscrizione);
    $creditiValidi = [];

    // Recupera tutti i bonus di tipo Reputazione
    $bonusNodes = $bonusDoc->query("//premio[tipo='Reputazione']");

    foreach ($bonusNodes as $bonus) {
        // Verifica che sia attivo
        $attivoNode = $bonus->getElementsByTagName("attivo")->item(0);
        if (!$attivoNode || strtolower($attivoNode->nodeValue) !== 'true') continue;

        // Verifica validità temporale
        $inizioNode = $bonus->getElementsByTagName("data_inizio")->item(0);
        if ($inizioNode) {
            $inizio = new DateTime($inizioNode->nodeValue);
            if ($oggi < $inizio) continue;

            // Verifica che l'utente sia registrato prima della data_inizio del bonus
            if ($dataIscrizioneDT > $inizio) continue;
        }

        // Verifica reputazione minima
        $reputazioneMinimaNode = $bonus->getElementsByTagName("reputazioneMinima")->item(0);
        $reputazioneMinima = $reputazioneMinimaNode ? (int)$reputazioneMinimaNode->nodeValue : 0;
        if ($reputazione < $reputazioneMinima) continue;

        // Verifica limite utilizzi
        $limiteNode = $bonus->getElementsByTagName("limite_utilizzi")->item(0);
        $limite = $limiteNode ? (int)$limiteNode->nodeValue : 1;

        $usatiCarrello = $carrelliDoc->query("//carrello[ID_utente='$idUtente' and bonus_utilizzato='Reputazione']")->length;
        $usatiOrdini = $compraDoc->query("//ordine[ID_Utente='$idUtente' and bonus_utilizzato='Reputazione']")->length;
        $totaleUsi = $usatiCarrello + $usatiOrdini;

        if ($totaleUsi >= $limite) continue;

        // Recupera crediti
        $creditiNode = $bonus->getElementsByTagName("crediti")->item(0);
        if ($creditiNode) {
            $creditiValidi[] = (int)trim($creditiNode->nodeValue);
        }
    }

    // Restituisci il valore massimo tra i bonus validi
    return !empty($creditiValidi) ? max($creditiValidi) : 0;

}


function BonusRetro(string $stagione, DOMXPath $bonusDoc): int {
    $annoCorrente = (int)date('Y');
    $annoStagione = (int)substr($stagione, 0, 4);
    
    // Cerca prima un bonus specifico per la stagione
    $bonusSpecifico = $bonusDoc->query("//premio[tipo='MagliaRetro' and attivo='true' and stagione='$stagione']");
    
    if ($bonusSpecifico->length > 0) {
        $creditiNode = $bonusSpecifico->item(0)->getElementsByTagName("crediti")->item(0);
        if ($creditiNode) {
            return (int)trim($creditiNode->nodeValue);
        }
    }
    
    // Se non trova bonus specifico, cerca bonus globale (senza stagione)
    $bonusGlobale = $bonusDoc->query("//premio[tipo='MagliaRetro' and attivo='true' and not(stagione)]");
    
    if ($bonusGlobale->length > 0) {
        // Verifica che la stagione della maglia sia diversa dall'attuale
        if ($annoStagione !== $annoCorrente) {
            $creditiNode = $bonusGlobale->item(0)->getElementsByTagName("crediti")->item(0);
            if ($creditiNode) {
                return (int)trim($creditiNode->nodeValue);
            }
        }
    }
    
    return 0;
}
// Sconto Reputazione
$percReputazione = calcolaScontoReputazione($reputazione, $xpathSconti, $xpathCompra, $xpathCarrello, $userId);

// Sconto Anzianità
$percAnzianita = calcolaScontoAnzianita($_SESSION['DataIscrizione'] ?? '2024-01-01', $xpathSconti, $magliaNode);

// Sconto Fedelizzazione
$scontoFedelizzazione = calcoloScontoFedelizzazione($xpathSconti, $xpathCompra, $xpathCarrello, $userId, $magliaNode);
$percFedelizzazione = $scontoFedelizzazione['percentuale'] ?? 0;
$codiceFedelizzazione = $scontoFedelizzazione['codice'] ?? '';

// Sconto Benvenuto
$percBenvenuto = calcoloScontoBenvenuto($xpathSconti, $xpathCrediti_Rischiesti, $xpathCompra, $xpathCarrello, $userId);

// Bonus Retro
$BonusRetro = BonusRetro($stagione, $xpathBonus);

// Bonus Reputazione
$BonusReputazione = BonusReputazione($reputazione, $_SESSION['DataIscrizione'] ?? '2024-01-01', $xpathBonus, $xpathCarrello, $xpathCompra, $userId);

/* === CARICAMENTO GIOCATORI === */
$xmlGiocatori = new DOMDocument();
$xmlGiocatori->load("xml/giocatori.xml");
$giocatori = [];
foreach ($xmlGiocatori->getElementsByTagName("giocatore") as $g) {
    $giocatori[] = [
        'ID' => $g->getElementsByTagName("ID")[0]->nodeValue,
        'nome' => $g->getElementsByTagName("nome")[0]->nodeValue,
        'cognome' => $g->getElementsByTagName("cognome")[0]->nodeValue,
        'num_maglia' => $g->getElementsByTagName("num_maglia")[0]->nodeValue
    ];
}

$msg = '';
$ok = false;
$azione = $_POST['azione'] ?? '';
// carrello//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $azione === 'carrello') {
    $ID_Maglia = $_POST['maglia_id'] ?? '';
    $taglia = $_POST['taglia'] ?? '';
    if (empty($taglia)) {
        $msg = "Seleziona una taglia prima di continuare.";
    } else {
        $mod_tipo = $_POST['mod_tipo'] ?? 'none';
        $logo_g = $_POST['logo_g'] ?? '';
        $logo_p = $_POST['logo_p'] ?? '';
        $logo = ($mod_tipo === 'giocatore') ? $logo_g : ($mod_tipo === 'personalizzata' ? $logo_p : '');
        $id_giocatore = $_POST['id_giocatore'] ?? '';
        $nome_pers = trim($_POST['nome_pers'] ?? '');
        $num_pers = trim($_POST['num_pers'] ?? '');
        $prezzo_finale = floatval($_POST['prezzo_finale'] ?? 0);
        $sconto_totale = floatval($_POST['sconto_totale'] ?? 0);
        
        // === Sconti individuali ===
        $sconto_fedelissimo = floatval($_POST['sconto_fedelissimo'] ?? 0); 
        $sconto_benvenuto   = floatval($_POST['sconto_benvenuto'] ?? 0);
        $sconto_anzianita   = floatval($_POST['sconto_anzianita'] ?? 0);
        $sconto_reputazione = floatval($_POST['sconto_reputazione'] ?? 0);
        $sconto_retro       = ($_SESSION['Tipo_sconto'] === 'RETRO') ? 1 : 0;
        $sconto_taglia      = ($_SESSION['Tipo_sconto'] === 'TIPO_MAGLIA') ? 1 : 0;
        $sconti = [
            'FEDELISSIMO'      => $sconto_fedelissimo,
            'BENVENUTOLUPETTO' => $sconto_benvenuto,
            'REPUTAZIONE'      => $sconto_reputazione,
            'ANZIANITA'        => $sconto_anzianita,
            'TIPO_MAGLIA'      => $sconto_taglia,
            'RETRO'            => $sconto_retro
        ];

        // === Trova la maglia selezionata ===
        $magliaScelta = null;
        foreach ($maglie as $m) {
            if ($m['ID'] === $ID_Maglia && $m['taglia'] === $taglia) {
                $magliaScelta = $m;
                break;
            }
        }

        if (!$magliaScelta) {
            $msg = "Errore: maglia non trovata.";
        } else {
            // === Calcolo supplemento personalizzazione ===
            $supplemento = 0;
            if ($mod_tipo === 'giocatore') {
                $supplemento = (!empty($logo)) ? 15 : 10;
            } elseif ($mod_tipo === 'personalizzata') {
                $supplemento = (!empty($logo)) ? 20 : 15;
            }
            $prezzo_base = (float)$magliaScelta['costo_fisso'];
            
            // === Calcolo prezzo netto ===
            $prezzo_netto = $prezzo_finale > 0 ? $prezzo_finale : ($prezzo_base * (1 - $sconto_totale) + $supplemento);

            // Percentuale totale di sconto
            $scontoPercentualeTotale = 0;
            if ($prezzo_base > 0) {
                $scontoPercentualeTotale = (($prezzo_base - ($prezzo_netto - $supplemento)) / $prezzo_base) * 100;
            }

            $bonus = $BonusRetro + $BonusReputazione;
            $oggi = date('c');

            /* === Salvataggio nel carrello XML === */
            $carrelli_path = "xml/carrelli.xml";
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            if (file_exists($carrelli_path)) $dom->load($carrelli_path);
            else $dom->appendChild($dom->createElement("carrelli"));
            $root = $dom->getElementsByTagName("carrelli")->item(0);

            // Trova o crea carrello utente
            $carrelloNode = null;
            foreach ($root->getElementsByTagName("carrello") as $c) {
                if ($c->getElementsByTagName("ID_utente")->item(0)->nodeValue == $userId) {
                    $carrelloNode = $c;
                    break;
                }
            }

            if (!$carrelloNode) {
                $carrelloNode = $dom->createElement("carrello");
                $carrelloNode->appendChild($dom->createElement("ID_utente", $userId));
                $carrelloNode->appendChild($dom->createElement("data_carrello", $oggi));
                $articoliNode = $dom->createElement("articoli");
                $carrelloNode->appendChild($articoliNode);
                $root->appendChild($carrelloNode);
            } else {
                $carrelloNode->getElementsByTagName("data_carrello")->item(0)->nodeValue = $oggi;
                $articoliNode = $carrelloNode->getElementsByTagName("articoli")->item(0);
                if (!$articoliNode) $articoliNode = $carrelloNode->appendChild($dom->createElement("articoli"));
            }

            /* === Crea nuovo articolo === */
            $newId = $articoliNode->getElementsByTagName("articolo")->length + 1;
            $articolo = $dom->createElement("articolo");
            $articolo->appendChild($dom->createElement("ID_carrello", $newId));

            $tipoMaglia = ($mod_tipo === 'giocatore') ? "Giocatore" :
                          (($mod_tipo === 'personalizzata') ? "Personalizzata" : "Standard");
            $articolo->appendChild($dom->createElement("tipo_maglia", $tipoMaglia));
            $articolo->appendChild($dom->createElement("ID_maglia", $ID_Maglia));
            $articolo->appendChild($dom->createElement("prezzo_unitario", number_format($prezzo_base, 2, '.', '')));
            $articolo->appendChild($dom->createElement("percentuale_sconto", number_format($scontoPercentualeTotale, 2, '.', '')));
            $articolo->appendChild($dom->createElement("prezzo_netto_riga", number_format($prezzo_netto, 2, '.', '')));
            $articolo->appendChild($dom->createElement("supplemento", number_format($supplemento, 2, '.', '')));
            $articolo->appendChild($dom->createElement("bonus_previsto", number_format($bonus, 2, '.', '')));

            if($BonusReputazione > 0){
            $bonusNode = $dom->createElement("BonusUtilizzato", "Reputazione");
            $articolo->appendChild($bonusNode);
            }

            if($BonusRetro > 0){
                $bonusNode = $dom->createElement("BonusUtilizzato", "MagliaRetro");
                $articolo->appendChild($bonusNode);
            }
            
            // === Sconti utilizzati (solo quelli con valore > 0, max 2) ===
            foreach ($sconti as $tipo => $valore) {
                if ($valore != 0 ) {
                    $articolo->appendChild($dom->createElement("sconto_utilizzato", $tipo));
                    
                }
            }
            // === Campi aggiuntivi === //
            $articolo->appendChild($dom->createElement("taglia", $taglia));
            $articolo->appendChild($dom->createElement("supplemento_personalizzazione", number_format($supplemento, 2, '.', '')));
            if ($logo) $articolo->appendChild($dom->createElement("Logo", $logo));

            if ($mod_tipo === 'giocatore') {
                $nomeG = ''; $cognomeG = '';
                foreach ($giocatori as $g) {
                    if ($g['ID'] == $id_giocatore) {
                        $nomeG = $g['nome'];
                        $cognomeG = $g['cognome'];
                        break;
                    }
                }
                $articolo->appendChild($dom->createElement("ID_giocatore", $id_giocatore));
                $articolo->appendChild($dom->createElement("nome_giocatore", "$cognomeG $nomeG"));
            } elseif ($mod_tipo === 'personalizzata') {
                $articolo->appendChild($dom->createElement("nome_pers", strtoupper($nome_pers)));
                $articolo->appendChild($dom->createElement("num_pers", $num_pers));
            }

            $articoliNode->appendChild($articolo);
            $dom->save($carrelli_path);

            echo "<script>
                    alert('Articolo aggiunto al carrello con successo! Prezzo: " . number_format($prezzo_netto, 2, ',', '.') . " €');
                    window.location.href = 'carrello.php';
                  </script>";
            exit();
        }
    }
}

/* ===============================================================
   💳 ACQUISTO IMMEDIATO
   =============================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $azione === 'acquista') {
    $ID_Maglia = $_POST['maglia_id'] ?? '';
    $taglia = $_POST['taglia'] ?? '';

    if (empty($taglia)) {
        $msg = "Seleziona una taglia prima di continuare.";
    } else {
        $indirizzo = trim($_POST['indirizzo'] ?? '');
        $mod_tipo = $_POST['mod_tipo'] ?? 'none';
        $logo_g = $_POST['logo_g'] ?? '';
        $logo_p = $_POST['logo_p'] ?? '';
        $logo = ($mod_tipo === 'giocatore') ? $logo_g : ($mod_tipo === 'personalizzata' ? $logo_p : '');
        $id_giocatore = $_POST['id_giocatore'] ?? '';
        $nome_p = trim($_POST['nome_pers'] ?? '');
        $num_p = trim($_POST['num_pers'] ?? '');

        // Prezzi e sconti
        $prezzo_finale = floatval($_POST['prezzo_finale'] ?? 0);
        $sconto_fedelissimo = floatval($_POST['sconto_fedelissimo'] ?? 0);
        $sconto_benvenuto   = floatval($_POST['sconto_benvenuto'] ?? 0);
        $sconto_anzianita   = floatval($_POST['sconto_anzianita'] ?? 0);
        $sconto_reputazione = floatval($_POST['sconto_reputazione'] ?? 0);
        $sconto_retro       = ($_SESSION['Tipo_sconto'] === 'RETRO') ? 1 : 0;
        $sconto_taglia      = ($_SESSION['Tipo_sconto'] === 'TIPO_MAGLIA') ? 1 : 0;

        // Calcolo totale sconti
        $sconti = [
            'FEDELISSIMO'      => $sconto_fedelissimo,
            'BENVENUTOLUPETTO' => $sconto_benvenuto,
            'REPUTAZIONE'      => $sconto_reputazione,
            'ANZIANITA'        => $sconto_anzianita,
            'TIPO_MAGLIA'      => $sconto_taglia,
            'RETRO'            => $sconto_retro
        ];
        $sconto_totale = array_sum($sconti);

        // Trova la maglia scelta
        $magliaScelta = null;
        foreach ($maglie as $m) {
            if ($m['ID'] === $ID_Maglia && $m['taglia'] === $taglia) {
                $magliaScelta = $m;
                break;
            }
        }

        if (!$magliaScelta) {
            $msg = "Maglia non valida.";
        } elseif ($indirizzo === '') {
            $msg = "Inserisci l'indirizzo di consegna.";
        } else {
            // Supplemento personalizzazione
            $supplemento = 0;
            if ($mod_tipo === 'giocatore') {
                $supplemento = (!empty($logo)) ? 15 : 10;
            } elseif ($mod_tipo === 'personalizzata') {
                $supplemento = (!empty($logo)) ? 20 : 15;
            }

            $prezzo_base = (float)$magliaScelta['costo_fisso'];
            $prezzo_netto = $prezzo_finale > 0 ? $prezzo_finale : ($prezzo_base * (1 - $sconto_totale) + $supplemento);

            // Calcola percentuale sconto
            $scontoPercentualeTotale = 0;
            if ($prezzo_base > 0) {
                $scontoPercentualeTotale = (($prezzo_base - ($prezzo_netto - $supplemento)) / $prezzo_base) * 100;
            }

            if ($crediti < $prezzo_netto) {
                $msg = "Crediti insufficienti. Disponibili: " . number_format($crediti, 2, ',', '.') . " € - Richiesti: " . number_format($prezzo_netto, 2, ',', '.') . " €";
            } else {
                $oggi = date('Y-m-d');

                // === CREA / AGGIORNA XML COMPRA ===
                $xmlCompra = new DOMDocument();
                $xmlCompra->preserveWhiteSpace = false;
                $xmlCompra->formatOutput = true;

                $pathCompra = "xml/compra.xml";
                if (!file_exists($pathCompra) || filesize($pathCompra) === 0) {
                    $root = $xmlCompra->createElement("ordini");
                    $xmlCompra->appendChild($root);
                } else {
                    $xmlCompra->load($pathCompra);
                    $root = $xmlCompra->documentElement;
                    if (!$root) {
                        $root = $xmlCompra->createElement("ordini");
                        $xmlCompra->appendChild($root);
                    }
                }

                $newId = $xmlCompra->getElementsByTagName("ordine")->length + 1;
                $n = $xmlCompra->createElement("ordine");
                $n->appendChild($xmlCompra->createElement("ID", $newId));
                $n->appendChild($xmlCompra->createElement("ID_Utente", $userId));
                $n->appendChild($xmlCompra->createElement("ID_Maglia", $ID_Maglia));
                $n->appendChild($xmlCompra->createElement("pagamento_finale", $prezzo_netto));

                // Usa createTextNode per sicurezza
                $indirizzoNode = $xmlCompra->createElement("indirizzo_consegna");
                $indirizzoNode->appendChild($xmlCompra->createTextNode($indirizzo));
                $n->appendChild($indirizzoNode);

                $n->appendChild($xmlCompra->createElement("data_compra", $oggi));
                $n->appendChild($xmlCompra->createElement("percentualeSconto", $scontoPercentualeTotale));

                // Aggiungi sconti usati
                foreach ($sconti as $nome => $valore) {
                    if ($valore > 0) {
                        $scontoNode = $xmlCompra->createElement("sconto_utilizzato", $nome);
                        $n->appendChild($scontoNode);
                    }
                }

                $root->appendChild($n);
                $xmlCompra->save($pathCompra);

                // === PERSONALIZZAZIONI XML ===
                if ($mod_tipo === 'giocatore') {
                    $xmlGiocatore = new DOMDocument();
                    $xmlGiocatore->preserveWhiteSpace = false;
                    $xmlGiocatore->formatOutput = true;
                    $pathGiocatore = "xml/maglie_giocatore.xml";

                    // Crea o carica il file XML
                    if (!file_exists($pathGiocatore) || filesize($pathGiocatore) === 0) {
                        $rootG = $xmlGiocatore->createElement("maglie_giocatore");
                        $xmlGiocatore->appendChild($rootG);
                    } else {
                        $xmlGiocatore->load($pathGiocatore);
                        $rootG = $xmlGiocatore->documentElement;
                        if (!$rootG) {
                            $rootG = $xmlGiocatore->createElement("maglie_giocatore");
                            $xmlGiocatore->appendChild($rootG);
                        }
                    }

                    // Calcola nuovo ID univoco
                    $newIdG = $xmlGiocatore->getElementsByTagName("personalizzazione")->length + 1;

                    // Calcola il supplemento corretto
                    $supplemento = (!empty($logo)) ? 15 : 10;  // coerente con la logica precedente

                    // Crea nodo personalizzazione
                    $nG = $xmlGiocatore->createElement("personalizzazione");

                    $nG->appendChild($xmlGiocatore->createElement("ID", $newIdG));
                    $nG->appendChild($xmlGiocatore->createElement("Supplemento", $supplemento));

                    if (!empty($logo)) {
                        // Valore ammesso: SERIE A, CHAMPIONS LEAGUE, EUROPA LEAGUE, COPPA ITALIA, CONFERENCE LEAGUE
                        $nG->appendChild($xmlGiocatore->createElement("Logo", strtoupper($logo)));
                    }

                    $nG->appendChild($xmlGiocatore->createElement("ID_Giocatore", $id_giocatore));
                    $nG->appendChild($xmlGiocatore->createElement("ID_Maglia", $ID_Maglia));

                    // Aggiungi al documento e salva
                    $rootG->appendChild($nG);
                    $xmlGiocatore->save($pathGiocatore);
                } elseif ($mod_tipo === 'personalizzata') {
                    $xmlPers = new DOMDocument();
                    $xmlPers->preserveWhiteSpace = false;
                    $xmlPers->formatOutput = true;
                    $pathPers = "xml/maglie_personalizzate.xml";

                    // Crea o carica il file XML
                    if (!file_exists($pathPers) || filesize($pathPers) === 0) {
                        $rootP = $xmlPers->createElement("maglie_personalizzate");
                        $xmlPers->appendChild($rootP);
                    } else {
                        $xmlPers->load($pathPers);
                        $rootP = $xmlPers->documentElement;
                        if (!$rootP) {
                            $rootP = $xmlPers->createElement("maglie_personalizzate");
                            $xmlPers->appendChild($rootP);
                        }
                    }

                    // Calcola nuovo ID univoco
                    $newIdP = $xmlPers->getElementsByTagName("maglia")->length + 1;

                    // Calcola il supplemento corretto
                    $supplemento = (!empty($logo)) ? 20 : 15;

                    // Crea nodo maglia
                    $nP = $xmlPers->createElement("maglia");
                    $nP->appendChild($xmlPers->createElement("ID", $newIdP));
                    $nP->appendChild($xmlPers->createElement("ID_Maglia", $ID_Maglia));

                    if (!empty($logo)) {
                        // Solo se il logo è valorizzato
                        $nP->appendChild($xmlPers->createElement("Logo", strtoupper($logo)));
                    }

                    $nP->appendChild($xmlPers->createElement("supplemento", $supplemento));
                    $nP->appendChild($xmlPers->createElement("nome", htmlspecialchars($nome_p, ENT_XML1 | ENT_COMPAT, 'UTF-8')));
                    $nP->appendChild($xmlPers->createElement("num_maglia", intval($num_p)));

                    // Aggiungi e salva
                    $rootP->appendChild($nP);
                    $xmlPers->save($pathPers);
                }

                // === CASHBACK / CREDITI ===
                $BonusReputazione = $BonusReputazione ?? 0;
                $BonusRetro = $BonusRetro ?? 0;
                $cashback = $BonusReputazione + $BonusRetro;

                $upd = $conn->prepare("UPDATE Utenti SET crediti = crediti - ? WHERE ID=?");
                $upd->bind_param("di", $prezzo_netto, $userId);
                $upd->execute();
                $upd->close();

                $upd = $conn->prepare("UPDATE Utenti SET crediti = crediti + ? WHERE ID=?");
                $upd->bind_param("di", $cashback, $userId);
                $upd->execute();
                $upd->close();

                echo "<script>
                        alert('Acquisto completato! Totale: " . number_format($prezzo_netto, 2, ',', '.') . " € (Sconto applicato: " . number_format($scontoPercentualeTotale, 1, ',', '.') . "%)');
                        window.location.href = 'storico_acquisti_utente.php';
                      </script>";
                exit();
            }
        }
    }
}

$prima = $maglie[0];
$descrizione = $prima['descrizione_maglia'];
$sponsor = $prima['Sponsor'];
$img_default = $prima['path_immagine'];
$base_default = (float)$prima['costo_fisso'];
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars(ucfirst($tipo)." • ".$stagione) ?> | Acquisto</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="styles/style_compra_maglia.css" />
</head>
<body>

<header>
  <a href="<?= htmlspecialchars($homepage_link) ?>" class="header-link">
    <div class="logo-container"><img src="img/AS_Roma_Logo_2017.svg.png" class="logo" alt="Logo AS Roma"></div>
  </a>
  <h1><a href="<?= htmlspecialchars($homepage_link) ?>" style="color:inherit;text-decoration:none;">PLAYERBASE</a></h1>
  <div class="utente-container">
    <div class="logout"><a href="?logout=true">Logout</a></div>
  </div>
</header>

<div class="page">
  <h2 class="title"><?= htmlspecialchars(ucfirst($tipo)." • ".$stagione) ?></h2>

  <?php if ($msg): ?>
    <div class="alert <?= $ok ? 'alert-ok':'alert-err' ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="post" id="acquistoForm">
    <input type="hidden" name="azione" id="azione" value="">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
    <input type="hidden" name="stagione" value="<?= htmlspecialchars($stagione) ?>">
    <input type="hidden" name="maglia_id" id="maglia_id" value="<?= (int)$maglie[0]['ID'] ?>">
    <input type="hidden" name="base_price" id="base_price" value="<?= (float)$maglie[0]['costo_fisso'] ?>">
    <input type="hidden" name="prezzo_finale" id="prezzo_finale" value="">
    <input type="hidden" name="sconto_fedelissimo" id="sconto_fedelissimo" value="">
    <input type="hidden" name="sconto_benvenuto" id="sconto_benvenuto" value="">
    <input type="hidden" name="sconto_anzianita" id="sconto_anzianita" value="">
    <input type="hidden" name="sconto_reputazione" id="sconto_reputazione" value="">


    <div class="grid">
      <!-- IMMAGINE -->
      <div class="figure">
        <?php $img = $maglie[0]['path_immagine'] ?: 'img/placeholder.png'; ?>
        <img id="imgMaglia" src="<?= htmlspecialchars($img) ?>" alt="Maglia selezionata">
      </div>

      <!-- DETTAGLI -->
      <div class="details">
        <p class="lead"><?= htmlspecialchars($maglie[0]['descrizione_maglia']) ?></p>
        <?php if ($maglie[0]['Sponsor']): ?>
          <p class="meta"><strong>Sponsor:</strong> <?= htmlspecialchars($maglie[0]['Sponsor']) ?></p>
        <?php endif; ?>
        <div class="card" style="margin-bottom:15px;">
        <h3 style="margin-top:0;">I tuoi sconti attivi</h3>
        <ul style="list-style:none; padding:0; margin:0;" id="listaSconti">
            <?php if ($BonusRetro != 0): ?>
            <li class="bonus-item">
            <div class="bonus-box">
                <span>questo articolo ti garantisce un <strong>bonus di <?= number_format($BonusRetro, 0, ',', '.') ?> crediti!</strong></span>
            </div>
            </li>
            <?php endif; ?>
            <?php if ($BonusReputazione != 0): ?>
            <li class="bonus-item">
            <div class="bonus-box">
                <span>questo articolo ti garantisce un <?= number_format($BonusReputazione, 0, ',', '.') ?> crediti!</strong></span>
            </div>
            </li>
            <?php endif; ?>

            <?php if($percReputazione > 0): ?>
            <li class="sconto-item">
                <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;"> 
                <strong>Sconto Reputazione:</strong>
                <?= number_format($percReputazione,2,',','.') ?>%
                <input type="checkbox" id="scontoReputazione" class="sconto-toggle" data-percentuale="<?= $percReputazione ?>">
                </label>
            </li>
            <?php endif; ?>

            <?php if($percAnzianita > 0): ?>
            <li class="sconto-item">
                <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;"> 
                <strong>Sconto Anzianità:</strong>
                <?= number_format($percAnzianita,2,',','.') ?>%
                <input type="checkbox" id="scontoAnzianita" class="sconto-toggle" data-percentuale="<?= $percAnzianita ?>">
                </label>
            </li>
            <?php endif; ?>

            <?php if($percFedelizzazione > 0): ?>
            <li class="sconto-item">
                <!-- PER SCONTI NON CHECKABILI, USA SOLO SPAN -->
                <span>
                <strong>Sconto Fedelizzazione:</strong>
                <?= $codiceFedelizzazione ?><?= $userId ?>" <?= number_format($percFedelizzazione,2,',','.') ?>%
                </span>
            </li>
            <?php endif; ?>

            <?php if($percBenvenuto > 0): ?>
            <li class="sconto-item">
                <span>
                <strong>Sconto Benvenuto:</strong>
                "BENVENUTOLUPETTO<?= $percBenvenuto ?>" <?= number_format($percBenvenuto,2,',','.') ?>%
                </span>
            </li>
            <?php endif; ?>

            <?php if ($_POST['Tipo_sconto'] == 'TIPO_MAGLIA' && $_POST['Percentuale_sconto'] > 0): ?>
            <li class="sconto-item">
                <span>
                <strong>Sconto Tipo Maglia:</strong>
                <?= number_format($_POST['Percentuale_sconto']*100,2,',','.') ?>%
                </span>
            </li>
            <?php endif; ?>

            <?php if ($_POST['Tipo_sconto'] == 'RETRO' && $_POST['Percentuale_sconto'] > 0): ?>
            <li class="sconto-item">
                <span>
                <strong>Sconto Retro:</strong>
                <?= number_format($_POST['Percentuale_sconto']*100,2,',','.') ?>%
                </span>
            </li>
            <?php endif; ?>
        </ul>

        <?php if (($percReputazione + $percAnzianita + $percFedelizzazione + $percBenvenuto) === 0): ?>
            <p style="color:#666;margin-top:8px;">Nessuno sconto attivo al momento.</p>
        <?php endif; ?>
        </div>
        <!-- CODICE SCONTO -->
        <div class="card" style="margin-bottom:15px;">
          <label for="codice_sconto"><strong>Codice Sconto (facoltativo)</strong></label>
          <input type="text" name="codice_sconto" id="codice_sconto" placeholder="Inserisci codice promo..." style="width:220px;">
          <p class="helper" id="scontoTxt" style="margin-top:6px;color:#444;">Sconto applicato: <strong>0%</strong></p>
        </div>

        <div class="row">
          <div class="price">
            <strong>Prezzo:</strong> 
            <div style="font-size:1.4em; font-weight:bold;">
                <span id="basePriceTxt"><?= number_format($base_default*(1-$sconto_totale),2,',','.') ?></span>
            </div>
            </div>
          <div style="margin-left:auto; min-width:220px">
            <label for="taglia"><strong>Taglia</strong></label>
            <select name="taglia" id="taglia" required>
              <option value="">Seleziona taglia</option>
              <?php foreach ($maglie as $v): ?>
                <option 
                  value="<?= htmlspecialchars($v['taglia']) ?>"
                  data-id="<?= (int)$v['ID'] ?>"
                  data-price="<?= (float)$v['costo_fisso'] ?>"
                  data-img="<?= htmlspecialchars($v['path_immagine']) ?>"
                ><?= htmlspecialchars($v['taglia']) ?> — € <?= number_format($v['costo_fisso'],2,',','.') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- PERSONALIZZAZIONE -->
        <div class="card">
          <div class="opt-row">
            <span class="badge">Personalizzazione</span>
            <label><input type="radio" name="mod_tipo" value="none" checked> Nessuna</label>
            <label><input type="radio" name="mod_tipo" value="giocatore"> Maglia giocatore</label>
            <label><input type="radio" name="mod_tipo" value="personalizzata"> Personalizzata</label>
          </div>

          <!-- GIOCATORE -->
          <div id="box_giocatore" style="display:none; margin-top:8px;">
            <div class="opt-row">
              <div style="flex:1">
                <label for="id_giocatore"><strong>Giocatore</strong></label>
                <select name="id_giocatore" id="id_giocatore">
                  <option value="">Seleziona giocatore</option>
                  <?php foreach ($giocatori as $g): ?>
                    <option value="<?= (int)$g['ID'] ?>">
                      <?= htmlspecialchars($g['cognome']." ".$g['nome']." #".$g['num_maglia']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div style="flex:1">
                <label for="logo_g"><strong>Logo (opzionale)</strong></label>
                <select name="logo_g" id="logo_g">
                  <option value="">Nessuno</option>
                  <option>SERIE A</option>
                  <option>CHAMPIONS LEAGUE</option>
                  <option>EUROPA LEAGUE</option>
                  <option>COPPA ITALIA</option>
                  <option>CONFERENCE LEAGUE</option>
                </select>
              </div>
            </div>
            <p class="helper">Supplemento: <strong>+10€</strong> (solo giocatore) • <strong>+15€</strong> (con logo).</p>
          </div>

          <!-- PERSONALIZZATA -->
          <div id="box_pers" style="display:none; margin-top:8px;">
            <div class="opt-row">
              <div style="flex:1">
                <label for="nome_pers"><strong>Nome</strong></label>
                <input type="text" name="nome_pers" id="nome_pers" maxlength="50" placeholder="Es. ROSSI">
              </div>
              <div style="flex:1">
                <label for="num_pers"><strong>Numero</strong></label>
                <input type="number" name="num_pers" id="num_pers" min="1" max="99" placeholder="1-99">
              </div>
            </div>
            <div class="opt-row">
              <div style="flex:1">
                <label for="logo_p"><strong>Logo (opzionale)</strong></label>
                <select name="logo_p" id="logo_p">
                  <option value="">Nessuno</option>
                  <option>SERIE A</option>
                  <option>CHAMPIONS LEAGUE</option>
                  <option>EUROPA LEAGUE</option>
                  <option>COPPA ITALIA</option>
                  <option>CONFERENCE LEAGUE</option>
                </select>
              </div>
            </div>
            <p class="helper">Supplemento: <strong>+15€</strong> (nome+numero) • <strong>+20€</strong> (con logo).</p>
          </div>
        </div>

        <!-- TOTALE -->
        <div class="total">
          <div class="line">
            <span>Totale</span>
            <span id="totaleTxt"><?= number_format($maglie[0]['costo_fisso']*(1-$sconto_totale),2,',','.') ?> €</span>
          </div>
          <input class="addr" type="text" name="indirizzo" id="indirizzo" placeholder="Indirizzo di consegna">
          <p class="note">Crediti disponibili: <strong><?= number_format($crediti,2,',','.') ?> €</strong></p>
          <br>
          <div style="display:flex;gap:10px;flex-wrap: wrap; justify-content: flex-end;">
            <button type="button" class="btn" id="btnCarrello"> Aggiungi al carrello</button>
            <button type="button" class="btn" id="btnAcquista"> Acquista ora</button>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<footer>
  <p>&copy; 2025 Playerbase. Tutti i diritti riservati.</p>
  <a class="link_footer" href="contatti.php">Contatti, policy, privacy</a>
</footer>

<!-- ===== JAVASCRIPT ===== -->
<script>
const SCONTO_TOTALE = <?= (float)$sconto_totale ?>;
let currentDiscount = SCONTO_TOTALE;
let scontiSelezionati = new Set(); // Per tracciare quali sconti sono attivi

// === Mostra/Nascondi box personalizzazione ===
function modTipo() {
  const val = document.querySelector('input[name="mod_tipo"]:checked').value;
  document.getElementById('box_giocatore').style.display = val === 'giocatore' ? 'block' : 'none';
  document.getElementById('box_pers').style.display = val === 'personalizzata' ? 'block' : 'none';
  recalc();
}
document.querySelectorAll('input[name="mod_tipo"]').forEach(r => r.addEventListener('change', modTipo));

// === Calcola supplemento ===
function supplemento() {
  const tipo = document.querySelector('input[name="mod_tipo"]:checked').value;
  if (tipo === 'giocatore') {
    const g = document.getElementById('id_giocatore').value;
    const logo = document.getElementById('logo_g').value;
    if (!g) return 0;
    return logo ? 15 : 10;
  }
  if (tipo === 'personalizzata') {
    const nome = document.getElementById('nome_pers').value.trim();
    const num = document.getElementById('num_pers').value.trim();
    const logo = document.getElementById('logo_p').value;
    if (!nome || !num) return 0;
    return logo ? 20 : 15;
  }
  return 0;
}

// === GESTIONE LIMITE MASSIMO 2 SCONTI ===
function gestisciLimiteSconti(checkboxCliccato) {
    const checkboxesAttivi = document.querySelectorAll('.sconto-toggle:checked');
    const codiceSconto = document.getElementById('codice_sconto').value.trim();
    const codiceFEDELISSIMO = <?= (float)$percFedelizzazione ?>;
    const codiceBENVENUTO = <?= (float)$percBenvenuto ?>;
    let scontoCodiceAttivo = 0;
    if((codiceSconto === `FEDELISSIMO<?=(int)$userId ?>22`.toUpperCase() && <?= (float)$percFedelizzazione ?> > 0) ||(
        codiceSconto === `BENVENUTOLUPETTO<?= (float)$percBenvenuto ?>`.toUpperCase() && <?=(float)$percBenvenuto ?> > 0))
        scontoCodiceAttivo = 1; 
    
    
    // Conta gli sconti attivi totali (checkbox + codice sconto + sconto dal POST se presente)
    const scontiDalPost = SCONTO_TOTALE > 0 ? 1 : 0;
    const scontiTotaliAttivi = checkboxesAttivi.length + scontoCodiceAttivo + scontiDalPost;
    
    // Se si supera il limite di 2 sconti
    if (scontiTotaliAttivi > 2) {
        // Disabilita tutte le checkbox non selezionate
        document.querySelectorAll('.sconto-toggle:not(:checked)').forEach(cb => {
            cb.disabled = true;
        });
        
        // Mostra avviso
        mostraAvvisoLimiteSconti();
        
        // Se l'utente sta cercando di selezionare una nuova checkbox, impediscilo
        if (checkboxCliccato && checkboxCliccato.checked) {
            checkboxCliccato.checked = false;
            return false;
        }
    } else {
        // Riabilita tutte le checkbox
        document.querySelectorAll('.sconto-toggle').forEach(cb => {
            cb.disabled = false;
        });
        
        // Nascondi avviso
        nascondiAvvisoLimiteSconti();
    }
    
    return true;
}

function mostraAvvisoLimiteSconti() {

    let avviso = document.getElementById('avviso-limite-sconti');
    if (!avviso) {
        avviso = document.createElement('div');
        avviso.id = 'avviso-limite-sconti';
        avviso.style.cssText = 'background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; border-radius: 4px; margin: 10px 0; font-size: 14px;';
        avviso.innerHTML = ' <strong>Limite sconti raggiunto</strong> - Puoi applicare massimo 2 sconti contemporaneamente.';
        
        const listaSconti = document.getElementById('listaSconti');
        if (listaSconti) {
            listaSconti.parentNode.insertBefore(avviso, listaSconti.nextSibling);
        }
    }
}

function nascondiAvvisoLimiteSconti() {
    const avviso = document.getElementById('avviso-limite-sconti');
    if (avviso) {
        avviso.remove();
    }
}

// === CALCOLO SCONTI ATTIVI E PREZZO NETTO ===
function calcolaScontoTotale() {
    let scontoPercentualeTotale = 0;
    const scontiAttivi = [];
    
    // 1. Sconto dal POST PHP (se presente) - SEMPRE ATTIVO
    if (SCONTO_TOTALE > 0) {
        const percentualePost = SCONTO_TOTALE * 100;
        scontoPercentualeTotale += percentualePost;
        scontiAttivi.push({
            tipo: 'sconto_post',
            percentuale: percentualePost
        });
    }
    
    // 2. Sconti dalle checkbox selezionate
    document.querySelectorAll('.sconto-toggle:checked').forEach(checkbox => {
        const percentuale = parseFloat(checkbox.dataset.percentuale) || 0;
        scontoPercentualeTotale += percentuale;
        if(checkbox.id === 'scontoReputazione')
        {
            document.getElementById('sconto_reputazione').value = percentuale;
        }else if(checkbox.id === 'scontoAnzianita')
        {
            document.getElementById('sconto_anzianita').value = percentuale;
        }
        scontiAttivi.push({
            tipo: checkbox.id,
            percentuale: percentuale
        });
    });
    
    // 3. Sconto codice (se inserito e se non superiamo il limite)
    const codiceSconto = document.getElementById('codice_sconto').value.trim();
    const scontiAttuali = scontiAttivi.length; // Già incluso sconto POST
    const codiceFEDELISSIMO = <?= (float)$percFedelizzazione ?>;
    const codiceBENVENUTO = <?= (float)$percBenvenuto ?>;

    if ( codiceSconto === `FEDELISSIMO<?= (int)$userId ?>22`.toUpperCase() && (scontiAttuali + 1) <= 2 && <?= (float)$percFedelizzazione ?> > 0) {
        scontoPercentualeTotale += codiceFEDELISSIMO; // 10% per codice sconto
        document.getElementById('sconto_fedelissimo').value = codiceFEDELISSIMO;
        
        scontiAttivi.push({
            tipo: 'codice_fedelissimo',
            percentuale: codiceFEDELISSIMO
        });
    }else if ( codiceSconto === `BENVENUTOLUPETTO<?= (float)$percBenvenuto ?>`.toUpperCase() && (scontiAttuali + 1) <= 2 && <?= (float)$percBenvenuto ?> > 0) {
        scontoPercentualeTotale += codiceBENVENUTO; 
        document.getElementById('sconto_benvenuto').value = codiceBENVENUTO;
        scontiAttivi.push({
            tipo: 'codice_benvenuto',
            percentuale: codiceBENVENUTO
        });
    }
    
    return {
        percentualeTotale: scontoPercentualeTotale,
        scontiAttivi: scontiAttivi
    };
}

// === FUNZIONE PRINCIPALE DI RICALCOLO ===
function recalc() {

    const tagliaSelezionata = document.getElementById('taglia').value;
    if (!tagliaSelezionata) {
        // Nascondi tutto e resetta se non c'è taglia
        nascondiTuttiIPrezzi();
        resetContatoreSconti();
        return;
    }

    const base = parseFloat(document.getElementById('base_price').value || '0');
    const extra = supplemento();
    
    // Calcola tutti gli sconti
    const { percentualeTotale, scontiAttivi } = calcolaScontoTotale();
    
    // Calcola il prezzo scontato
    const scontoDecimale = percentualeTotale / 100;
    let prezzoScontato = base * (1 - scontoDecimale);
    
    // Aggiungi i supplementi di personalizzazione
    let netto = prezzoScontato + extra;
    
    // Aggiorna la visualizzazione
    aggiornaDisplayPrezzi(base, prezzoScontato, netto, percentualeTotale, scontiAttivi.length);
    
    // Aggiorna il contatore sconti
    aggiornaContatoreSconti(scontiAttivi.length);

    document.getElementById('prezzo_finale').value = netto.toFixed(2);
    
    console.log('Calcolo prezzi:', {
        base: base,
        scontoPercentuale: percentualeTotale + '%',
        prezzoDopoSconto: prezzoScontato,
        supplemento: extra,
        totaleNetto: netto,
        scontiAttivi: scontiAttivi.length,
        scontoDalPost: SCONTO_TOTALE * 100 + '%'
    });
}

// === AGGIORNA LA VISUALIZZAZIONE DEI PREZZI ===
function aggiornaDisplayPrezzi(base, prezzoScontato, netto, scontoTotalePercent, numSconti) {
    // Aggiorna il prezzo base (con sconto applicato)
    
    const basePriceElement = document.getElementById('basePriceTxt');
    
    // Calcola lo sconto applicato al prezzo base (escludendo i supplementi)
    const prezzoBaseScontato = base * (1 - scontoTotalePercent / 100);
    
    if (basePriceElement) {
        if (scontoTotalePercent > 0) {
            basePriceElement.innerHTML = 
                '<span class="prezzo-originale" style="text-decoration: line-through; color: #999; margin-right: 8px;">' + 
                base.toLocaleString('it-IT', { minimumFractionDigits: 2 }) + 
                ' €</span> ' +
                '<span class="prezzo-scontato" style="color: #e74c3c; font-weight: bold;">' + 
                prezzoBaseScontato.toLocaleString('it-IT', { minimumFractionDigits: 2 }) + 
                ' €</span>';
                
            // Aggiungi tooltip per mostrare la composizione degli sconti
            if (SCONTO_TOTALE > 0) {
                basePriceElement.title = `Include sconto del ${(SCONTO_TOTALE * 100).toFixed(1)}% dal catalogo`;
            }
        } else {
            basePriceElement.innerHTML = 
                '<span class="prezzo-base">' + 
                base.toLocaleString('it-IT', { minimumFractionDigits: 2 }) + 
                ' €</span>';
            basePriceElement.title = '';
        }
    }
    
    // Aggiorna il testo dello sconto
    const scontoTxtElement = document.getElementById('scontoTxt');
    if (scontoTxtElement) {
        if (scontoTotalePercent > 0) {
            let testoExtra = '';
            if (SCONTO_TOTALE > 0) {
                testoExtra = ` (di cui ${(SCONTO_TOTALE * 100).toFixed(1)}% dal catalogo)`;
            }
            scontoTxtElement.innerHTML = "Sconto totale applicato: <strong>" + scontoTotalePercent.toFixed(1) + "%</strong>" + testoExtra;
            scontoTxtElement.style.color = '#27ae60';
            scontoTxtElement.style.fontWeight = 'bold';
        } else {
            scontoTxtElement.innerHTML = "Sconto applicato: <strong>0%</strong>";
            scontoTxtElement.style.color = '#444';
            scontoTxtElement.style.fontWeight = 'normal';
        }
    }
    
    // Aggiorna il totale
    const totaleElement = document.getElementById('totaleTxt');
    if (totaleElement) {
        totaleElement.textContent = netto.toLocaleString('it-IT', { minimumFractionDigits: 2 }) + ' €';
        
        // Evidenzia il totale se ci sono sconti
        if (scontoTotalePercent > 0) {
            totaleElement.style.color = '#e74c3c';
            totaleElement.style.fontWeight = 'bold';
        } else {
            totaleElement.style.color = '';
            totaleElement.style.fontWeight = 'normal';
        }
    }
    

}

// === CONTATORE SCONTI ATTIVI ===
function aggiornaContatoreSconti(numSconti) {
    let contatoreElement = document.getElementById('contatore-sconti');
    
    if (numSconti > 0) {
        if (!contatoreElement) {
            contatoreElement = document.createElement('div');
            contatoreElement.id = 'contatore-sconti';
            contatoreElement.style.cssText = 'background: #e8f5e8; padding: 8px 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #2ecc71; font-size: 14px;';
            
            const listaSconti = document.getElementById('listaSconti');
            if (listaSconti) {
                listaSconti.parentNode.insertBefore(contatoreElement, listaSconti.nextSibling);
            }
        }
        
        const scontiDalPost = SCONTO_TOTALE > 0 ? 1 : 0;
        const scontiRimanenti = Math.max(0, 2 - numSconti);
        
        const testoPlurale = numSconti === 1 ? 'sconto attivo' : 'sconti attivi';
        const testoLimite = numSconti >= 2 ? ' (massimo raggiunto)' : ` (puoi aggiungerne ancora ${scontiRimanenti})`;
        
        let testoExtra = '';
        if (scontiDalPost > 0) {
            testoExtra = ` - <em>${scontiDalPost} dal catalogo</em>`;
        }
        
        contatoreElement.innerHTML = `<strong> ${numSconti} ${testoPlurale}${testoLimite}${testoExtra}</strong> - Risparmi sul prezzo base!`;
    } else if (contatoreElement) {
        contatoreElement.remove();
    }
    
    return numSconti;
}

// === INIZIALIZZAZIONE EVENT LISTENER ===
function inizializzaSconti() {
    // Listener per checkbox sconti con gestione limite
    document.querySelectorAll('.sconto-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (gestisciLimiteSconti(this)) {
                recalc();
            }
        });
    });
    
    // Listener per codice sconto
    document.getElementById('codice_sconto').addEventListener('input', function() {
        gestisciLimiteSconti(null);
        recalc();
    });
    
    // Listener per tutti gli altri elementi che influenzano il prezzo
    ['id_giocatore','logo_g','nome_pers','num_pers','logo_p'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', recalc);
            el.addEventListener('change', recalc);
        }
    });
}

// === CAMBIO TAGLIA - AGGIORNATO ===
document.getElementById('taglia').addEventListener('change', e => {
    const opt = e.target.options[e.target.selectedIndex];
    
    // Aggiorna ID maglia e prezzo base
    document.getElementById('maglia_id').value = opt.dataset.id;
    document.getElementById('base_price').value = opt.dataset.price;
    document.getElementById('imgMaglia').src = opt.dataset.img || 'img/placeholder.png';
    
    // AGGIORNA LO SCONTO DINAMICAMENTE per la taglia selezionata
    currentDiscount = parseFloat(opt.dataset.discount) || 0;
    const codice_sconto = document.getElementById('codice_sconto');
    if(codice_sconto)
        if(currentDiscount > 0) {
            codice_sconto.disabled = true;
            codice_sconto.hidden = true;
            document.getElementById('scontoTxt').hidden = true;
        } else {
            codice_sconto.disabled = false;
            codice_sconto.hidden = false;
            document.getElementById('scontoTxt').innerHTML = "Sconto applicato: <strong>0%</strong>";
            document.getElementById('scontoTxt').hidden = false;
        }
    
    // Aggiorna la visualizzazione del prezzo base
    const basePriceElement = document.getElementById('basePriceTxt');
    if (basePriceElement) {
        const basePrice = parseFloat(opt.dataset.price);
        
        // Calcola il prezzo base con tutti gli sconti (incluso quello dal POST)
        const { percentualeTotale } = calcolaScontoTotale();
        const prezzoBaseScontato = basePrice * (1 - percentualeTotale / 100);

        if (percentualeTotale > 0) {
            basePriceElement.innerHTML = 
                '<span class="prezzo-originale" style="text-decoration: line-through; color: #999;">' + 
                basePrice.toLocaleString('it-IT', { minimumFractionDigits: 2 }) + 
                ' €</span> ' +
                '<span class="prezzo-scontato" style="color: #e74c3c; font-weight: bold;">' + 
                prezzoBaseScontato.toLocaleString('it-IT', { minimumFractionDigits: 2 }) + 
                ' €</span>';
        } else {
            basePriceElement.textContent = basePrice.toLocaleString('it-IT', { minimumFractionDigits: 2 }) + ' €';
        }
    }
    
    // Ricalcola il totale
    recalc();
});
// Disabilita l'opzione vuota dopo la selezione
document.getElementById('taglia').addEventListener('change', function() {
    const noneOption = this.querySelector('option[value=""]');
    if (this.value !== "") {
        noneOption.disabled = true;
    }
});

// === Eventi dinamici per ricalcolo ===
['id_giocatore','logo_g','nome_pers','num_pers','logo_p','codice_sconto'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', recalc);
    if (el) el.addEventListener('change', recalc);
});

// === Gestione pulsanti ===
const form = document.getElementById('acquistoForm');
const indirizzo = document.getElementById('indirizzo');

document.getElementById('btnCarrello').addEventListener('click', () => {
    const tagliaSelezionata = document.getElementById('taglia').value;
    if (!tagliaSelezionata) {
        alert("Seleziona una taglia prima di aggiungere al carrello!");
        document.getElementById('taglia').focus();
        return;
    }
    document.getElementById('azione').value = 'carrello';
    form.submit();
});

document.getElementById('btnAcquista').addEventListener('click', () => {
    const tagliaSelezionata = document.getElementById('taglia').value;
    if (!tagliaSelezionata) {
        alert("Seleziona una taglia prima di acquistare!");
        document.getElementById('taglia').focus();
        return;
    }
    
    document.getElementById('azione').value = 'acquista';
    if (indirizzo.value.trim() === '') {
        alert("Inserisci l'indirizzo di consegna prima di acquistare!");
        indirizzo.focus();
        return;
    }
    
    form.submit();
});

// === Nascondi prezzo finché non viene selezionata una taglia ===
document.addEventListener("DOMContentLoaded", function() {
    const priceContainer = document.querySelector('.price');
    const totalContainer = document.querySelector('.total'); 
    const scontoTxtSelect = document.getElementById('scontoTxt');
    const tagliaSelect = document.getElementById('taglia');
    
    // All'inizio nascondi il prezzo
    if (priceContainer) priceContainer.style.display = 'none';
    if (totalContainer) totalContainer.style.display = 'none';
    if (scontoTxtSelect) scontoTxtSelect.style.display = 'none';

    tagliaSelect.addEventListener('change', function() {
        if (this.value === "") {
            if (priceContainer) priceContainer.style.display = 'none';
            if (totalContainer) totalContainer.style.display = 'none';
            if (scontoTxtSelect) scontoTxtSelect.style.display = 'none';
            if (contenitoreSconti) contenitoreSconti.style.display = 'none';
        } else {
            if (priceContainer) priceContainer.style.display = 'block';
            if (totalContainer) totalContainer.style.display = 'block';
            if (scontoTxtSelect) scontoTxtSelect.style.display = 'block';
            
        }
    });
});

// === AVVIA TUTTO QUANDO LA PAGINA È CARICATA ===
document.addEventListener('DOMContentLoaded', function() {
    inizializzaSconti();
    
    // Aspetta che il DOM sia completamente pronto prima di calcolare
    setTimeout(function() {
        if (document.getElementById('taglia').value !== "") {
            recalc();
        }
    }, 100);
});
</script>
</body>
</html>