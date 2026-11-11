import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const controllers = new Map();
const el = (tag, className = '', text = '') => {
    const node = document.createElement(tag);
    node.className = className;
    node.textContent = text;
    return node;
};
const text = (node, value) => { if (node.textContent !== value) node.textContent = value; };
const icon = kind => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    for (const [key, value] of Object.entries({ viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '1.7', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true' })) svg.setAttribute(key, value);
    const path = document.createElementNS(svg.namespaceURI, 'path');
    path.setAttribute('d', kind === 'vehicle'
        ? 'M5 17H3V9l2-5h14l2 5v8h-2M5 17h14M3 9h18M7 13h.01M17 13h.01M5 17v3h3v-3M16 17v3h3v-3'
        : 'M3 21h18M5 21V3h14v18M9 21v-5h6v5M9 7h.01M15 7h.01M9 11h.01M15 11h.01');
    svg.append(path);
    return svg;
};
const duration = ms => {
    const seconds = Math.max(0, Math.ceil(ms / 1000));
    const days = Math.floor(seconds / 86400);
    const clock = [Math.floor(seconds / 3600) % 24, Math.floor(seconds / 60) % 60, seconds % 60].map(n => String(n).padStart(2, '0')).join(':');
    return (days ? `${days}g ` : '') + clock;
};

class OccupancyLive {
    constructor(root, wire) {
        this.root = root;
        this.wire = wire;
        this.config = JSON.parse(root.dataset.config);
        this.cards = [];
        this.groups = [];
        this.disposed = false;
        this.synced = false;
        this.subscribed = false;
        this.inflight = false;
        this.pending = false;
        this.connection = root.querySelector('[data-connection]');
        this.warning = root.querySelector('[data-warning]');
        this.updated = root.querySelector('[data-updated]');
        this.container = root.querySelector('[data-resources]');
        this.apply(JSON.parse(root.dataset.snapshot));
        this.timer = setInterval(() => this.tick(), 250); // Solo animazione locale, nessuna richiesta HTTP.
        this.onVisible = () => { if (!document.hidden && this.config.enabled) this.refresh(); };
        this.onOnline = () => { this.pusher?.connect(); if (this.config.enabled) this.refresh(); };
        document.addEventListener('visibilitychange', this.onVisible);
        window.addEventListener('online', this.onOnline);
        this.connect();
    }

    now() { return this.serverBase + performance.now() - this.monotonicBase; }

    apply(snapshot, latency = 0) {
        if (!snapshot || !Array.isArray(snapshot.resources) || !Number.isFinite(snapshot.serverNow)) throw new Error('Snapshot non valido');
        this.serverBase = snapshot.serverNow + latency / 2;
        this.monotonicBase = performance.now();
        this.timezone = snapshot.timezone || 'Europe/Rome';
        text(this.updated, 'Allineato alle ' + new Intl.DateTimeFormat('it-IT', { timeZone: this.timezone, hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date(snapshot.serverNow)));
        this.container.replaceChildren(); this.cards = []; this.groups = [];
        for (const [kind, title] of [['vehicle', 'Mezzi'], ['room', 'Stanze']]) {
            const resources = snapshot.resources.filter(r => r.kind === kind);
            const group = el('section', 'ol-group');
            const header = el('div', 'ol-group-heading');
            const count = el('span', 'ol-group-count');
            const heading = el('h3'); heading.append(icon(kind), document.createTextNode(title));
            header.append(heading, count); group.append(header);
            const grid = el('div', 'ol-cards'); group.append(grid);
            const cards = [];
            for (const resource of resources) {
                const card = el('article', 'ol-card'); card.dataset.resource = resource.key;
                const top = el('div', 'ol-card-top');
                const identity = el('div', 'ol-identity');
                const symbol = el('span', 'ol-icon'); symbol.append(icon(kind));
                identity.append(el('span', 'ol-kind', kind === 'vehicle' ? 'MEZZO' : 'STANZA'), el('h4', 'ol-name', resource.name), el('p', 'ol-detail', resource.detail));
                const badge = el('span', 'ol-state'); top.append(symbol, identity, badge); card.append(top);
                if (!resource.enabled) card.append(el('p', 'ol-disabled', 'Mezzo disattivato: impegni ancora presenti'));
                const activeContainer = el('div', 'ol-current'); card.append(activeContainer);
                const empty = el('p', 'ol-free', 'Nessun impegno in corso'); card.append(empty);
                const nextContainer = el('div', 'ol-next'); card.append(nextContainer);
                const future = el('details', 'ol-future');
                const summary = el('summary'); const futureContainer = el('div'); future.append(summary, futureContainer); card.append(future);
                const overlap = el('p', 'ol-overlap', 'Attenzione: prenotazioni sovrapposte'); card.append(overlap);
                const rows = [...resource.rows].sort((a, b) => a.start - b.start || a.end - b.end).map(row => {
                    const item = el('div', 'ol-booking');
                    // Solo URL relativi prodotti dal server del gestionale.
                    const parsed = row.url ? new URL(row.url, window.location.origin) : null;
                    const label = parsed?.origin === window.location.origin ? el('a', 'ol-case', row.label) : el('span', 'ol-case', row.label);
                    if (label.tagName === 'A') label.href = parsed.href;
                    const countdown = el('div', 'ol-countdown');
                    const countdownLabel = el('span', 'ol-countdown-label');
                    const countdownTime = el('strong', 'ol-countdown-time');
                    countdown.append(countdownLabel, countdownTime);
                    item.append(label, el('p', 'ol-period', row.startLabel + ' — ' + row.endLabel));
                    if (row.director) item.append(el('p', 'ol-director', 'Direttore tecnico: ' + row.director));
                    item.append(countdown);
                    const track = el('div', 'ol-track'); track.setAttribute('role', 'progressbar'); track.setAttribute('aria-label', 'Avanzamento impegno'); track.setAttribute('aria-valuemin', '0'); track.setAttribute('aria-valuemax', '100');
                    const bar = el('div', 'ol-bar'); track.append(bar); item.append(track);
                    return { ...row, item, countdownLabel, countdownTime, track, bar, position: null };
                });
                const state = { resource, rows, badge, card, activeContainer, nextContainer, futureContainer, future, summary, empty, overlap };
                cards.push(state); this.cards.push(state); grid.append(card);
            }
            if (!resources.length) {
                const placeholder = el('div', 'ol-placeholder'); placeholder.append(icon(kind), el('strong', '', kind === 'room' ? 'Nessuna stanza inserita' : 'Nessun mezzo inserito'), el('p', '', 'Le risorse aggiunte compariranno qui con disponibilità e impegni in tempo reale.'));
                grid.append(placeholder);
            }
            this.groups.push({ count, cards, grid, order: [] }); this.container.append(group);
        }
        this.tick();
    }

    tick() {
        if (this.disposed) return;
        const now = this.now();
        for (const c of this.cards) {
            const active = c.rows.filter(r => r.start <= now && now < r.end);
            const upcoming = c.rows.filter(r => now < r.start);
            const stillBooked = c.rows.filter(r => now < r.end);
            let maxEnd = 0; let overlapping = false;
            for (const r of stillBooked) { if (r.start < maxEnd) overlapping = true; maxEnd = Math.max(maxEnd, r.end); }
            c.rank = active.length ? 0 : upcoming.length ? 1 : 2;
            c.sortTime = active.length ? Math.min(...active.map(r => r.end)) : upcoming.length ? upcoming[0].start : Infinity;
            const state = active.length > 1 ? 'conflict' : active.length ? 'busy' : !c.resource.enabled ? 'disabled' : upcoming.length ? 'reserved' : 'free';
            text(c.badge, !this.synced ? 'Da verificare' : state === 'conflict' ? 'Sovrapposizione' : state === 'busy' ? 'Occupato' : state === 'disabled' ? 'Disattivato' : state === 'reserved' ? 'Prenotato' : 'Disponibile');
            c.card.dataset.state = this.synced ? state : 'unknown';
            c.empty.hidden = active.length > 0 || upcoming.length > 0;
            const preview = active.length ? null : upcoming[0];
            const otherUpcoming = upcoming.length - (preview ? 1 : 0);
            c.future.hidden = otherUpcoming === 0;
            text(c.summary, `${otherUpcoming} ${otherUpcoming === 1 ? 'altro impegno in programma' : 'altri impegni in programma'}`);
            c.overlap.hidden = !overlapping;
            for (const r of c.rows) {
                const position = now >= r.end ? 'expired' : now < r.start ? (r === preview ? 'next' : 'future') : 'current';
                if (r.position !== position) {
                    r.item.hidden = position === 'expired';
                    if (position === 'future') c.futureContainer.append(r.item);
                    if (position === 'next') c.nextContainer.append(r.item);
                    if (position === 'current') c.activeContainer.append(r.item);
                    r.position = position;
                }
                if (position === 'expired') { r.item.hidden = true; continue; }
                const percent = Math.max(0, Math.min(100, (now - r.start) / Math.max(1, r.end - r.start) * 100));
                text(r.countdownLabel, position === 'current' ? 'Termina tra' : 'Inizia tra');
                text(r.countdownTime, duration((position === 'current' ? r.end : r.start) - now));
                r.track.hidden = position !== 'current';
                r.track.setAttribute('aria-valuenow', String(Math.round(percent)));
                r.bar.style.width = `${percent.toFixed(3)}%`;
            }
        }
        for (const group of this.groups) {
            const busy = group.cards.filter(c => c.rank === 0).length;
            const reserved = group.cards.filter(c => c.rank === 1).length;
            const free = group.cards.filter(c => c.rank === 2 && c.resource.enabled).length;
            text(group.count, `${busy} in corso · ${reserved} in programma · ${free} disponibili`);
            const sorted = [...group.cards].sort((a, b) => a.rank - b.rank || a.sortTime - b.sortTime || a.resource.name.localeCompare(b.resource.name, 'it', { numeric: true }) || a.resource.key.localeCompare(b.resource.key));
            const order = sorted.map(c => c.resource.key);
            if (order.some((key, index) => key !== group.order[index])) {
                // Sposta solo le schede fuori posto; conserva focus e dettagli aperti.
                sorted.forEach((c, index) => {
                    if (group.grid.children[index] !== c.card) group.grid.insertBefore(c.card, group.grid.children[index] || null);
                });
                group.order = order;
            }
        }
    }

    status(label, warning = '') {
        text(this.connection, label);
        this.connection.dataset.live = this.synced ? 'true' : 'false';
        this.warning.hidden = !warning;
        text(this.warning, warning);
        this.tick();
    }

    connect() {
        if (!this.config.enabled || !this.config.key || !this.config.host) {
            this.status('Realtime da configurare', 'La connessione WebSocket non è attiva. Gli orari mostrati sono quelli caricati all’apertura.');
            return;
        }
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            this.pusher = new Pusher(this.config.key, {
                cluster: 'mt1', wsHost: this.config.host, wsPort: this.config.port, wssPort: this.config.port,
                wsPath: this.config.path, forceTLS: this.config.tls, enabledTransports: ['ws', 'wss'], enableStats: false,
                channelAuthorization: { endpoint: this.config.authEndpoint, transport: 'ajax', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } },
            });
            this.echo = new Echo({ broadcaster: 'reverb', client: this.pusher });
            this.pusher.connection.bind('state_change', ({ current }) => {
                if (this.disposed) return;
                if (current !== 'connected') {
                    this.subscribed = false; this.synced = false;
                    this.status(current === 'connecting' ? 'Connessione in corso…' : 'Live disconnesso', 'Aggiornamenti non sincronizzati. Riconnessione automatica in corso; nessun controllo periodico al server.');
                }
            });
            this.echo.private('occupancy')
                .subscribed(() => { if (this.disposed) return; this.subscribed = true; this.refresh(); })
                .listen('.occupancy.changed', () => {
                    if (this.disposed) return;
                    clearTimeout(this.debounce);
                    this.debounce = setTimeout(() => this.refresh(), 120);
                })
                .error(() => { if (this.disposed) return; this.subscribed = false; this.synced = false; this.status('Accesso realtime non riuscito', 'Verifica la sessione del gestionale e la connessione WebSocket.'); });
        } catch {
            this.synced = false; this.status('Realtime non disponibile', 'Impossibile avviare la connessione WebSocket.');
        }
    }

    async refresh() {
        if (this.disposed) return;
        if (this.inflight) { this.pending = true; return; }
        this.inflight = true;
        const began = performance.now();
        try {
            const snapshot = await this.wire.$call('snapshot');
            if (this.disposed) return;
            this.synced = this.subscribed && this.pusher?.connection.state === 'connected';
            this.apply(snapshot, performance.now() - began);
            this.status(this.synced ? 'Live connesso' : 'Live non connesso', this.synced ? '' : 'Dati aggiornati ora. Le modifiche degli altri operatori non sono sincronizzate finché la connessione non torna attiva.');
        } catch {
            if (!this.disposed) { this.synced = false; this.status('Allineamento non riuscito', 'Verifica connessione e sessione. La disponibilità visualizzata potrebbe non essere aggiornata.'); }
        } finally {
            this.inflight = false;
            if (this.pending && !this.disposed) { this.pending = false; this.refresh(); }
        }
    }

    dispose() {
        this.disposed = true;
        clearInterval(this.timer); clearTimeout(this.debounce);
        document.removeEventListener('visibilitychange', this.onVisible);
        window.removeEventListener('online', this.onOnline);
        this.echo?.disconnect(); controllers.delete(this.root);
    }
}

function boot() {
    if (!window.Livewire) return;
    for (const [root, controller] of controllers) if (!root.isConnected) controller.dispose();
    for (const root of document.querySelectorAll('[data-occupancy-live]')) {
        if (controllers.has(root)) continue;
        const owner = root.closest('[wire\\:id]');
        const wire = owner && window.Livewire.find(owner.getAttribute('wire:id'));
        if (wire) controllers.set(root, new OccupancyLive(root, wire));
    }
}
let hooksBound = false;
function initialize() {
    if (window.Livewire && !hooksBound) {
        hooksBound = true;
        window.Livewire.hook('component.init', ({ component, cleanup }) => {
            queueMicrotask(boot);
            cleanup(() => { for (const [root, controller] of controllers) if (component.el?.contains(root)) controller.dispose(); });
        });
        window.Livewire.hook('morph.added', ({ el: added }) => { if (added?.matches?.('[data-occupancy-live]') || added?.querySelector?.('[data-occupancy-live]')) queueMicrotask(boot); });
    }
    boot();
}
document.addEventListener('livewire:init', initialize);
document.addEventListener('livewire:initialized', initialize);
document.addEventListener('livewire:navigated', initialize);
document.addEventListener('livewire:navigating', () => { for (const controller of [...controllers.values()]) controller.dispose(); });
window.addEventListener('pagehide', () => { for (const controller of [...controllers.values()]) controller.dispose(); });
window.addEventListener('pageshow', initialize);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
