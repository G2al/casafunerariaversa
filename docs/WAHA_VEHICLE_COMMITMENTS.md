# Impegno mezzo e WhatsApp

Nella scheda Trasporto della pratica, selezionando un mezzo si vedono le foto caricate in Mezzi. I PDF del mezzo non vengono mostrati nella galleria. Le foto sono private e disponibili agli utenti attivi che possono creare pratiche; i mezzi disattivati restano consultabili dall'amministratore e dagli operatori con una propria pratica associata. Gli altri allegati del mezzo restano riservati all'amministratore.

## Configurazione sul server

Non è richiesta una nuova installazione di WAHA o di pacchetti Composer/NPM. Usare il servizio WAHA già presente e una sessione WhatsApp connessa.

Impostare nel `.env` di produzione:

```dotenv
WAHA_ENABLED=true
WAHA_URL=https://indirizzo-del-proprio-server-waha
WAHA_API_KEY=chiave-api-del-proprio-server-waha
WAHA_SESSION=default
```

`WAHA_URL` è l'URL base, senza `/api/sendText`, raggiungibile dal server PHP. `WAHA_SESSION` deve coincidere con il nome reale della sessione collegata. La chiave è una credenziale del server e non va inserita nei form o committata. Il destinatario è fisso nel codice: `393357378138@c.us` (+39 335 737 8138). Non viene richiesto al browser e non dipende dai numeri del familiare.

Dopo aver caricato il codice:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

Ricaricare completamente le pagine aperte. Nessuna modifica al JavaScript o nuova build richiesta per questa funzionalità. Non serve un queue worker: l'invio avviene nella richiesta di conferma, con timeout massimo di 25 secondi. La nuova migrazione `2026_11_06_000200` crea la tabella `vehicle_commitments`; la precedente `000100` aggiunge gli allegati dei mezzi, se non ancora applicata.

## Funzionamento

1. Scegliere il mezzo, compilare nome/cognome del defunto e Data e ora trasporto.
2. Premere **Impegna mezzo**: il riepilogo mostra defunto, mezzo, targa, data/ora e destinatario.
3. **Salva e invia WhatsApp** salva l'intera pratica con la validazione ordinaria e le relazioni, poi registra l'impegno e chiama WAHA. Funziona anche durante la creazione della pratica; dopo la creazione si apre la modifica della stessa pratica.
4. Il messaggio contiene codice pratica, nome/cognome del defunto, nome del mezzo, targa, Data e ora trasporto, eventuali partenza/destinazione e riferimento del tentativo. Non contiene foto, documenti d'identità o dati sanitari. Lo stato della pratica non viene impostato automaticamente su Completata.
5. Il riquadro della pratica riporta i dati dell'impegno e lo stato WhatsApp. **Accettato da WAHA** significa che l'API ha risposto con successo e un ID messaggio; non certifica consegna o lettura sul telefono, non essendoci un webhook di ricevuta.
6. Dopo un invio confermato, il pulsante diventa **Aggiorna impegno**. Una nuova conferma con identici dati non manda un duplicato. Modificando defunto, mezzo/targa, data/ora o partenza/destinazione, la conferma invia un aggiornamento. Il semplice salvataggio, incluso lo stato Completata, non manda messaggi.

L'impegno non blocca sovrapposizioni o disponibilità del veicolo. Non vengono inviati messaggi di cancellazione quando si cestina/elimina una pratica. L'impegno resta nel cestino insieme alla pratica; l'eliminazione definitiva lo rimuove tramite chiave esterna.

## Errori e invii ripetuti

Lock della pratica, impronta dei dati e identificativo del tentativo proteggono gli invii concorrenti e i doppi clic. I dati vengono persistiti prima della chiamata esterna; una transazione aperta impedisce l'invio. Ogni tentativo/esito viene annotato nell'audit della pratica, senza chiavi API o corpi delle risposte WAHA.

Non ci sono retry automatici. Un errore HTTP 4xx (eccetto 408) viene indicato come invio non riuscito; timeout, errore server o risposta senza ID sono esiti incerti. Verificare WhatsApp prima di premere **Riprova invio WhatsApp**, perché il messaggio potrebbe essere già partito. Un invio in corso impedisce altri tentativi per due minuti; dopo tale intervallo un click lo contrassegna come incerto senza spedire nulla, consentendo una successiva conferma esplicita. Un'interruzione del processo dopo l'invio e prima del salvataggio dell'esito non può garantire l'assenza di duplicati su un nuovo tentativo manuale.

Se WAHA non è configurato/attivato, il pulsante mostra un errore senza inviare messaggi; la pratica resta salvabile con le normali azioni.

## Verifiche

Per esplicita richiesta dell'utente questa modifica non è stata eseguita o testata localmente: nessun test automatico/browser, nessuna migrazione locale, nessun collegamento al servizio WAHA e nessun invio di prova. La verifica funzionale e l'applicazione delle migrazioni avverranno sul server a cura dell'utente.

Riferimento API: [WAHA Send Text](https://waha.devlike.pro/docs/how-to/send-messages/#send-text), `POST /api/sendText`, header `X-Api-Key`, corpo con `session`, `chatId` e `text`.
