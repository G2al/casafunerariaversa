#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

DOMAIN='casafuneraria.deviommi.it'
PROJECT='/var/www/vhosts/casafuneraria.deviommi.it/httpdocs'
DOMAIN_USER='casafuneraria.deviom_q6gbenkef0n'
DOMAIN_GROUP='psacln'
PHP_BIN='/opt/plesk/php/8.3/bin/php'
CONF_DIR="/var/www/vhosts/system/$DOMAIN/conf"
VHOST="$CONF_DIR/vhost_nginx.conf"
SNIPPET="$CONF_DIR/occupancy-reverb.nginx.conf"
INCLUDE="include $SNIPPET;"
SERVICES=(casafuneraria-reverb.service casafuneraria-realtime-worker.service)

fail() { printf '%s\n' "$1" >&2; exit 1; }
[[ $EUID -eq 0 ]] || fail 'Eseguire come root.'
[[ "$(realpath "$(dirname "${BASH_SOURCE[0]}")/..")" == "$PROJECT" ]] || fail 'Percorso del progetto diverso da quello previsto.'
[[ -x "$PHP_BIN" && -f "$PROJECT/.env" && -d "$CONF_DIR" ]] || fail 'Verificare PHP 8.3, .env e directory del dominio.'
id "$DOMAIN_USER" >/dev/null
getent group "$DOMAIN_GROUP" >/dev/null
for tool in systemctl nginx plesk runuser ss; do command -v "$tool" >/dev/null || fail "Comando richiesto: $tool"; done
[[ -f "$PROJECT/vendor/laravel/reverb/src/ReverbServiceProvider.php" ]] || fail 'Eseguire prima composer install dal nuovo composer.lock.'
[[ -f "$PROJECT/public/build/manifest.json" ]] || fail 'Caricare prima public/build.'
"$PHP_BIN" -r '$m=json_decode(file_get_contents($argv[1]),true);exit(isset($m["resources/js/occupancy-live.js"],$m["resources/css/occupancy-live.css"])?0:1);' "$PROJECT/public/build/manifest.json" || fail 'Manca la nuova build realtime in public/build.'
if ss -ltnH | awk '{print $4}' | grep -qE '(^|:)8085$'; then
    systemctl is-active --quiet casafuneraria-reverb.service || fail 'Porta 8085 già occupata da un altro servizio.'
fi
if [[ -f "$VHOST" ]] && ! grep -Fqx "$INCLUDE" "$VHOST" && grep -Eq 'location[^{]*realtime' "$VHOST"; then
    fail 'Esiste già una location realtime nel dominio: verificarla prima di installare.'
fi

BACKUP="/root/casafuneraria-realtime-backups/$(date -u +%Y%m%d-%H%M%S)-$$"
install -d -m 0700 "$BACKUP"
cp "$PROJECT/.env" "$BACKUP/env"
chmod 0600 "$BACKUP/env"
OLD_VHOST=0; OLD_SNIPPET=0; NGINX_CHANGED=0
if [[ -f "$VHOST" ]]; then cp -p "$VHOST" "$BACKUP/vhost_nginx.conf"; OLD_VHOST=1; fi
if [[ -f "$SNIPPET" ]]; then cp -p "$SNIPPET" "$BACKUP/occupancy-reverb.nginx.conf"; OLD_SNIPPET=1; fi
for unit in "${SERVICES[@]}"; do
    if [[ -f "/etc/systemd/system/$unit" ]]; then cp -p "/etc/systemd/system/$unit" "$BACKUP/$unit"; fi
done
on_error() {
    local code=$?
    trap - ERR
    set +e
    if [[ $NGINX_CHANGED -eq 1 ]]; then
        if [[ $OLD_VHOST -eq 1 ]]; then cp -p "$BACKUP/vhost_nginx.conf" "$VHOST"; else rm -f -- "$VHOST"; fi
        if [[ $OLD_SNIPPET -eq 1 ]]; then cp -p "$BACKUP/occupancy-reverb.nginx.conf" "$SNIPPET"; else rm -f -- "$SNIPPET"; fi
        plesk sbin httpdmng --reconfigure-domain "$DOMAIN"
    fi
    printf '\nInstallazione interrotta. Backup privato: %s\n' "$BACKUP" >&2
    printf 'Il sito potrebbe essere in manutenzione. Consultare journalctl per i due servizi prima di riattivarlo.\n' >&2
    exit "$code"
}
trap on_error ERR

cd "$PROJECT"
# Nessuna modifica a APP_KEY, credenziali DB, dati utente o prenotazioni.
"$PHP_BIN" deploy/configure-realtime.php
chown "$DOMAIN_USER:$DOMAIN_GROUP" "$PROJECT/.env"
chmod 0600 "$PROJECT/.env"
chown -R "$DOMAIN_USER:$DOMAIN_GROUP" "$PROJECT/storage" "$PROJECT/bootstrap/cache"
artisan() { runuser -u "$DOMAIN_USER" -- "$PHP_BIN" "$PROJECT/artisan" "$@"; }
artisan down
artisan optimize:clear
artisan migrate --force
artisan optimize

NGINX_CHANGED=1
install -m 0644 "$PROJECT/deploy/nginx/occupancy-reverb.conf" "$SNIPPET"
if [[ ! -f "$VHOST" ]]; then install -m 0644 /dev/null "$VHOST"; fi
if ! grep -Fqx "$INCLUDE" "$VHOST"; then printf '\n# Casa Funeraria: WebSocket occupazioni\n%s\n' "$INCLUDE" >> "$VHOST"; fi
plesk sbin httpdmng --reconfigure-domain "$DOMAIN"
nginx -t
systemctl reload nginx
for unit in "${SERVICES[@]}"; do install -m 0644 "$PROJECT/deploy/systemd/$unit" "/etc/systemd/system/$unit"; done
systemctl daemon-reload
systemctl enable "${SERVICES[@]}"
systemctl restart "${SERVICES[@]}"
sleep 3
systemctl is-active --quiet casafuneraria-reverb.service
systemctl is-active --quiet casafuneraria-realtime-worker.service
artisan up
NGINX_CHANGED=0
printf '\nRealtime installato. Backup privato: %s\n' "$BACKUP"
systemctl --no-pager --full status "${SERVICES[@]}"
printf '\nRicaricare la dashboard con Ctrl+F5 e verificare il badge Live.\n'
