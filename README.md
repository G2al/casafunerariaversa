# Gestionale Casa Funeraria Aversa

Laravel 12 / Filament 5, pannello italiano `/admin`, timezone Europe/Rome. Applicazione per una sola agenzia, senza fatturazione o pagamenti.

## Avvio locale

Il progetto e il database esistenti sono già configurati. La migrazione incrementale è stata applicata; account, password e APP_KEY sono preservati.

```powershell
php artisan serve
```

Aprire http://127.0.0.1:8000/admin con l’account esistente. Gli asset sono già compilati. Dopo modifiche agli asset: `npm run build`. Su una nuova copia con dipendenze mancanti: `composer install`, `npm ci`, `php artisan migrate`, `npm run build`. Non usare lo script generico `composer setup` sul database esistente.

## Utilizzo

1. L’amministratore gestisce **Utenti**, **Impostazioni agenzia**, **Personale trasporto** e **Mezzi**. Impostare i dati aziendali verificati: nessuna denominazione o partita IVA è stata copiata dalle fotografie. La camera ardente ha un indirizzo separato, inizialmente Via Savoia 31, Aversa, da verificare.
2. Caricare le cornici definitive da **Amministrazione → Cornici manifesti**, percorso `/admin/manifest-templates`. Sono disponibili caricamento singolo e multiplo, ordine, anteprima, ricerca e disattivazione. Il catalogo operativo parte vuoto; i tre campioni sono riservati ai test. Le cornici già utilizzate non si eliminano; la sostituzione non altera i PDF archiviati.
3. In **Pratiche funebri** scegliere esattamente una delle due procedure: **Cremazione** o **Trasporto funebre normale**. Salvare anche dati incompleti; completare successivamente le sezioni. Il codice annuale è automatico. L’età è quella al decesso.
4. Nel tab **Manifesto** scegliere una cornice, eventuale foto e testi; le sezioni facoltative si possono omettere. **Salvare prima dell’anteprima**. L’anteprima è il PDF effettivo dei dati salvati; i dati mancanti sono indicati nella pagina di anteprima e nell’azione di generazione. Testi troppo lunghi vengono adattati oppure segnalati prima dell’archiviazione.
5. **Genera Documento / Genera Manifesto** crea una nuova versione privata. Le versioni precedenti sono scaricabili nello **Storico documenti e manifesti** in fondo alla pratica. L’anteprima non crea versioni. Stampare il manifesto A4 orizzontale al 100%; il layout include 5 mm di margine.
6. **Panoramica pratiche**, elenco ed esportazioni rispettano il proprietario. **Report per operatore** è riservato agli amministratori: intervallo date, data di creazione/presa in carico/decesso, riepilogo mensile e CSV. «Create» indica l’autore originario; «Assegnate» il proprietario attuale. Il report include archiviate e cestino; il CSV dell’elenco rispetta i filtri applicati.

Un operatore vede solo le proprie pratiche, statistiche e file. L’amministratore può riassegnare le pratiche e ripristinare il cestino. Gli utenti associati allo storico si disabilitano, conservandone l’autore; l’ultimo amministratore attivo non può essere disabilitato, declassato o eliminato. La password dei nuovi utenti richiede almeno 12 caratteri; per cambiarla usare la modifica dell’utente.

## Documenti da verificare

I due template amministrativi riportano **BOZZA DA VERIFICARE**. Il riferimento 04 è annotato «Cremazione»; per il trasporto normale non è identificato con certezza un singolo originale. Il secondo generatore produce quindi una scheda operativa, senza presentarla come modulo ufficiale. Non vengono generati dichiarazione, verbale e delega aggiuntivi.

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
