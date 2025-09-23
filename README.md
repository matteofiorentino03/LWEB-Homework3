# PLAYERBASE

## Autori
- Cristian Buttaro ([@cristian03git](https://github.com/cristian03git))
- Matteo Fiorentino ([@matteofiorentino03](https://github.com/matteofiorentino03))

## Descrizione

**PLAYERBASE** è un piccolo **gestionale per una società di calcio**(in questo caso l'A.S. Roma), sviluppato in **PHP + MySQL + XML** (con HTML/CSS).  
Il sistema permette la gestione dei dati tramite **XML** per giocatori, maglie, acquisti, con **autenticazione e ruoli** utente/admin, operazioni **CRUD** *(Create-Read-Update-Delete)*, un **modulo di acquisto** e uno per la **richiesta di crediti** con **validazione via XSD**.

---

## Funzionalità

### Per gli utenti:
- Senza fare il login si possono fare le seguenti azioni, entrando da `homepage_user.php`:
  - Consultare il **catalogo maglie** (`catalogo_maglie.php`);
  - Visualizzare la **Tabella di tutti i giocatori dell'attuale stagione** (`tabella_giocatore.php`);
  - Visualizzare la **Classifica dei marcatori dell'attuale stagione** (`visualizzazione_classifica_marcatori.php`).
- Facendo il login (anche a seguito di una registrazione dell'account), oltre a vedere le pagine sopra citate, si possono fare le seguenti azioni:
  - Visualizzare lo **Storico degli acquisti effettuati** da quell'utente (`storico_acquisti_utente.php`) con la possibilità della **stampa di un singolo ordine** (`stampa_ordine.php`);
  - Effettuare le **Modifiche delle informazioni personali** dell'account(`modifica_info_utente.php`), con conseguente possibilità di effettuare una richiesta dei crediti ad un account admin;
  - **Acquisto guidato** (tramite `compra_maglia.php).

### Per gli amministratori:
Dopo aver effettuato l'accesso in `login.php`, l'admin, entrando in `homepage_admin.php`, può compiere le seguenti azioni:
- **Inserimento di un record** (`inserimenti.php`):
  - Inserimento di un giocatore (`inserimento_giocatore.php`), che può essere un portiere (`inserisci_portiere.php`), o un difensore (`inserisci_difensore.php`), o un centrocampista (`inserisci_centrocampista.php`) oppure un attaccante (`inserisci_attaccante.php`);
  - Inserimento di una maglia (`inserimento_maglia.php`).
- **Modifiche di un record** (`modifiche.php`):
  - Modifica di un giocatore (`modifica_giocatore.php` e `modifica_statistiche.php`);
  - Modifica di una maglia (`modifica_maglia.php`);
  - Modifica di un utente (`modifica_utente.php`).
- **Cancellazione di un giocatore** (`cancella_giocatore.php`);
- **Visualizzazione delle tabelle** (`visualizzazione_tabelle.php`):
  - Visualizzare la **Tabella di tutti i giocatori dell'attuale stagione** (`tabella_giocatore.php`);
  - Visualizzare la **Tabella di tutte le maglie** (`tabella_maglia.php`).
- **Visualizzazione degli utenti registrati** (`dashboard.php`);
- Effettuare l'**accettazione delle richieste dei crediti** dagli utenti (`accettazione_crediti.php`);
- Visualizzare lo **Storico degli acquisti effettuati** da tutti gli utenti (`storico_acquisti.php`);
- Visualizzare lo **Storico degli inserimenti effettuati** da tutti gli utenti admin (`storico_inserimenti.php`).

---

## Tecnologia usata

- **Frontend**: HTML5 + CSS3 (custom styling, layout responsive)  
- **Backend**: PHP 8.x, DOMDocument/XML, SQL (MySQLi)  
- **Dati XML**:
  - `agisce.xml` (validato con `agisce.dtd`);
  - `giocatori.xml`, `attaccanti.xml`, `centrocampisti.xml`, `difensori.xml`, `portieri.xml` (validati con i rispettivi file `.xsd`);
  - `compra.xml`, `crediti_richieste.xml` (validati con i rispettivi file `.xsd`);
  - `maglie.xml`, `maglie_giocatore.xml`, `maglie_personalizzate.xml` (validati con i rispettivi file `.xsd`). 

> Tutti i file XML vengono letti, scritti e validati dinamicamente via **DOM PHP**.

---

## Validazione XML

Ogni file XML usa:
- **DTD**: per file come `agisce.xml` (azioni utente);
- **XSD**: per i rimanenti file `.xml`.

> La validazione viene effettuata **prima della scrittura**. Se fallisce, viene mostrato un messaggio di errore.

---

## Setup & installazione

### Prerequisiti:
- Server Apache (XAMPP);  
- PHP ≥ 8.0 con estensione `mysqli` attiva;  
- MySQL o MariaDB. 

### 1. Configurazione `connect.php`:
La connessione viene gestita centralmente tramite `connect.php`,  
che include `config.php` generato automaticamente da `install.php`.

### 2. Avvio con `install.php`
- Crea automaticamente lo schema SQL `pbdef` *(o con un nome a piacimento)*, inserisce i dati demo;
- Crea la tabella `Utenti` in SQL;
- Inizializza tutti i file `.xml` nella cartella `/xml/`;
- Valida i file contro gli XSD o DTD corrispondenti.

⚠️ **Nota**: lanciare `install.php` solo una volta. Esegue una re-inizializzazione.

---

## Struttura delle cartelle

- `/img/` – immagini (logo, sponsor, maglie);  
- `/styles/` – file CSS divisi per pagina;  
- `/xml/` – contiene tutti i file XML strutturati e validati.  

---

## Note aggiuntive:

- `stampa_ordine.php`: genera PDF semplice del singolo acquisto;  
- Supporto per personalizzazione maglie: giocatori reali o nome+numero;  
- Aggiunta automatica dei **supplementi per il logo**, visibili nel pagamento:
  - Se si seleziona una Maglia di un Giocatore:
    - Il Supplemento sarà di 10 euro, laddove si scegliesse solo il Giocatore;
    - Il Supplemento sarà di 15 euro, laddove, oltre al Giocatore, si scegliesse il Logo della Competizione.
  - Se si seleziona una Maglia Personalizzata:
    - Il Supplemento sarà di 15 euro, laddove si scegliesse solo il Nome e il Numero di Maglia;
    - Il Supplemento sarà di 20 euro, laddove, oltre al Nome e il Numero di Maglia, si scegliesse il Logo della Competizione.
  - Altrimenti l'Utente paga il costo fisso di quella maglia.
- Statistiche dei ruoli (gol, assist, ecc.) gestite su **file XML separati**, evitando ambiguità.
