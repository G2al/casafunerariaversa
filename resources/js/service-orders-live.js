import Pusher from 'pusher-js';

const clients = new Map();
document.addEventListener('click', event => {
    const target = event.target.closest('a,button');
    if (!target || target.closest('[data-service-order-form]')) return;
    if (document.querySelector('[data-service-order-form][data-dirty=true]') && !confirm('Ci sono modifiche non salvate nell’ordine di servizio. Vuoi continuare?')) {
        event.preventDefault(); event.stopImmediatePropagation();
    }
}, true);
function boot() {
    for (const [root, client] of clients) {
        if (!root.isConnected) { client.disconnect(); clients.delete(root); }
    }
    for (const root of document.querySelectorAll('[data-service-orders-live]')) {
        if (clients.has(root)) continue;
        const config = JSON.parse(root.dataset.config);
        const label = root.querySelector('[data-order-connection]');
        if (!config.enabled || !config.key) { label.textContent = 'Live ordini da configurare'; continue; }
        const owner = root.closest('[wire\\:id]');
        const wire = owner && window.Livewire?.find(owner.getAttribute('wire:id'));
        if (!wire) continue;
        const client = new Pusher(config.key, {
            cluster: 'mt1', wsHost: config.host, wsPort: config.port, wssPort: config.port, wsPath: config.path,
            forceTLS: config.tls, enabledTransports: ['ws', 'wss'], enableStats: false,
            channelAuthorization: { endpoint: config.authEndpoint, transport: 'ajax', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } },
        });
        let ready = false, pending = false;
        async function refresh() {
            if (!root.isConnected) return;
            if (pending) { ready = true; return; }
            pending = true;
            try { if (root.dataset.mode === 'list') await wire.$refresh(); else await wire.$call('remoteUpdate'); }
            catch { label.textContent = 'Aggiornamento ordini non riuscito: ricarica la pagina'; }
            finally { pending = false; if (ready) { ready = false; refresh(); } }
        }
        client.connection.bind('state_change', ({current}) => {
            if (current !== 'connected') { root.dataset.connected = 'false'; label.textContent = 'Live ordini: riconnessione in corso…'; }
        });
        const channel = client.subscribe('private-' + root.dataset.channel);
        channel.bind('pusher:subscription_succeeded', () => { root.dataset.connected = 'true'; label.textContent = 'Live ordini connesso'; refresh(); });
        channel.bind('pusher:subscription_error', () => { label.textContent = 'Accesso live ordini non riuscito'; });
        channel.bind('service-order.changed', () => {
            const notice = root.querySelector('[data-order-notice]'); notice.hidden = false;
            notice.textContent = 'Ordini aggiornati alle ' + new Date().toLocaleTimeString('it-IT', {timeZone:'Europe/Rome'}) + '. Nella lista trovi gli ultimi ordini ricevuti e confermati.';
            refresh();
        });
        clients.set(root, client);
    }
}
document.addEventListener('livewire:initialized', boot);
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:navigating', () => { for (const client of clients.values()) client.disconnect(); clients.clear(); });
window.addEventListener('pagehide', () => { for (const client of clients.values()) client.disconnect(); clients.clear(); });
window.addEventListener('pageshow', boot);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once: true}); else boot();
