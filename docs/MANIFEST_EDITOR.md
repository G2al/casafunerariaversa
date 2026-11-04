# Editor live di manifesto e prelapide

## Uso

Aprire una pratica salvata e scegliere **Modifica manifesto live**, disponibile tra le azioni PDF, anche nelle pagine di visualizzazione e modifica. Prima scegliere la cornice nel tab Manifesto e caricare l'eventuale foto nella pratica; il logo arriva dalle Impostazioni agenzia.

L'editor Fabric.js parte dalla disposizione automatica e permette di:

- trascinare testi, foto e logo, con aggancio al centro del foglio e dell'area testo;
- ridimensionare gli elementi e modificare posizione/dimensioni dal pannello;
- modificare il testo direttamente con doppio clic o dal pannello;
- cambiare carattere, dimensione, colore, grassetto, corsivo, allineamento e interlinea;
- aggiungere testi, foto/logo, simboli dal catalogo e ulteriori immagini JPEG/PNG/WebP;
- ingrandire i testi dagli angoli, compreso quello inferiore sinistro, come le foto;
- creare rettangoli, cerchi/ovali e triangoli con riempimento pieno o trasparente, colore e spessore del bordo;
- adattare immagini e forme al foglio, portarli sul fondo e creare cornici personalizzate;
- rimuovere elementi e cambiare ordine dei livelli;
- bloccare posizione/dimensioni, usare zoom, annulla/ripeti e frecce della tastiera (Shift: 10 punti);
- salvare e riaprire la composizione, ottenere anteprima PDF oppure salvare una nuova versione nello storico.

La cornice si può sostituire direttamente dalla barra dell’editor, mantenendo gli altri elementi. Le figure incorporate nello sfondo non sono separabili. Il cambio vale per questa composizione; non cambia la cornice scelta nella scheda della pratica.

**Salva composizione** memorizza soltanto il progetto. **Anteprima PDF** salva prima la composizione corrente e apre il PDF senza aggiungere una versione allo storico. **Salva e scarica PDF** salva il progetto e archivia una nuova versione. Sono disponibili al massimo 40 elementi.

## Aggiornamento dei dati

La composizione personalizzata è una disposizione salvata, non un aggiornamento automatico continuo dei campi della pratica. Se cambiano testi, foto, cornice o impostazioni rilevanti, compare un avviso. Il PDF già salvato rimane coerente con la composizione precedente.

**Aggiorna dalla pratica** ricarica testi e immagini mantenendo le posizioni e gli stili dei blocchi comuni. Sostituisce i testi personalizzati dei blocchi standard, previa conferma; mantiene i testi aggiunti liberamente. Poi si può adattare e salvare.

**Ripristina automatico** disattiva la composizione personalizzata e torna al generatore automatico già esistente, previa conferma. I PDF storici rimangono invariati. Anche nella scheda della pratica viene segnalato quando una composizione personalizzata è attiva.

## PDF e stampa

L'editor usa coordinate A4 orizzontali e font DejaVu locali. Al salvataggio il browser esporta il foglio a **3508 × 2480 pixel**, circa **300 DPI**, in JPEG qualità 97. L'immagine viene incorporata nel PDF A4 senza ricostruire testi e posizioni con un motore diverso; anteprima e generazione ordinarie del manifesto riutilizzano la composizione salvata.

Il testo rimane modificabile nel progetto dell'editor. Nel PDF di questa prima versione è parte dell'immagine, quindi non è selezionabile come testo vettoriale. Una bassa risoluzione della foto/cornice originale non viene recuperata dall'esportazione ad alta risoluzione.

## Accessi e conservazione

- Stesse policy delle pratiche: operatore sulle proprie pratiche, amministratore su tutte; editor disabilitato per le pratiche nel cestino.
- Salvataggi con CSRF, verifica proprietario, lock database e controllo revisione/dati sorgenti. Due finestre non si sovrascrivono silenziosamente.
- Schema applicativo limitato a testi, immagini autorizzate e forme; niente HTML, script o URL esterni nel progetto ricevuto. Dimensioni, colori, font, quantità e immagine finale vengono validati lato server.
- File generati in storage privato `manifest-editor/`; immagini aggiuntive private in `editor-uploads/` (massimo 50 per pratica, 10 MB ciascuna prima dell’ottimizzazione); immagini/font non vengono inviati a servizi esterni dall'editor. Le immagini di origine della composizione sono conservate nel progetto, per mantenerlo riapribile anche dopo una modifica alla pratica.
- Cestino conserva progetto e immagini. L'eliminazione definitiva del fascicolo rimuove tutte le immagini delle revisioni, oltre ai PDF e agli altri allegati. Restano le procedure di ritentativo per eventuali errori del disco.
- Audit di salvataggio/ripristino con autore e revisione, senza duplicare i testi nei messaggi di audit.

## Installazione e aggiornamento

Dipendenza Fabric.js **7.4.0**, licenza MIT conservata in [licenses/fabric-MIT.txt](licenses/fabric-MIT.txt). Il pacchetto è caricato soltanto nella pagina dell'editor. Nessun servizio grafico esterno o processo Node permanente sul server.

Migrazione incrementale: `2026_11_03_000200_add_manifest_editor_layout`, aggiunge il JSON nullable `manifest_layout` senza modificare le pratiche o i PDF esistenti.

```bash
php artisan optimize:clear
php artisan migrate --force
npm ci
npm run build
php artisan optimize
```

Se gli asset compilati vengono caricati dal computer di sviluppo, non è necessario compilarli sul server. PHP deve poter scrivere nello storage privato; non serve un nuovo `storage:link`.

Per i test PHP eseguire prima `php artisan optimize:clear`: la suite richiede SQLite in memoria. I controlli browser usano un database SQLite separato e dati inventati.

## Verifiche versione 3 novembre

Suite completa: **81 test superati, 1072 asserzioni**. Controllati nel browser Edge modifica di font/colore, annulla/ripeti, ridimensionamento e trascinamento della foto, salvataggio, riapertura, PDF e layout desktop/mobile. Nessun errore JavaScript nella prova conclusiva. PDF A4 orizzontale di una pagina, con immagine incorporata verificata identica a quella salvata tramite SHA256. Pint, compilazione Blade e build Vite riusciti; installazione riprodotta dal lockfile, audit npm senza vulnerabilità rilevate. Migrazione applicata al database locale con verifica di dati preesistenti e PDF storici invariati.

## Aggiornamento 4 novembre 2026

**Simboli** è una risorsa amministrativa in Risorse del servizio: titolo, immagine, ordine, attivazione, modifica e cancellazione singola/multipla. Gli operatori usano il catalogo sulle proprie pratiche. Le immagini aggiuntive appartengono alla singola pratica e non sono accessibili agli altri operatori.

Il modello automatico del manifesto ha postazioni fisse: foto a sinistra, introduzione, nome maiuscolo, coniuge quando disponibile, età, familiari, separatore, camera ardente, trasferimento, rito, ringraziamento e data. Logo fisso in basso a sinistra e frase Facebook su due righe. Il testo si adatta alla propria postazione; non inventa informazioni mancanti. Per applicare questa nuova base a un progetto personalizzato precedente usare **Ripristina automatico**.

**Modifica prelapide live** apre lo stesso editor con un progetto separato: doppio bordo rettangolare, nome in alto, date N./D., primo simbolo attivo secondo ordine/ID a sinistra e logo in basso a destra. Senza simboli il modello resta utilizzabile; si può caricare il primo nella nuova risorsa. La prelapide automatica e quella modificabile condividono la disposizione iniziale. Le modifiche ai due progetti non si sovrascrivono. I PDF storici restano immutabili.

Migrazione aggiuntiva `2026_11_04_000100_extend_graphic_editor`: progetto prelapide, catalogo simboli e immagini aggiuntive. Non richiede dipendenze Composer o un nuovo collegamento storage. Dopo il caricamento del codice eseguire `php artisan optimize:clear`, `php artisan migrate --force`, `php artisan filament:assets`, `php artisan optimize`. Ricompilare gli asset localmente con `npm ci` e `npm run build`, quindi caricare **tutta** `public/build` (manifest e assets) sul server.

Verifiche aggiornamento 4 novembre: suite completa **86 test, 1120 asserzioni**, build riprodotta da lockfile, Pint e Blade superati; audit npm produzione senza vulnerabilità. Prova browser con scala del testo dagli angoli, forme/riempimento/bordi/foglio/livelli, simboli, immagini aggiuntive, cambio cornice, salvataggio/riapertura e progetti separati su desktop/mobile. PDF dei due editor: una pagina A4 orizzontale, immagine incorporata identica al salvataggio (SHA256). Migrazione locale verificata senza variazioni di dati preesistenti e PDF storici.

## Cartelle dei simboli

La voce **Simboli** apre le cartelle. Creare una cartella, aprirla e usare **Carica più immagini** per importare fino a 100 immagini JPEG/PNG/WebP insieme, ottimizzate e salvate in privato. I titoli iniziali derivano dai nomi dei file. Dentro la cartella si possono modificare, disattivare o eliminare i simboli; dalla modifica si può cambiare cartella. Le cartelle piene sono protette dalla cancellazione.

Negli editor di manifesto e prelapide scegliere **Cartella simboli**, poi una immagine dalla galleria o dal selettore e premere **Inserisci**. La voce **Immagini della pratica** mostra gli upload aggiuntivi della pratica, separati dal catalogo condiviso.

La migrazione `2026_11_04_000200_add_symbol_folders` raccoglie gli eventuali simboli preesistenti in **Generali**, conservando immagini, identificativi e composizioni salvate. Pubblicare codice e `public/build` aggiornati, poi eseguire `php artisan optimize:clear`, `php artisan migrate --force`, `php artisan optimize`.
