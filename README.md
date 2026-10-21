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

1. L’amministratore gestisce **Utenti**, **Impostazioni agenzia**, **Personale trasporto** e **Mezzi**. Impostare i dati aziendali verificati: nessuna denominazione o partita IVA è stata copiata dalle fotografie. La camera ardente ha un indirizzo separato, inizialmente Via Savoia 31, Aversa, da verificare.
2. Caricare le cornici definitive da **Risorse del servizio → Cornici manifesti**, percorso `/admin/manifest-templates`. Sono disponibili caricamento singolo e multiplo, ordine, anteprima, ricerca e disattivazione. Il catalogo operativo parte vuoto; i tre campioni sono riservati ai test. Le cornici già utilizzate non si eliminano; la sostituzione non altera i PDF archiviati.
3. In **Pratiche funebri** scegliere esattamente una delle due procedure: **Cremazione** o **Trasporto funebre normale**. Salvare anche dati incompleti; completare successivamente le sezioni. Il codice annuale è automatico. L’età è quella al decesso.
4. Nel tab **Manifesto** scegliere una cornice, eventuale foto e testi; le sezioni facoltative si possono omettere. **Salvare prima dell’anteprima**. L’anteprima è il PDF effettivo dei dati salvati; i dati mancanti sono indicati nella pagina di anteprima e nell’azione di generazione. Testi troppo lunghi vengono adattati oppure segnalati prima dell’archiviazione.
5. **Genera Documento / Genera Manifesto** crea una nuova versione privata. Le versioni precedenti sono scaricabili nello **Storico documenti e manifesti** in fondo alla pratica. L’anteprima non crea versioni. Stampare il manifesto A4 orizzontale al 100%; il layout include 5 mm di margine.
6. **Panoramica pratiche**, elenco ed esportazioni rispettano il proprietario. **Report per operatore** è riservato agli amministratori: intervallo date, data di creazione/presa in carico/decesso, riepilogo mensile e CSV. «Create» indica l’autore originario; «Assegnate» il proprietario attuale. Il report include archiviate e cestino; il CSV dell’elenco rispetta i filtri applicati.

Un operatore vede solo le proprie pratiche, statistiche e file. L’amministratore può riassegnare le pratiche e ripristinare il cestino. Gli utenti associati allo storico si disabilitano, conservandone l’autore; l’ultimo amministratore attivo non può essere disabilitato, declassato o eliminato. La password dei nuovi utenti richiede almeno 12 caratteri; per cambiarla usare la modifica dell’utente.

Le tabelle **Pratiche funebri**, **Mezzi**, **Personale trasporto**, **Cornici manifesti** e **Utenti** consentono la selezione multipla: spuntare le righe e aprire **Azioni sui selezionati → Elimina selezionati**. È disponibile anche l’eliminazione sulla singola riga. Le pratiche finiscono nel cestino, con ripristino singolo o multiplo riservato all’amministratore. Mezzi, personale e cornici già collegati a pratiche/PDF, gli utenti associati allo storico e l’ultimo admin attivo vengono conservati anche se inclusi nella selezione; il risultato dell’operazione segnala le eliminazioni parziali. Gli operatori possono eliminare in gruppo soltanto le proprie pratiche.

## Documenti da verificare

Il PDF **Cremazione** contiene **quattro pagine** nell'ordine degli originali: domanda di autorizzazione al trasporto, verbale di chiusura del feretro, dichiarazione sostitutiva dell'impresa, delega al servizio e trattamento dati. Include testi fissi, richiami normativi, dati aziendali e autorizzazioni, direttore tecnico, personale scelto con selezione multipla, operatore incaricato, mezzo, allegati e spazi per le firme. Usa il logo dell'agenzia senza la dicitura BOZZA. Il trasporto normale mantiene la scheda operativa **BOZZA DA VERIFICARE**. La riproduzione dei testi originali non costituisce una validazione normativa.

Per il nuovo modello eseguire `php artisan migrate --force` e `php artisan optimize:clear`. Configurare **Impostazioni agenzia → Impresa incaricata del trasporto**, completare l'anagrafica del direttore in **Personale trasporto**, quindi i campi aggiunti nel tab **Cremazione** della pratica. In **Trasporto** selezionare l'elenco del personale, includendo l'operatore incaricato. L'eventuale precedente selezione singola viene conservata e copiata nell'elenco dalla migrazione. Generare una nuova versione: i PDF storici non cambiano.

Per le pagine 2–4 configurare anche **Impostazioni agenzia → Dichiarazione dell'impresa e delega** (REA, licenze, posizioni INPS/INAIL). Nel tab **Cremazione** compilare identificazione tramite documento del defunto o testimone, tipo di cassa, verbale, autorizzazione del singolo trasporto, date della dichiarazione/delega e nascita del familiare. Gli altri dati del familiare vengono dal tab **Familiare referente**; l'indirizzo deve comprendere il civico. Il PDF mantiene quattro pagine con adattamento del carattere e segnala i contenuti troppo lunghi senza tagliarli. Bozze incomplete sempre salvabili.

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
