# Gestionale Casa Funeraria Aversa

Laravel 12 / Filament 5, pannello italiano `/admin`, timezone Europe/Rome. Applicazione per una sola agenzia, senza fatturazione o pagamenti.

## Avvio locale

Il progetto e il database esistenti sono già configurati. La migrazione incrementale è stata applicata; account, password e APP_KEY sono preservati.

```powershell
php artisan serve
```

Aprire http://127.0.0.1:8000/admin con l’account esistente. Gli asset sono già compilati. Dopo modifiche agli asset: `npm run build`. Su una nuova copia con dipendenze mancanti: `composer install`, `npm ci`, `php artisan migrate`, `npm run build`. Non usare lo script generico `composer setup` sul database esistente.

## Utenti iniziali sul server

Il seeder predefinito non crea più utenti dimostrativi. Dopo `php artisan migrate --force`, eseguire:

```bash
php artisan config:clear
php artisan db:seed --force
php artisan optimize
```

Se non esiste un amministratore attivo, il comando richiede l’email e una password nascosta di almeno 12 caratteri e crea l’admin. Se un admin attivo è già presente, lo conserva. Gli altri utenti si possono creare da **Amministrazione → Utenti**.

Per creare anche un operatore iniziale o eseguire senza interazione, configurare nel `.env` `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PASSWORD` e, facoltativamente, `INITIAL_OPERATOR_NAME`, `INITIAL_OPERATOR_EMAIL`, `INITIAL_OPERATOR_PASSWORD` (vedi `.env.example`). Quindi usare `php artisan config:clear`, `php artisan db:seed --force --no-interaction` e `php artisan optimize`. Non sono previste password predefinite. Dopo il bootstrap si possono rimuovere le password `INITIAL_*` dal `.env` e rigenerare la cache con `php artisan optimize`.

Il seeder è ripetibile: non aggiorna nomi, password, ruoli o stato degli account esistenti. Se l’email appartiene a un account inattivo o con ruolo diverso, si interrompe senza promuoverlo. Le credenziali vengono validate prima di creare gli account; non servono factory o dipendenze di sviluppo sul server.

## Utilizzo

Per creare due pratiche con dati inventati ma plausibili, una cremazione e una trasporto normale:

```bash
php artisan db:seed --class=DemoFuneralCaseSeeder --force
```

Vengono create **Antonio Rinaldi — Cremazione** e **Teresa Moretti — Trasporto funebre normale**, assegnate al primo amministratore attivo. Nomi, familiari, indirizzi, date, riti, spostamenti, autorizzazioni e destinazioni delle ceneri sono plausibili e interamente inventati, senza etichette DEMO nei campi. Restano vuoti documento d'identità (tipologia, estremi, fronte/retro), foto e selezioni di direttore tecnico/operatore/personale. Mezzo e cornice sono già selezionati. Il seeder completa solo i campi aziendali vuoti o riconoscibili come vecchi valori automatici DEMO, conservando quelli configurati dall'utente; email con dominio riservato example.com. È escluso dal DatabaseSeeder ordinario, non crea utenti/personale e non genera PDF. Le due nuove pratiche sono riconosciute tramite audit: rilanciarlo conserva modifiche e cestino senza duplicarle, anche dopo la modifica delle note. Le pratiche precedenti, inclusa l'eventuale vecchia Mario Esempio DEMO, restano conservate.

Il seeder è allineato al flusso attuale: trasporto attivo e distanza iniziale **100 km**, modificabile. La cremazione include una traslazione precedente; il trasporto normale non la include e termina al **Cimitero comunale di Carinaro**, senza crematorio/ceneri. **Certificato di morte e modello ISTAT** restano da caricare manualmente. Non vengono compilati i campi ritirati (estremi autorizzazione e righe 5–7). I dati permettono di provare Documento, Manifesto e Prelapide; scegliendo Trasporto = No si può provare la sola delega. Le nuove impostazioni valgono per le pratiche create dal seeder aggiornato: gli esempi già presenti non vengono sovrascritti o ripristinati automaticamente.

1. L’amministratore gestisce **Utenti**, **Impostazioni agenzia**, **Personale trasporto** e **Mezzi**. Impostare i dati aziendali verificati: nessuna denominazione o partita IVA è stata copiata dalle fotografie. La camera ardente ha un indirizzo separato, inizialmente Via Savoia 31, Aversa, da verificare.
2. Caricare le cornici definitive da **Risorse del servizio → Cornici manifesti**, percorso `/admin/manifest-templates`. Sono disponibili caricamento singolo e multiplo, ordine, anteprima, ricerca e disattivazione. Il catalogo operativo parte vuoto; i tre campioni sono riservati ai test. Le cornici già utilizzate non si eliminano; la sostituzione non altera i PDF archiviati.
3. In **Pratiche funebri** scegliere esattamente una delle due procedure: **Cremazione** o **Trasporto funebre normale**. Salvare anche dati incompleti; completare successivamente le sezioni. Il codice annuale è automatico. L’età è quella al decesso.
4. Nel tab **Manifesto** scegliere una cornice, eventuale foto e testi; le sezioni facoltative si possono omettere. **Salvare prima dell’anteprima**. L’anteprima usa i dati salvati. Per i **documenti cremazione e trasporto normale**, anteprima, generazione e download funzionano anche con dati mancanti: i campi assenti restano vuoti o su righe da compilare, senza date automatiche. La completezza nella scheda è informativa. Il manifesto mantiene il controllo sui propri dati e sulla cornice. Testi troppo lunghi vengono adattati oppure segnalati prima dell’archiviazione.
5. **Genera Documento / Genera Manifesto** crea una nuova versione privata. Le versioni precedenti sono scaricabili nello **Storico documenti e manifesti** in fondo alla pratica. L’anteprima non crea versioni. Stampare il manifesto A4 orizzontale al 100%; il layout include 5 mm di margine.
6. **Panoramica pratiche**, elenco ed esportazioni rispettano il proprietario. **Report per operatore** è riservato agli amministratori: intervallo date, data di creazione/presa in carico/decesso, riepilogo mensile e CSV. «Create» indica l’autore originario; «Assegnate» il proprietario attuale. Il report include archiviate e cestino; il CSV dell’elenco rispetta i filtri applicati.

Un operatore vede solo le proprie pratiche, statistiche e file. L’amministratore può riassegnare le pratiche e ripristinare il cestino. Gli utenti associati allo storico si disabilitano, conservandone l’autore; l’ultimo amministratore attivo non può essere disabilitato, declassato o eliminato. La password dei nuovi utenti richiede almeno 12 caratteri; per cambiarla usare la modifica dell’utente.

Le tabelle **Pratiche funebri**, **Mezzi**, **Personale trasporto**, **Cornici manifesti** e **Utenti** consentono la selezione multipla. Solo nelle pratiche, l'amministratore può **Spostare nel cestino**, poi ripristinare o **Eliminare definitivamente** dal filtro dei record eliminati, singolarmente o in gruppo. Il cestino conserva tutti gli allegati. Gli operatori non possono eliminare pratiche, neppure proprie. Mezzi, personale e cornici già collegati a pratiche/PDF, gli utenti associati allo storico e l’ultimo admin attivo vengono conservati anche se inclusi nella selezione; il risultato segnala le eliminazioni parziali.

La rimozione dei file avviene dopo il commit del database, preservando quelli ancora usati da altre pratiche. Se il disco non consente la cancellazione, i percorsi restano in una coda persistente: `php artisan funeral:cleanup-deleted-files` ritenta la pulizia. Il comando è pianificato ogni ora e richiede lo scheduler Laravel attivo sul server (`php artisan schedule:run` ogni minuto tramite cron). Resta un evento minimo di eliminazione con codice pratica e autore, senza snapshot o nominativi del fascicolo. Dopo una cancellazione definitiva, rilanciare esplicitamente il seeder ricrea le pratiche di esempio mancanti.

## Documenti da verificare

Con **Trasporto = Sì**, entrambi i PDF contengono **quattro pagine**: domanda, verbale di chiusura feretro, dichiarazione dell'impresa e delega/privacy. Con **Trasporto = No**, si genera soltanto la delega, secondo l'impostazione provvisoria concordata in attesa del chiarimento con Ernesto. Il trasporto normale stampa il cimitero, senza riferimenti a cremazione o ceneri. Percorso e cimitero sono collegati ai moduli; le righe sulla traslazione precedente compaiono solo se attivata. Testi fissi, logo, autorizzazione dell'impresa e personale selezionato vengono compilati dalla pratica e dalle impostazioni. Nessuno dei due PDF riporta BOZZA. La riproduzione dei testi non costituisce una validazione normativa.

Dal menu PDF si può generare anche la **Prelapide A4 orizzontale**. Certificato di morte e modello ISTAT si caricano nel tab **Documento del defunto**; le nuove immagini degli upload vengono ottimizzate in WebP. Date scrivibili da tastiera. Dettagli completi e comandi in [RELEASE_2026-11-03.md](docs/RELEASE_2026-11-03.md).

**Modifica manifesto live** apre l'editor Fabric.js: testi, foto e logo trascinabili/ridimensionabili, stili, annulla/ripeti, zoom e salvataggio della composizione. Il PDF usa il foglio esportato a circa 300 DPI; in questa versione il testo del PDF è parte dell'immagine, mentre il progetto resta modificabile. Cornice fissa, file privati e versioni storiche conservate. Cambi ai dati della pratica vengono segnalati e si applicano con **Aggiorna dalla pratica**; **Ripristina automatico** torna al generatore precedente. Migrazione aggiuntiva `2026_11_03_000200_add_manifest_editor_layout` e dettagli in [MANIFEST_EDITOR.md](docs/MANIFEST_EDITOR.md).

Per aggiornare eseguire `php artisan migrate --force` e `php artisan optimize:clear`. Configurare **Impostazioni agenzia → Impresa incaricata del trasporto**, scegliere il direttore tecnico e completare il tab **Documentazione**. Nascita e residenza del direttore restano facoltative. In **Trasporto** selezionare l'elenco del personale, includendo l'operatore incaricato. Generare una nuova versione: i PDF storici non cambiano.

Per le pagine 2–4 configurare anche **Impostazioni agenzia → Dichiarazione dell'impresa e delega** (REA, licenze, INPS/INAIL). Nel tab **Documentazione** compilare identificazione tramite documento o testimone, tipo di cassa, verbale, autorizzazione, date della dichiarazione/delega e nascita del familiare. Per il trasporto normale indicare anche finalità del trasporto, affidatario, luogo/Comune/indirizzo delle ceneri. Crematorio e destinazione ceneri restano nel tab **Cremazione**, disponibile per entrambi i flussi perché previsto dagli originali. Gli altri dati del familiare vengono dal tab **Familiare referente**. Bozze incomplete sempre salvabili; contenuti eccessivi segnalati senza tagli.

Il tab **Documento del defunto**, disponibile già in creazione per entrambe le procedure, contiene quattro tipologie fisse (patente, carta d'identità cartacea/elettronica, passaporto), numero, ente, rilascio e scadenza. Gli estremi alimentano il verbale senza ricopiarli. Fronte e retro facoltativi in JPEG, PNG, WebP o PDF, massimo 10 MB ciascuno (immagini massimo 25 megapixel): salvati sul disco privato e scaricabili solo dal proprietario della pratica o da un admin. Non vengono aggiunti automaticamente alle quattro pagine del PDF o al manifesto. Le vecchie tipologie inserite liberamente restano utilizzabili nei PDF finché non si specifica una delle quattro opzioni. Nessuna nuova Resource o OCR.

Leggere [docs/DOCUMENT_TEMPLATES_REVIEW.md](docs/DOCUMENT_TEMPLATES_REVIEW.md) prima dell’uso amministrativo ufficiale. I template sono in `resources/views/pdf`; i campi comuni sono definiti in `config/funeral.php`.

## Verifiche e conservazione

```powershell
php artisan test --compact
php vendor/bin/pint --test
npm run build
```

La suite forza SQLite in memoria e interrompe l’avvio se rileva un’altra connessione, prima di eseguire RefreshDatabase. Non esegue reset del MySQL operativo. Copre permessi, UI Livewire, bozze, date, upload, PDF reali, versioni, cornici, overflow, report, CSV e migrazione di un account preesistente.

Per ottenere PDF dimostrativi locali con dati fittizi:

```powershell
$env:PDF_PREVIEW_ARTIFACTS='1'
php artisan test --compact --filter=PdfWorkflowTest
Remove-Item Env:PDF_PREVIEW_ARTIFACTS
```

Risultati in `storage/framework/testing/pdf-previews`, esclusi dal repository. Foto, cornici, logo e PDF operativi sono nel disco privato `storage/app/private`; accessi tramite route autenticate e policy. La causa del decesso è cifrata e non compare in PDF, manifesti o CSV. L’audit conserva autore, evento, data e campi modificati, senza duplicare i valori personali.

Per conservare lo storico, salvare insieme database, storage privato e APP_KEY originale. La numerazione usa transazioni e lock; non è stato eseguito un test di carico concorrente su MySQL. La conformità amministrativa dei moduli e la resa sulla stampante fisica richiedono verifica dell’agenzia.
