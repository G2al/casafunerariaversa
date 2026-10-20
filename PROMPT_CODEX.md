# MANDATO DI SVILUPPO — Gestionale Casa Funeraria Aversa

Sei un senior Laravel/Filament engineer incaricato di **progettare e implementare integralmente** un gestionale interno per una singola casa funeraria, direttamente in questo repository. Non limitarti a proporre codice: crea/modifica davvero file, migrazioni, modelli, servizi, policy, interfacce, test e documentazione; esegui verifiche e correggi gli errori. L'obiettivo è un'applicazione realmente utilizzabile, non un mockup.

## 0. REGOLE OPERATIVE: MASSIMA QUALITÀ CON MINIMO CONSUMO DI TOKEN

- **Rapporto qualità/token prioritario:** minimizza il consumo di token senza tagliare requisiti, sicurezza, test o qualità. Non confondere brevità con lavoro incompleto.
- Ispeziona **una sola volta** struttura, `composer.json`, versioni, pannello, `.env` senza stampare segreti, migrazioni esistenti e file rilevanti. Usa ricerche mirate (`rg`, `Get-ChildItem`, percorsi specifici), non dump ricorsivi dell'intero progetto o di `vendor/` e `node_modules/`.
- Prepara internamente un piano breve e implementa **per tranche ordinate**, continuando autonomamente alla tranche successiva dopo i test della precedente. Evita di interromperti per conferme non essenziali. Non ripetere lunghi riepiloghi o lo stesso contesto a ogni passaggio.
- Scrivi **codice completo nei file**, non lunghi snippet in chat. Comunica solo sintesi compatte: fase, file/moduli principali, esito test, blocchi reali. Alla fine un unico riepilogo con comandi per l'avvio.
- Riutilizza componenti/servizi esistenti, non duplicare form, query, autorizzazioni o logiche PDF. Se qualcosa è semplice non inventare livelli astratti superflui: **DRY, SOLID, KISS, responsabilità chiare, nomi parlanti**, typed code quando sensato.
- Evita dipendenze pesanti/inutili, documentazione prolissa, indagini web ripetitive e ri-scritture di codice funzionante. Controlla la documentazione **Filament 5** solo per API effettivamente incerte.
- Dopo ogni tranche esegui controlli **mirati**; suite completa e build al termine. Non consumare token con output enormi: sintetizza errori e usa estratti pertinenti.
- Conserva una traccia minima del progresso in `docs/IMPLEMENTATION_STATUS.md` così le sessioni successive possono riprendere senza rileggere tutta la chat. Non trascrivere deliberazioni lunghe o duplicare questo prompt.
- Se trovi un impedimento che non puoi risolvere in autonomia, completa il resto e indica **precisamente** il blocco. Non inventare fatti, norme, campi illeggibili o successi nei test.
- Non chiedere all'utente di programmare: devi implementare tu il progetto. L'utente caricherà **soltanto le cornici definitive** tramite la Resource dedicata quando la piattaforma sarà pronta.

## 1. STATO INIZIALE DEL REPOSITORY — NON REINIZIALIZZARE

Il progetto **esiste già** e funziona localmente su Windows/PowerShell:

- Laravel **12**, PHP locale compatibile (probabilmente 8.2: verifica), MySQL locale gestito con phpMyAdmin;
- Filament **5**, pannello `admin` già installato e login `/admin` funzionante;
- **un account amministratore già creato** con `php artisan make:filament-user`;
- database MySQL **già creato, collegato e migrato**;
- locale dell'app italiano (`it`), timezone desiderato `Europe/Rome`.

**Non** lanciare `composer create-project`, `filament:install`, `migrate:fresh`, `db:wipe`, `key:generate` o comandi distruttivi. Non resettare account, dati, `.env` o `APP_KEY`. Non riportare password o segreti nei messaggi. Aggiungi solo ciò che manca e usa migrazioni incrementali. Controlla prima le versioni reali dei pacchetti e segui le API della major installata: non usare sintassi Filament 3/4 incompatibile con Filament 5. Mantieni il percorso `/admin`.

## 2. OBIETTIVO OPERATIVO

Attualmente un operatore va dai familiari di una persona deceduta con **carta e penna**, raccoglie dati in una scheda, poi l'ufficio li ricopia in Excel e in documenti stampati. Digitalizza l'intero flusso: l'operatore accede anche da smartphone, crea la pratica, compila una sola volta i dati, eventualmente carica la foto del defunto e genera **il documento del tipo scelto** e **un manifesto funebre A4** usando una cornice selezionata.

Concetto fondamentale: **una pratica funebre è la fonte unica dei dati**. Documenti e manifesti devono derivare da campi normalizzati e non da testi ricopiati manualmente. Mantieni anche copie PDF immutabili delle generazioni precedenti.

Lingua dell'interfaccia: italiano; formato date `dd/mm/yyyy`, orari 24h, timezone `Europe/Rome`. Interfaccia professionale, sobria, responsive e leggibile su smartphone: il collaboratore deve compilare facilmente da casa dei familiari. Usa componenti Filament nativi e widget pertinenti, non costruire un frontend Next.js o un altro pannello separato.

## 3. RUOLI, ACCESSI E SICUREZZA

Ruoli applicativi: `admin` e `operator`.

- `admin`: visione e controllo completi di ogni pratica, utenti, modelli di manifesto, impostazioni, documenti e report; può creare, modificare, archiviare e ripristinare pratiche altrui; può riassegnare una pratica.
- `operator`: login nello **stesso pannello Filament** e funzioni operative simili all'admin **solo sulle proprie pratiche**; può creare, leggere, modificare, generare PDF ed eliminare logicamente le proprie pratiche. **Non** può vedere, aprire, modificare, esportare, scaricare PDF o scoprire dati di pratiche di altri operatori; non può creare utenti o modificare impostazioni globali/modelli.
- Ogni pratica ha `created_by`/`operator_id` proprietario, assegnato lato server dall'utente autenticato, mai fidandosi di input arbitrario. Solo l'admin può modificarlo. Verifiche **server-side** con Laravel Policies, Filament query scoping, route/controller per file/download, azioni e widget/statistiche. Un URL diretto o ID enumerato deve restituire 403/404 se non autorizzato.
- L'utente già presente è il **primo amministratore**: migra/assegna il suo ruolo senza cancellarlo; se ci sono altri utenti preesistenti, non promuoverli automaticamente senza certezza. Gli account creati in seguito devono ricevere un ruolo esplicito.
- Usa soft delete per le pratiche; niente cancellazione definitiva per operatori. Admin può gestire archiviate e ripristinarle. Traccia almeno autore, timestamp e tipo delle modifiche rilevanti. Proteggi dati dei familiari, fotografie e PDF in **storage privato**, mai con URL pubblici indovinabili; downloads autenticati/autorizzati. Validazione upload (tipo, dimensione, estensione, immagini malformate), autorizzazioni e protezione CSRF. Evita archiviazione di dati personali reali nelle factory/seed.

## 4. ANAGRAFICA PRATICA: CAMPI OBBLIGATORI E FUNZIONALITÀ

Prevedi una Filament Resource **Pratiche funebri** con elenco ricercabile, filtro per periodo/tipo/stato/operatore (quest'ultimo solo admin), ordinamento, badge, visualizzazione, modifica, creazione, soft delete, export ragionato CSV e azioni rapide per PDF.

### 4.1 Dati raccolti dal foglio cartaceo (tutti presenti)
- Cognome, nome del defunto.
- **Sesso** (maschio/femmina; usato per testi del manifesto, con formulazione adattabile).
- Luogo di nascita, data di nascita.
- Paternità, maternità.
- Residenza (indirizzo/comune, con campi coerenti per i documenti).
- Stato civile, professione.
- Luogo del decesso, data del decesso e **ora del decesso se nota**.
- Causa del decesso (campo previsto nella scheda; trattare come informazione riservata, non inserirla mai automaticamente sul manifesto).
- Chiesa.
- Cimitero di destinazione (`al cimitero di`).
- Telefono di riferimento.
- Foto facoltativa del defunto, con anteprima e rimozione.
- Ora/data di presa in carico o registrazione pratica; chi l'ha creata.

Calcola **l'età al momento del decesso** da nascita + data decesso (non età corrente), gestendo dati incompleti/ignoti, date impossibili e omonimie. Codice pratica univoco e progressivo nell'anno (es. `FUN-2026-0001`), assegnazione sicura rispetto a richieste contemporanee. Stati: `draft`, `in_progress`, `completed`, `archived` con etichette italiane. Salvataggio anche di pratiche incomplete in bozza; impedisci solo la generazione del documento finale quando mancano dati indispensabili, mostrando i campi mancanti.

### 4.2 Tipologia PRINCIPALE: ESATTAMENTE DUE FLUSSI
L'agenzia utilizza **due schede/documenti cartacei fissi**, corrispondenti a due procedure operative:
1. `cremation`: **Cremazione**.
2. `standard_transport`: **Trasporto funebre normale**.

Queste sono le **due opzioni principali dell'interfaccia**. Non sostituirle con tre voci cremazione/tumulazione/inumazione: la distinzione della sepoltura non è il flusso operativo richiesto. Se necessario alla compilazione dei documenti, usa un campo secondario facoltativo per destinazione/tipologia di sepoltura, senza inventare un terzo documento principale.

La selezione controlla sezioni e campi condizionali specifici, requisiti documentali e **il singolo modello fisso** generabile per quella pratica. Consentire cambi di tipologia conservando i dati comuni e avvertendo delle vecchie versioni PDF.

### 4.3 Familiare referente e organizzazione del rito
Per rendere la scheda utile ai documenti e al manifesto, prevedi campi separati e riutilizzabili:
- Familiare referente: nome, cognome, parentela, telefono, eventuale email, residenza e dati documento **solo se richiesti da un modello**.
- Altri familiari / persone da nominare nel manifesto, eventualmente tramite elenco strutturato, con possibilità di correggere liberamente il testo di annuncio.
- Indirizzo completo camera ardente, data e ora di inizio e fine esposizione, sede casa funeraria (selezionabile/precompilata dalle impostazioni).
- Chiesa/parrocchia per il rito, località, data/ora esequie, eventuale luogo e orario del trasferimento, cimitero/crematorio e destinazione.
- Note interne e particolari indicazioni organizzative (mai stampate se non esplicitamente previste).
- Per trasporto/documenti amministrativi: luoghi, comune competente, percorso/destinazione, data/ora trasporto; eventuale carro funebre, targa, operatore incaricato, direttore tecnico e estremi autorizzazioni **soltanto nei campi effettivamente necessari**.

Non rendere obbligatori dati prematuri; usa sezioni/tabs Filament mobile friendly con indicatori di completezza e messaggi di validazione comprensibili.

## 5. DOCUMENTI: SOLO I DUE MODELLI OPERATIVI PER ORA

I riferimenti fotografici sono dentro `docs/references/`:
- `05_scheda_raccolta_dati.jpg`: scheda cartacea da digitalizzare;
- `01_dichiarazione_sostitutiva.jpg`, `02_verbale_chiusura_feretro.jpg`, `03_delega_servizio_trattamento_dati.jpg`, `04_domanda_autorizzazione_trasporto.jpg`: fotografie della modulistica mostrata dall'utente. **Non assumere** che tutti e quattro vadano generati: per ora l'utente vuole **esclusivamente due documenti operativi fissi**, uno per cremazione e uno per trasporto normale.

**Prima di codificare i template**, esamina visivamente queste fotografie e ricostruisci per quanto leggibile il mapping fra tipo di pratica, campi e due modelli richiesti. Non attribuire arbitrariamente un documento al tipo sbagliato, non inventare contenuti giuridici o passaggi che non si leggono. Se le foto non permettono di verificare perfettamente il testo normativo o l'associazione esatta, implementa comunque i due generatori con struttura/template editabile da codice, etichetta **BOZZA DA VERIFICARE** sulle stampe non convalidate e documenta puntualmente il punto da verificare in `docs/DOCUMENT_TEMPLATES_REVIEW.md`. Non presentare moduli ricostruiti come ufficialmente conformi senza revisione dell'agenzia. Niente generazione degli altri moduli non richiesti.

Implementa per ciascuno dei due tipi:
- un template riutilizzabile, separato dalla logica di raccolta dati;
- compilazione automatica dal modello dati unico e impostazioni agenzia;
- preview, generazione, download e stampa PDF A4, con date e testi corretti in italiano;
- validazione dei campi necessari, segnalazione dati mancanti e versionamento delle generazioni (timestamp, utente, file, tipo e snapshot dei dati). Generare un nuovo PDF non sovrascrive quello già archiviato.

Preferisci una libreria PDF PHP compatibile con Windows/PHP realmente installato, senza obbligo di Chromium o strumenti di sistema non presenti, e in grado di gestire immagini e testi italiani. Fai una prova di PDF reale prima di considerare la funzionalità terminata. Prevedi eventuali sezioni configurabili per dati aziendali, personale e veicolo.

## 6. GENERATORE MANIFESTI FUNEBRI (FUNZIONE CENTRALE)

Riferimenti in `docs/references/`:
- `06_manifesto_esempio_compilato.jpg`: esempio di manifesto con dati e tipografia;
- `07_cornice_madonna_stelle.jpg`, `08_cornice_madonna_coronata.jpg`, `09_cornice_gesu.jpg`: esempi delle cornici.

La casa funeraria ha **circa 100 cornici**; tutte **hanno la stessa geometria e lo stesso spazio bianco centrale**, cambia principalmente il soggetto religioso sul lato sinistro. Non realizzare 100 viste o template di codice diversi: crea **un solo motore di impaginazione** con un catalogo di immagini di sfondo intercambiabili. Il formato è **A4 orizzontale (landscape)**, coerente con le fotografie: non deformare l'immagine, mantenere rapporto e margini di stampa. Usa un layout parametrico condiviso (coordinate e dimensioni relative) con preview coerente al PDF.

Realizza una Filament Resource amministrativa **Cornici manifesti** per upload multiplo/individuale delle immagini definitive, titolo, anteprima, ordine, attiva/disattiva, sostituzione, cancellazione protetta quando già utilizzata; visualizza una galleria selezionabile nella pratica (con preview e ricerca). Il catalogo può essere vuoto all'inizio e mostrare un empty state elegante. **L'unico lavoro che farà l'utente a progetto finito sarà caricare da sé le 100 immagini con questa Resource.** Le 3 immagini di riferimento servono allo sviluppo/test e non vanno spacciate per catalogo definitivo; puoi usarle temporaneamente in test/local development senza renderle obbligatorie.

Nel manifesto devono comparire in modo dinamico:
- Testo introduttivo correttamente flesso: “È venuto a mancare all'affetto dei suoi cari il Signor” / “È venuta a mancare all'affetto dei suoi cari la Signora”, configurabile in anteprima.
- Nome e cognome in grande, età **al decesso**.
- Fotografia del defunto **opzionale** (se manca, impaginazione gradevole senza spazio vuoto anomalo).
- Testo libero della famiglia per il triste annuncio; permetti inserimento dei nominativi e correzione manuale del testo solo per quello specifico manifesto, senza corrompere l'anagrafica comune.
- Camera ardente: sede, indirizzo, data e ora inizio/fine.
- Trasferimento della salma e/o rito: parrocchia/chiesa, località, data/ora della cerimonia.
- Eventuali messaggi finali fissi configurabili: “Il presente vale anche per ringraziamento”, recapiti/logo agenzia, luogo/data.

Offri una **preview visiva affidabile** prima di generare, con aggiornamento dei dati, possibilità di scegliere un'altra cornice senza perdere il testo, opzioni per omettere sezioni facoltative, controllo overflow e adattamento ragionevole dei nomi lunghi. Esporta PDF A4 landscape realmente stampabile e leggibile, con accenti/font incorporati. I contenuti non devono sovrapporsi alla figura religiosa sul lato sinistro: usa una zona sicura di testo regolabile nella configurazione comune. Memorizza manifesto generato (pratica, cornice, autore, data, snapshot di contenuti, PDF privato). Generare una nuova versione non cancella le precedenti.

L'utente non deve installare software di grafica né editare codice per usare le cornici.

## 7. GESTIONE UTENTI, AGENZIA, PERSONALE, AUTOMEZZI

Implementa nell'admin:
- **Utenti**: CRUD, nome, email univoca, ruolo admin/operator, stato attivo, password impostabile o resettabile in sicurezza. Non consentire di rimuovere/disabilitare l'ultimo admin né di escalare privilegi dal form operatore.
- **Impostazioni agenzia**: ragione sociale/denominazione, nome commerciale, logo, partita IVA/codice fiscale facoltativi, indirizzi (sede operativa e casa funeraria), telefono, email, eventuali dati legali e testi del manifesto. Valori riutilizzati nei documenti.
- **Personale abilitato al trasporto** e **mezzi**: anagrafiche minime pertinenti alle sezioni dei modelli stampati, con targa, qualifica/estremi utili, stato attivo. Non implementare gestione HR o flotta avanzata.

Riferimento da verificare e **non confondere con la sede legale**: negli esempi la casa funeraria per la camera ardente è indicata in **Via Savoia 31, Aversa**. I documenti fotografati citano anche indirizzi/denominazioni differenti in relazione alla ditta e al trasporto. Modella **indirizzo casa funeraria** e **dati della società** come campi distinti, modificabili dall'admin; usa Via Savoia 31 solo come default plausibile per la casa funeraria, non inventare ragione sociale, partita IVA o recapiti. Qualunque dato esterno deve essere verificato prima di fissarlo come anagrafica ufficiale.

## 8. DASHBOARD, REPORT E ANALISI

Dashboard Filament personalizzata, gradevole, con widget utili e non decorativi:
- conteggio pratiche totali (admin) / proprie (operator), nuove oggi, mese corrente, in lavorazione, completate, archiviate;
- pratiche recenti con link rapido;
- ripartizione cremazione/trasporto normale;
- andamento mensile (ultimi 12 mesi) e riepilogo per mese/anno/intervallo date;
- report amministratore per operatore con numero pratiche create/gestite, stato e tipo; tabella con filtri temporali ed export CSV;
- filtri per data di creazione/pratica e, quando utile, data decesso chiaramente distinguibili;
- contatori e grafici con query aggregate efficienti, senza N+1.

L'operatore può consultare statistiche **solo sulle proprie pratiche** e non deve conoscere totali/nomi di altri operatori attraverso widget o esportazioni. Testa i permessi anche sulle aggregazioni.

## 9. STRUTTURA DEL CODICE, DATI, RESPONSABILITÀ

Usa convenzioni Laravel standard e API Filament 5. Nomi PHP/database in **inglese chiaro e consistente**, label UI in italiano. Entità indicative (adatta se trovi soluzione più pulita):

- `User` (+ `role`, `is_active`), `FuneralCase`, `FuneralCaseContact` o relazioni familiari;
- `FuneralDocument` (versioni PDF), `ManifestTemplate`, `FuneralManifest` (versioni/snapshot);
- `CompanySettings` o tabella di impostazioni single-tenant;
- `FuneralStaffMember`, `FuneralVehicle` se necessari per i modelli;
- eventuale audit/event log semplice.

Usa migration complete con indici su owner, tipo, stato, date, foreign key, vincoli e unique dove utile; Enum PHP per ruolo/tipo/stato quando semplificano validazione. Factory e test senza dati reali. No multi-tenancy, no contabilità/fatturazione, no pagamenti, no notifiche WhatsApp, no firme digitali in questa versione.

Evita god classes e logica di dominio nei metodi di configurazione dei form Filament. Separa appropriatamente:
- Resource/Page Filament per UI e actions;
- Policies per accessi;
- Service/Action per numerazione, logiche di pratica, assemblaggio dati documento, generazione PDF, layout manifesto e report;
- Controller sottili solo dove servono endpoint di preview/download protetti;
- template di stampa separati, riutilizzabili e testabili.

Preferisci Dependency Injection, query scopes, eager loading, transazioni DB per operazioni atomiche, validazione tramite regole/form e test; niente SQL o JSON hardcoded in widget, niente valori di contatto hardcoded nelle viste, niente duplicazione di logica per ciascuna cornice. Non aggiungere package per un problema risolvibile in maniera semplice col framework.

## 10. PERCORSO DI IMPLEMENTAZIONE (DA COMPLETARE AUTONOMAMENTE)

Implementa fino a consegna, senza fermarti al solo piano:

1. **Audit non distruttivo** del repository e versioni; individua i file di riferimento e registra l'eventuale loro assenza.
2. **Schema DB, Enum, Models, Policies e ruoli**, sicurezza/owner scoping + test base.
3. **Filament Resource Pratiche funebri** completa e mobile-friendly, compresi contatti, salvataggio bozze, validazioni, foto e stati.
4. **Risorse amministrative** utenti, impostazioni agenzia, personale/mezzi se effettivamente necessari.
5. **Due generatori documentali** e storico PDF con download protetti.
6. **Resource cornici e generatore manifesti**: upload, anteprima A4 landscape, composizione dinamica e PDF.
7. **Dashboard e report** con filtri, raggruppamenti e CSV, rispettando i permessi.
8. **Hardening, test, build, fix, documentazione breve**.

Non interrompere il lavoro per chiedere conferme su dettagli ordinari: scegli soluzioni sensate documentando le assunzioni. Solo se manca un riferimento essenziale non ricostruibile o è richiesta un'operazione distruttiva fermati su quel punto, completa gli altri moduli e segnala il blocco senza inventare.

## 11. CRITERI DI ACCETTAZIONE E TEST OBBLIGATORI

Verifica almeno questi scenari, con test automatici dove appropriato:

- Admin preesistente continua ad accedere al pannello; creazione di account operator funzionante.
- Operatore A crea una pratica, può modificarla, generare/scaricare suoi documenti e manifesti e archiviarla.
- Operatore B non può vedere né aprire per ID, esportare o scaricare file dell'operatore A, anche via URL diretto. Admin vede entrambe.
- Scheda pratica contiene **tutti i campi del foglio**; data di morte e nascita calcolano correttamente l'età al decesso; bozze incomplete si salvano.
- I due flussi generano soltanto il documento pertinente, validano dati mancanti e producono PDF scaricabile non vuoto; versioni precedenti restano leggibili.
- Upload cornice in Resource admin e selezione nella pratica; manifesto preview/PDF A4 landscape, posizionamento coerente per tutte e tre le cornici campione, sesso grammaticale corretto, testo lungo gestito, foto presente/assente.
- Nessun documento, immagine privata o report di altro operatore accessibile senza autorizzazione.
- Dashboard e report restituiscono numeri corretti con date/owner diversi e filtri per mese/intervallo; CSV coerente.
- Migrazioni su DB esistente **senza perdita dati**, `php artisan test`, eventuali test Filament/Livewire pertinenti, lint (`vendor/bin/pint --test` o format mirato) e build asset se necessaria. Test automatizzati sempre su DB di test isolato, **mai** sul MySQL reale con comandi distruttivi.

Non affermare che un PDF è riprodotto esattamente o normativamente valido senza una verifica contro i documenti originali. Evidenzia gap di leggibilità in revisione. Usa test/factory con persone e numeri fittizi.

## 12. CONSEGNA FINALE

Alla fine, niente lunghi resoconti: rispondi in italiano con:

1. Cosa è stato completato (sintesi per modulo).
2. Test e comandi realmente eseguiti, esiti e limiti verificati.
3. Comandi essenziali per avviare e usare il progetto locale.
4. Percorso della Resource in cui l'amministratore può **caricare le 100 cornici** dopo la consegna.
5. Eventuali punti specifici dei due modelli cartacei da far verificare prima della stampa ufficiale.

**Comincia ora esaminando il progetto, poi implementa davvero tutte le fasi. La richiesta non è un semplice piano o una consulenza: consegna codice funzionante.**
