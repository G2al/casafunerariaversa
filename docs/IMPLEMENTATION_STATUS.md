# Stato implementazione

- Audit: PHP 8.2.12, Laravel 12, Filament 5.10.2, MySQL esistente, un utente preesistente (ID 1). Nessun reset.
- Riferimenti: esaminate visivamente tutte le 9 immagini in docs/references.
- Completati: schema incrementale, ruoli/policy, scheda e bozze, foto private, utenti, impostazioni, personale/mezzi, catalogo cornici con upload multiplo, due generatori amministrativi, manifesti, versioni immutabili e download protetti, dashboard, report e CSV.
- Migrazione applicata al MySQL esistente (batch 2); verificato utente ID 1 attivo e admin. Nessuna pratica o cornice dimostrativa nel DB operativo.
- Verifiche finali: `php artisan test --compact` → 19 test, 203 asserzioni; `php vendor/bin/pint --test --format=txt` → passed; `npm run build` → riuscita.
- Test automatici: SQLite in memoria obbligatorio (env forzato + guardia prima di RefreshDatabase). Mai reset del MySQL operativo.
- Browser: Edge headless su seconda istanza SQLite isolata; login, dashboard, risorse admin, scelta cornice e salvataggio, pagina a 390 px senza overflow orizzontale; nessun errore JS/HTTP rilevato.
- PDF reali esaminati: i due amministrativi e manifesti con tre cornici, foto presente/assente e nome/testo lunghi. Campioni A4 di una pagina, font incorporati; manifesto orizzontale con margini di 5 mm. Artefatti fittizi in storage/framework/testing, esclusi dal repository.
- Limiti: nessuna prova di carico concorrente MySQL o stampa su dispositivo fisico. Associazione e testi dei due modelli amministrativi da convalidare: vedi DOCUMENT_TEMPLATES_REVIEW.md. Entrambi restano BOZZA DA VERIFICARE.
- Avvio e istruzioni: README.md. Cornici definitive: /admin/manifest-templates; catalogo iniziale vuoto.
- Bootstrap utenti: DatabaseSeeder richiama InitialUsersSeeder; admin interattivo/configurato e operatore facoltativo da INITIAL_OPERATOR_*. Nessuna password predefinita, nessun utente demo; account esistenti preservati. Configurazione in config/initial_users.php e .env.example, procedura server in README.md.
- Verifiche dopo bootstrap utenti: 25 test, 227 asserzioni; Pint passed. Sei test specifici coprono ruoli, password cifrate tramite hash, ripetibilità, account esistenti, interazione CLI, credenziali mancanti/conflitti e assenza di creazioni parziali.
