# Occupazioni realtime con Laravel Reverb

Questa release sostituisce il polling ogni 5 secondi della release dell'11 novembre. Non usa lo scheduler per aggiornare la dashboard.

## Comportamento

- Prenotazioni, variazioni di stanza/orari, aggiornamenti del mezzo, cestino/ripristino e cancellazione definitiva notificano le dashboard dopo il commit.
- Un evento privato `occupancy.changed` contiene soltanto `refresh: true`. Non trasmette nominativi, targhe, ID pratica o documenti.
- Il browser riceve la notifica via WebSocket e richiede uno snapshot autorizzato al widget Livewire. Non ci sono richieste HTTP a intervalli fissi.
- Gli operatori vedono le risorse occupate e gli orari globali, con dettagli/link soltanto delle pratiche a cui possono accedere. Un canale privato richiede una sessione attiva.
- Countdown in ore/minuti/secondi e barre vengono aggiornati localmente ogni 250 ms, usando un orologio monotono allineato al server. Il tempo non dipende dalle impostazioni dell'orologio del PC.
- Alla fine dell'intervallo il mezzo/la stanza risultano liberi, oppure passa in corso la prenotazione successiva. Non serve un job che modifichi lo stato al secondo esatto.
- La dashboard mostra occupazioni correnti, prenotazioni future, conflitti e stato della connessione. Alla disconnessione non indica la disponibilità come sincronizzata; alla riconnessione e al ritorno sulla pagina riallinea lo snapshot. Nessun fallback a polling HTTP.
- Gli eventi sono accodati sulla connessione database, coda `realtime`, con tentativi successivi in caso di indisponibilità temporanea di Reverb. Il worker dedicato evita di accodare gli aggiornamenti dietro altri lavori. Il database worker in attesa può aggiungere circa un secondo alla consegna: non è una garanzia di tempo reale deterministico.

## Componenti

Reverb 1.13, Laravel Echo e Pusher JS (protocollo compatibile, nessun account Pusher esterno). Una singola istanza non richiede Redis. Nel lock sono stati aggiornati anche i pacchetti Symfony coinvolti in avvisi di sicurezza e le dipendenze compatibili, mantenendo la serie 7.4; audit Composer e npm senza vulnerabilità segnalate dopo l'aggiornamento.

Il browser usa `wss://casafuneraria.deviommi.it/realtime/app/...` sulla porta 443. Nginx termina TLS e inoltra a Reverb su `127.0.0.1:8085`. L'API di pubblicazione degli eventi resta su loopback. L'autorizzazione dei canali passa da `/broadcasting/auth`, protetta da sessione e account attivo. Gli eventi client Reverb sono disabilitati e l'origine consentita è il dominio del gestionale.

Il bundle legge la configurazione pubblica dalla pagina, non da credenziali incluse durante la build: il segreto Reverb non va al browser. Le impostazioni sono in `config/reverb.php`, `config/broadcasting.php` e `config/occupancy.php`.

## Pubblicazione dal PC

La build in `public/build` è già generata. Per pubblicarla insieme al codice tramite Git, includerla esplicitamente perché la directory è normalmente ignorata:

```powershell
git add -A
git add -f public/build
git commit -m "Aggiunge dashboard WebSocket Reverb per mezzi e stanze"
git push origin main
```

Non committare `.env`. Non serve Node sul server se pubblichi la build pronta. In alternativa, con Node compatibile già disponibile sul server, la build si rigenera con `npm ci --include=dev` e `npm run build`.

## Installazione sul server Plesk

Destinazione predisposta:

- Progetto `/var/www/vhosts/casafuneraria.deviommi.it/httpdocs`.
- PHP CLI `/opt/plesk/php/8.3/bin/php`.
- Utente `casafuneraria.deviom_q6gbenkef0n`, gruppo `psacln`.
- Porta interna 8085, nessuna apertura pubblica di questa porta.

Dopo aver aggiornato il repository sul server:

```bash
cd /var/www/vhosts/casafuneraria.deviommi.it/httpdocs
/opt/plesk/php/8.3/bin/php artisan down
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
bash deploy/install-realtime.sh
```

Fermarsi se un comando segnala errori. Questa volta l'installazione Composer è necessaria: Reverb e il client PHP Pusher sono dipendenze nuove.

L'installatore:

1. Verifica root, percorso, utente PHP, disponibilità della porta e presenza della build. Se esiste una location realtime non gestita, si ferma prima di sovrascriverla.
2. Conserva backup privati di `.env`, configurazione Nginx e unità esistenti in `/root/casafuneraria-realtime-backups/<data-pid>/`.
3. Aggiorna solo le variabili realtime del `.env`; genera le credenziali mancanti senza stamparle e conserva quelle già presenti. Non cambia `APP_KEY`, credenziali DB o dati della pratica.
4. Esegue Artisan come utente del dominio: `down`, `optimize:clear`, `migrate --force`, `optimize`.
5. Aggiunge un include al `vhost_nginx.conf` del dominio, preservando le direttive precedenti. Il file incluso è `occupancy-reverb.nginx.conf`; viene eseguita la riconfigurazione del solo dominio con Plesk e il controllo `nginx -t`. Se l'installazione fallisce, tenta di ripristinare i file Nginx precedenti. Non modifica la configurazione globale degli altri domini.
6. Installa e avvia `casafuneraria-reverb.service` e `casafuneraria-realtime-worker.service`, con riavvio automatico e avvio al boot. I processi girano come utente del dominio, non root.
7. Controlla che i servizi siano attivi, esegue `artisan up` e mostra lo stato. In caso di errore il sito può restare in manutenzione: consultare i log e correggere la causa prima di riaprirlo.

Non eseguire `install:broadcasting` o `reverb:install`: configurazione e dipendenze sono già predisposte; questi comandi potrebbero modificare nuovamente i file del progetto. Non occorrono nuove migrazioni specifiche di Reverb; `migrate --force` applica eventuali migrazioni pendenti, inclusa la tabella jobs già prevista da Laravel.

I file predisposti sono `deploy/install-realtime.sh`, `deploy/configure-realtime.php`, `deploy/nginx/occupancy-reverb.conf` e `deploy/systemd/*.service`. Sono configurati per questo dominio/utente/PHP; l'installatore non è generico per altri siti. Il configuratore PHP accetta solo CLI sul percorso Linux atteso.

## Variabili gestite

```dotenv
OCCUPANCY_REALTIME_ENABLED=true
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=generato-o-conservato
REVERB_APP_KEY=generata-o-conservata
REVERB_APP_SECRET=generato-o-conservato
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8085
REVERB_SERVER_PATH=
REVERB_HOST=127.0.0.1
REVERB_PORT=8085
REVERB_SCHEME=http
REVERB_PUBLIC_HOST=casafuneraria.deviommi.it
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https
REVERB_ALLOWED_ORIGINS=casafuneraria.deviommi.it
REVERB_SCALING_ENABLED=false
```

Non copiare i segnaposto delle credenziali: li genera l'installatore. `REVERB_HOST` è l'indirizzo interno usato da PHP; `REVERB_PUBLIC_HOST` è quello sicuro usato dal browser. Il client non usa variabili `VITE_REVERB_*`, quindi non serve rifare la build quando si configurano le credenziali sul server.

## Gestione ordinaria e diagnosi

```bash
systemctl status casafuneraria-reverb.service casafuneraria-realtime-worker.service --no-pager
journalctl -u casafuneraria-reverb.service -n 80 --no-pager
journalctl -u casafuneraria-realtime-worker.service -n 80 --no-pager
ss -ltnp | grep ':8085'
```

Dopo successivi aggiornamenti del codice/configurazione e ricostruzione delle cache:

```bash
systemctl restart casafuneraria-reverb.service casafuneraria-realtime-worker.service
```

I riavvii fanno riconnettere automaticamente le dashboard. Lo scheduler Plesk `schedule:run`, se già attivo per la pulizia dei file, può rimanere; non avvia e non mantiene i due servizi. Non aggiungere Reverb o `queue:work` allo scheduler.

In caso di interruzione lunga controllare anche `php artisan queue:failed`. Il comando `php artisan queue:retry ID` può riprovare un singolo job realtime dopo aver corretto il problema; non riprovare indiscriminatamente altri lavori dell'applicazione. Un evento perso per guasto della coda prima dell'accodamento viene registrato nei log: il riallineamento avviene alla riconnessione, al ritorno sulla pagina o tramite Aggiorna ora.

Sul browser: Ctrl+F5 dopo il deploy, badge `Live · WebSocket connesso`, richiesta WebSocket con stato 101 nella scheda Rete. Da due sessioni autorizzate, registrare una vera prenotazione e verificare l'aggiornamento dell'altra dashboard, countdown e scadenza. La verifica funzionale sul server resta a cura dell'utente.

## Verifiche locali

Dipendenze installate senza script applicativi, build Vite eseguita, formattazione/sintassi PHP e JS e sintassi dello script Bash controllate. Nessun test funzionale/browser, avvio Reverb/worker, accesso al database operativo o esecuzione dell'installatore sul server. Non sono stati inviati messaggi WhatsApp.

Riferimenti: [Laravel Reverb](https://laravel.com/docs/12.x/reverb), [Broadcasting Laravel](https://laravel.com/docs/12.x/broadcasting), [direttive personalizzate Plesk](https://support.plesk.com/hc/en-us/articles/12377861295895-How-to-add-custom-Apache-nginx-configuration-for-a-domain-in-Plesk).
