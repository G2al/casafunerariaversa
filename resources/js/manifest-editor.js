import { Canvas, Textbox, FabricImage, Rect, Ellipse, Triangle, controlsUtils, ActiveSelection } from 'fabric';

const W = 841.89, H = 595.28;
const labels = { first_name: 'Nome', last_name: 'Cognome', intro: 'Introduzione', name: 'Nome e cognome', age: 'Età', family: 'Annuncio familiari', viewing: 'Camera ardente', transfer: 'Trasferimento', ceremony: 'Esequie', closing: 'Ringraziamento', date: 'Luogo e data', agency: 'Firma agenzia', photo: 'Fotografia', logo: 'Logo' };

async function start(root) {
    if (root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const $ = selector => root.querySelector(selector);
    const status = (message, error = false) => { $('[data-status]').textContent = message; $('[data-status]').dataset.error = String(error); };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let data, busy = false, dirty = false, restoring = false, history = [], position = -1, timer, currentBackground = null, freshAssets = new Set();
    const canvas = new Canvas($('[data-canvas]'), { width: W, height: H, backgroundColor: '#ffffff', preserveObjectStacking: true, selection: true, selectionKey: ['shiftKey'], enableRetinaScaling: false, uniformScaling: true, uniScaleKey: null });
    root.querySelectorAll('[data-command]').forEach(button => button.disabled = true);
    const objects = () => canvas.getObjects().filter(object => object.editorId);
    const active = () => canvas.getActiveObject();
    let fields = {};
    const isGroup = object => object instanceof ActiveSelection;
    const selectMany = members => { if (members.length > 1) canvas.setActiveObject(new ActiveSelection(members, {canvas, lockRotation: true, lockScalingFlip:true})); else if (members[0]) canvas.setActiveObject(members[0]); };

    async function request(url, options = {}) {
        const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) } });
        const result = await response.json().catch(() => ({}));
        if (! response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || `Operazione non riuscita (${response.status}).`);
        return result;
    }

    function lock(object, value) {
        object.set({ editorLocked: value, lockMovementX: value, lockMovementY: value, lockScalingX: value, lockScalingY: value, editable: !value, hasControls: !value, selectable: !value, evented: !value });
    }

    function clamp(object) {
        object.set({ left: Math.max(0, Math.min(object.left, W - object.getScaledWidth())), top: Math.max(0, Math.min(object.top, H - object.getScaledHeight())) });
        object.setCoords();
    }

    function serialize() {
        return { version: 1, ...(currentBackground ? { backgroundAsset: currentBackground } : {}), elements: objects().map(object => {
            const scale = object.getObjectScaling(), point = object.getXY();
            return {
                id: object.editorId, type: object.editorType || (object.editorAsset ? 'image' : 'text'),
                ...(object.editorAsset ? {asset: object.editorAsset} : object.editorType === 'text' ? {text: object.text, stretchY: +(scale.y / scale.x).toFixed(6)} : {fill: object.fill || 'transparent', stroke: object.stroke || '#000000', strokeWidth: object.strokeWidth || 0}),
                x: +point.x.toFixed(3), y: +point.y.toFixed(3), width: +(object.width * scale.x).toFixed(3), height: +(object.height * scale.y).toFixed(3),
                fontSize: object.editorType === 'text' ? +(object.fontSize * scale.x).toFixed(4) : 12,
                fontFamily: object.fontFamily || 'DejaVu Serif', bold: object.fontWeight === 'bold', italic: object.fontStyle === 'italic',
                color: typeof object.fill === 'string' && /^#[a-f\d]{6}$/i.test(object.fill) ? object.fill : '#000000', align: object.textAlign || 'center', lineHeight: object.lineHeight || 1.5, locked: !!object.editorLocked,
            };
        }) };
    }

    function populateFields() {
        const section = $('[data-linked-fields]');
        if (!section) return;
        section.hidden = !data.fields;
        fields = {...(data.fields || {})};
        section.querySelectorAll('[data-field]').forEach(input => input.value = (input.type === 'datetime-local' ? String(fields[input.dataset.field] || '').replace(' ', 'T').slice(0,16) : input.type === 'date' ? String(fields[input.dataset.field] || '').slice(0,10) : fields[input.dataset.field] || ''));
    }

    function syncTextFields() {
        const heading = $('[data-manifest-name]'); if (heading && data?.fields) heading.textContent = [fields.first_name, fields.last_name].filter(Boolean).join(' ');
        for (const object of objects()) {
            if (['first_name', 'last_name'].includes(object.editorId)) fields[object.editorId] = object.text;
            const input = root.querySelector(`[data-block="${object.editorId}"]`);
            if (input && document.activeElement !== input) input.value = object.text || '';
        }
        root.querySelectorAll('[data-field]').forEach(input => { if (document.activeElement !== input) input.value = input.type === 'datetime-local' ? String(fields[input.dataset.field] || '').replace(' ','T').slice(0,16) : fields[input.dataset.field] || ''; });
    }

    function updateText(id, value) {
        let object = objects().find(o => o.editorId === id);
        if (!object) {
            const slots = {first_name:[75,29],last_name:[115,29],intro:[35,15], age:[166,17], spouse:[140,13], family:[199,17], viewing:[260,16], transfer:[340,15], ceremony:[390,16], closing:[455,14], date:[500,10]};
            const [top, fontSize] = slots[id] || [200,18];
            const standard = data.automatic?.elements.find(e => e.type === 'text');
            object = new Textbox('', {left: standard?.x || 240, top, width: standard?.width || 560, fontSize, fontFamily:'DejaVu Serif', textAlign:'center', lineHeight:1.5, originX:'left', originY:'top', strokeWidth:0, editorId:id, editorType:'text', lockRotation:true});
            object.controls = controlsUtils.createTextboxDefaultControls();
            for (const corner of ['tl','tr','bl','br']) {
                object.controls[corner].actionHandler = controlsUtils.scalingEqually;
                object.controls[corner].cursorStyleHandler = () => ['tl','br'].includes(corner) ? 'nwse-resize' : 'nesw-resize';
            }
            for (const side of ['mt','mb']) {
                object.controls[side].actionHandler = controlsUtils.scalingY;
                object.controls[side].getActionName = () => 'scaleY';
                object.controls[side].cursorStyleHandler = () => 'ns-resize';
            }
            object.setControlsVisibility({mtr:false,mt:true,mb:true}); canvas.add(object);
        }
        object.set('text', value); object.initDimensions(); clamp(object);
    }

    function fieldChanged(input) {
        fields[input.dataset.field] = input.value;
        const f = fields, key = input.dataset.field;
        if (['first_name','last_name'].includes(key)) {
            updateText(key, input.value);
            const legacyName = objects().find(o => o.editorId === 'name');
            if (legacyName) canvas.remove(legacyName);
            for (const name of ['first_name','last_name']) if (!objects().some(o => o.editorId === name)) updateText(name, f[name] || '');
        }
        if (['birth_date','death_date'].includes(key)) {
            const b = f.birth_date, d = f.death_date;
            const age = b && d ? Number(d.slice(0,4)) - Number(b.slice(0,4)) - (d.slice(5) < b.slice(5) ? 1 : 0) : null;
            updateText('age', age === null || age < 0 ? '' : `DI ANNI ${age}`);
        }
        if (['spouse_name','marital_status'].includes(key)) updateText('spouse', [f.marital_status, f.spouse_name].filter(Boolean).join(' '));
        const format = value => value ? new Intl.DateTimeFormat('it-IT',{dateStyle:'long', timeStyle:'short'}).format(new Date(value.replace(' ','T'))) : '';
        if (key.startsWith('viewing_')) updateText('viewing', [f.viewing_venue && `La camera ardente è allestita presso ${f.viewing_venue}`,f.viewing_address,f.viewing_starts_at && `dal ${format(f.viewing_starts_at)}`,f.viewing_ends_at && `fino al ${format(f.viewing_ends_at)}`].filter(Boolean).join(', '));
        if (['church','ceremony_city','ceremony_at'].includes(key)) updateText('ceremony', ['Il rito funebre si terrà',format(f.ceremony_at),f.church && `presso ${f.church}`,f.ceremony_city].filter(Boolean).join(' '));
        if (['transfer_place','transport_at'].includes(key)) updateText('transfer', f.transfer_place ? `Il trasferimento avverrà presso ${f.transfer_place} ${format(f.transport_at)}` : '');
        if (key === 'sex') updateText('intro', input.value === 'female' ? 'È venuta a mancare all’affetto dei suoi cari la Signora' : 'È venuto a mancare all’affetto dei suoi cari il Signor');
        canvas.requestRenderAll(); changed();
    }

    function fit() {
        const value = $('[data-zoom]').value;
        const zoom = value === 'fit' ? Math.min(1.3, Math.max(0.2, ($('.me-scroll').clientWidth - 36) / W)) : Number(value);
        canvas.setDimensions({ width: W * zoom, height: H * zoom });
        canvas.setZoom(zoom);
        canvas.requestRenderAll();
    }

    function selection() {
        const object = active();
        const select = $('[data-element]');
        select.replaceChildren(new Option('Seleziona sul foglio', ''), ...objects().map(o => new Option((o.editorLocked ? '🔒 ' : '') + (labels[o.editorId] || (o.editorAsset ? (labels[o.editorAsset] || [...data.catalog.symbols, ...data.catalog.uploads].find(item => item.key === o.editorAsset)?.title || 'Immagine') : ({ rect: 'Rettangolo', ellipse: 'Cerchio / ovale', triangle: 'Triangolo' }[o.editorType] || (o.text || 'Testo').slice(0, 28)))), o.editorId)));
        select.value = object?.editorId || '';
        $('[data-properties]').disabled = !object;
        $('[data-selection-hint]').hidden = !!object;
        root.querySelectorAll('[data-text-control]').forEach(node => node.hidden = object?.editorType !== 'text');
        root.querySelectorAll('[data-shape-control]').forEach(node => node.hidden = !object || !['rect','ellipse','triangle'].includes(object.editorType));
        if (!object) { $('[data-selection-hint]').textContent = 'Seleziona un elemento. Maiusc + clic per selezionarne più di uno.'; return; }
        if (isGroup(object)) {
            object.set({lockRotation:true, lockScalingFlip:true}); object.setControlsVisibility({mtr:false});
            $('[data-selection-hint]').hidden = false;
            $('[data-selection-hint]').textContent = `${object.getObjects().length} elementi selezionati. Trascina per spostarli o usa gli angoli per ridimensionarli.`;
            root.querySelectorAll('[data-property]').forEach(input => input.disabled = true);
            $('[data-command="full-page"]').disabled = true;
            return;
        }
        root.querySelectorAll('[data-property]').forEach(input => input.disabled = false);
        const element = serialize().elements.find(item => item.id === object.editorId);
        root.querySelectorAll('[data-property]').forEach(input => {
            if (document.activeElement === input) return;
            if (input.type === 'checkbox') input.checked = input.dataset.property === 'transparent' ? element.fill === 'transparent' : !!element[input.dataset.property];
            else input.value = input.type === 'color' && element[input.dataset.property] === 'transparent' ? '#ffffff' : element[input.dataset.property] ?? '';
        });
        $('[data-property="height"]').disabled = false;
        $('[data-command="full-page"]').disabled = object.editorType === 'text';
        $('[data-command="undo"]').disabled = position <= 0;
        $('[data-command="redo"]').disabled = position >= history.length - 1;
    }

    function changed() {
        if (restoring) return;
        syncTextFields();
        const json = JSON.stringify({scene: serialize(), fields});
        if (history[position] !== json) {
            history = history.slice(0, position + 1);
            history.push(json);
            if (history.length > 50) history.shift();
            position = history.length - 1;
        }
        dirty = true;
        status('Modifiche da salvare');
        selection();
    }

    async function load(scene) {
        restoring = true;
        canvas.clear(); canvas.backgroundColor = '#ffffff';
        currentBackground = scene.backgroundAsset || null;
        if (currentBackground) $('[data-frame]').value = currentBackground;
        const backgroundUri = data.assets[currentBackground || 'background'];
        if (backgroundUri) {
            const background = await FabricImage.fromURL(backgroundUri);
            const scale = Math.min(813.55 / background.width, 566.94 / background.height);
            background.set({ originX: 'left', originY: 'top', left: 14.17 + (813.55 - background.width * scale) / 2, top: 14.17, scaleX: scale, scaleY: scale, selectable: false, evented: false, strokeWidth: 0 });
            canvas.add(background);
        }
        for (const item of scene.elements) {
            let object;
            if (item.type === 'image') {
                if (!data.assets[item.asset]) continue;
                object = await FabricImage.fromURL(data.assets[item.asset]);
                object.set({ scaleX: item.width / object.width, scaleY: item.height / object.height, editorAsset: item.asset });
            } else if (item.type !== 'text') {
                const Klass = { rect: Rect, ellipse: Ellipse, triangle: Triangle }[item.type];
                object = new Klass({ width: item.width, height: item.height, ...(item.type === 'ellipse' ? { rx: item.width / 2, ry: item.height / 2 } : {}), fill: item.fill || 'transparent', stroke: item.stroke || '#000000', strokeWidth: item.strokeWidth ?? 1, strokeUniform: true });
            } else {
                object = new Textbox(item.text || '', { scaleY: item.stretchY ?? 1, width: item.width, fontSize: item.fontSize, fontFamily: item.fontFamily, fontWeight: item.bold ? 'bold' : 'normal', fontStyle: item.italic ? 'italic' : 'normal', fill: item.color, textAlign: item.align, lineHeight: item.lineHeight, splitByGrapheme: false });
            }
            object.set({ originX: 'left', originY: 'top', left: item.x, top: item.y, editorId: item.id, editorType: item.type, ...(item.type === 'text' || item.type === 'image' ? { strokeWidth: 0 } : {}), lockRotation: true, cornerColor: '#b45309', borderColor: '#b45309', cornerSize: 11, transparentCorners: false });
            object.setControlsVisibility({ mtr: false });
            if (item.type === 'text') {
                object.controls = controlsUtils.createTextboxDefaultControls();
                object.setControlsVisibility({ mtr: false, mt: true, mb: true });
                for (const corner of ['tl','tr','bl','br']) {
                    const control = object.controls[corner];
                    control.actionHandler = controlsUtils.scalingEqually;
                    // Fabric's automatic cursor follows the box aspect ratio and
                    // becomes horizontal on wide text: keep corners unambiguous.
                    control.cursorStyleHandler = () => ['tl','br'].includes(corner) ? 'nwse-resize' : 'nesw-resize';
                    control.sizeX = control.sizeY = 13;
                    control.touchSizeX = control.touchSizeY = 28;
                }
                for (const side of ['mt','mb']) {
                    object.controls[side].actionHandler = controlsUtils.scalingY;
                    object.controls[side].getActionName = () => 'scaleY';
                    object.controls[side].cursorStyleHandler = () => 'ns-resize';
                    object.controls[side].touchSizeX = object.controls[side].touchSizeY = 28;
                }
                for (const side of ['ml','mr']) object.controls[side].cursorStyleHandler = () => 'ew-resize';
            }
            lock(object, item.locked);
            canvas.add(object);
        }
        restoring = false;
        fit(); selection(); syncTextFields();
    }

    function folderImages() {
        const folder = $('[data-symbol-folder]').value;
        return folder === 'uploads' ? data.catalog.uploads : data.catalog.symbols.filter(item => String(item.folder) === folder);
    }

    function library() {
        const items = folderImages(), previous = $('[data-library]').value;
        $('[data-library]').replaceChildren(new Option(items.length ? 'Seleziona una immagine' : 'Nessuna immagine nella cartella', ''), ...items.map(item => new Option(item.title, item.key)));
        if (items.some(item => item.key === previous)) $('[data-library]').value = previous;
        const gallery = $('[data-symbol-gallery]'); gallery.replaceChildren();
        for (const item of items) {
            const button = document.createElement('button'); button.type = 'button'; button.title = item.title; button.dataset.symbolKey = item.key;
            button.setAttribute('aria-label', item.title); button.setAttribute('aria-pressed', String(item.key === $('[data-library]').value));
            const img = document.createElement('img'); img.src = item.url; img.alt = item.title; img.loading = 'lazy';
            const caption = document.createElement('span'); caption.textContent = item.title;
            button.append(img, caption); button.addEventListener('click', () => { if (busy) return; $('[data-library]').value = item.key; highlightLibrary(); }); gallery.append(button);
        }
        if (!items.length) { const hint = document.createElement('p'); hint.textContent = 'Nessuna immagine disponibile in questa cartella.'; gallery.append(hint); }
    }

    function highlightLibrary() {
        root.querySelectorAll('[data-symbol-key]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.symbolKey === $('[data-library]').value)));
    }

    function catalogs() {
        for (const group of Object.values(data.catalog)) for (const item of group) data.assets[item.key] ??= item.url;
        const previousFolder = $('[data-symbol-folder]').value;
        const folders = data.symbolFolders || [];
        $('[data-symbol-folder]').replaceChildren(...folders.map(item => new Option(item.name, String(item.id))), new Option('Immagini della pratica', 'uploads'));
        const initial = folders.find(folder => data.catalog.symbols.some(item => item.folder === folder.id));
        $('[data-symbol-folder]').value = [...$('[data-symbol-folder]').options].some(option => option.value === previousFolder) ? previousFolder : String(initial?.id ?? folders[0]?.id ?? 'uploads');
        library();
        $('[data-frame]').replaceChildren(new Option('Seleziona cornice', ''), new Option('Senza cornice', 'none'), ...data.catalog.frames.map(item => new Option(item.title, item.key)));
    }

    async function addImage(asset) {
        if (!asset || !data.assets[asset]) throw new Error('Seleziona un simbolo o una immagine.');
        const scene = serialize(); if (scene.elements.length >= 40) throw new Error('Massimo 40 elementi.');
        const current = [...data.catalog.symbols, ...data.catalog.uploads].find(item => item.key === asset);
        if (current) { data.assets[asset] = current.url; freshAssets.add(asset); }
        const image = await FabricImage.fromURL(data.assets[asset]);
        const scale = Math.min(160 / image.width, 160 / image.height);
        scene.elements.push({ id: `extra_${crypto.randomUUID().slice(0,12)}`, type: 'image', asset, x: 300, y: 200, width: image.width * scale, height: image.height * scale, fontSize: 12, fontFamily: 'DejaVu Serif', bold: false, italic: false, color: '#000000', align: 'center', lineHeight: 1.5, locked: false });
        await load(scene); canvas.setActiveObject(objects().at(-1)); changed();
    }

    async function undo(delta) {
        if (position + delta < 0 || position + delta >= history.length) return;
        position += delta;
        const entry = JSON.parse(history[position]); fields = {...entry.fields}; await load(entry.scene); syncTextFields();
        dirty = true; status('Modifiche da salvare'); selection();
    }

    function property(input) {
        const object = active(); if (!object) return;
        const key = input.dataset.property;
        const value = input.type === 'checkbox' ? input.checked : input.type === 'number' ? Number(input.value) : input.value;
        if (key === 'transparent') object.set('fill', value ? 'transparent' : $('[data-property=fill]').value);
        else if (key === 'locked') lock(object, value);
        else if (key === 'x' || key === 'y') object.set(key === 'x' ? 'left' : 'top', value);
        else if (key === 'width') {
            if (object.editorAsset) object.scaleToWidth(Math.max(10, value));
            else if (object.editorType === 'text') object.set('width', Math.max(10, value));
            else object.set('scaleX', Math.max(10, value) / object.width);
        }
        else if (key === 'height') {
            if (object.editorAsset) object.scaleToHeight(Math.max(5, value));
            else if (object.editorType === 'text') object.set('scaleY', Math.min(10, Math.max(0.1, value / object.height)));
            else object.set('scaleY', Math.max(0.5, value) / object.height);
        }
        else object.set(({ bold: 'fontWeight', italic: 'fontStyle', color: 'fill', align: 'textAlign' })[key] || key, key === 'bold' ? (value ? 'bold' : 'normal') : key === 'italic' ? (value ? 'italic' : 'normal') : value);
        if (object.editorType === 'text') object.initDimensions();
        clamp(object); canvas.requestRenderAll();
        clearTimeout(timer); timer = setTimeout(changed, 250);
    }

    function imageExport() {
        const scene = serialize();
        for (const element of scene.elements) {
            if (element.x + element.width > W + 0.1 || element.y + element.height > H + 0.1 || element.fontSize < 6 || element.fontSize > 100) throw new Error('Un elemento supera il foglio o la dimensione consentita. Ridimensionalo prima di salvare.');
        }
        const selected = canvas.getActiveObjects(), zoom = canvas.getZoom();
        canvas.discardActiveObject();
        canvas.setDimensions({ width: W, height: H }); canvas.setZoom(1);
        const exported = canvas.toCanvasElement(3508 / W);
        const output = document.createElement('canvas'); output.width = 3508; output.height = 2480;
        output.getContext('2d').drawImage(exported, 0, 0, 3508, 2480);
        const image = output.toDataURL('image/jpeg', 0.97);
        canvas.setDimensions({ width: W * zoom, height: H * zoom }); canvas.setZoom(zoom);
        selectMany(selected);
        canvas.requestRenderAll();
        return { scene, image };
    }

    async function save(generate = false) {
        clearTimeout(timer);
        if (data.stale) throw new Error('I dati della pratica sono cambiati. Usa Aggiorna dalla pratica prima di salvare.');
        const payload = imageExport();
        const result = await request(data.saveUrl, { method: 'POST', body: JSON.stringify({ ...payload, ...(data.fields ? {fields} : {}), freshAssets: [...freshAssets].filter(key => key === (payload.scene.backgroundAsset || 'background') || payload.scene.elements.some(item => item.asset === key)), source: data.source, revision: data.revision, generate }) });
        data.revision = result.revision; data.source = result.source || data.source; dirty = false; freshAssets.clear();
        $('[data-stale]').hidden = true;
        status(generate ? 'Composizione salvata e nuova versione PDF archiviata.' : 'Composizione salvata.');
        return result;
    }

    async function command(name) {
        if (busy) return;
        busy = true;
        canvas.upperCanvasEl.style.pointerEvents = 'none';
        root.querySelectorAll('input,textarea,select').forEach(input => input.disabled = true);
        const popup = ['generate', 'preview'].includes(name) ? window.open('', '_blank') : null;
        root.querySelectorAll('[data-command]').forEach(button => button.disabled = true);
        try {
            const object = active();
            if (name === 'save' || name === 'generate' || name === 'preview') {
                status('Salvataggio in corso…');
                const result = await save(name === 'generate');
                const url = name === 'generate' ? result.documentUrl : name === 'preview' ? data.previewUrl : null;
                if (url) { if (popup) popup.location = url; else window.location.href = url; }
            } else if (name === 'undo' || name === 'redo') await undo(name === 'undo' ? -1 : 1);
            else if (name === 'reset') {
                if (!confirm('Ripristinare il manifesto automatico? I PDF già archiviati resteranno conservati.')) return;
                await request(data.resetUrl, { method: 'DELETE', body: JSON.stringify({ revision: data.revision }) });
                data = await request(root.dataset.url);
                catalogs(); populateFields(); await load(data.scene); history = [JSON.stringify({scene: serialize(), fields})]; position = 0; dirty = false;
                $('[data-stale]').hidden = true; status('Disposizione automatica ripristinata.');
            } else if (name === 'refresh') {
                if (!confirm('Ricaricare testi, foto e cornice dalla pratica? Le modifiche ai testi personalizzati saranno sostituite; le posizioni dei blocchi comuni saranno mantenute.')) return;
                const current = serialize(); const updated = await request(root.dataset.url);
                if (!updated.automatic) throw new Error(updated.automaticError);
                const elements = updated.automatic.elements.map(item => {
                    const previous = current.elements.find(e => e.id === item.id);
                    return previous ? { ...previous, ...(item.type === 'text' ? { text: item.text } : { asset: item.asset }) } : item;
                });
                elements.push(...current.elements.filter(e => e.id.startsWith('extra_')));
                data = updated; populateFields(); data.assets = updated.currentAssets; catalogs(); freshAssets = new Set(Object.keys(data.assets)); data.source = updated.currentSource; data.stale = false; await load({ version: 1, ...(current.backgroundAsset ? { backgroundAsset: current.backgroundAsset } : {}), elements }); changed();
                $('[data-stale]').hidden = true;
            } else if (name === 'change-frame') {
                const key = $('[data-frame]').value;
                if (!key) throw new Error('Seleziona una cornice.');
                if (key !== 'none') { data.assets[key] = data.catalog.frames.find(item => item.key === key).url; freshAssets.add(key); }
                const scene = serialize(); scene.backgroundAsset = key; await load(scene); changed();
            } else if (name === 'upload' || name === 'upload-photo') {
                const file = $('[data-upload]').files[0]; if (!file) throw new Error('Seleziona prima una immagine JPEG, PNG o WebP.');
                const body = new FormData(); body.append('image', file);
                const response = await fetch(data.uploadUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body });
                const result = await response.json(); if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message);
                data.assets[result.key] = result.url; data.catalog.uploads.push(result); catalogs();
                $('[data-symbol-folder]').value = 'uploads'; library(); $('[data-library]').value = result.key; highlightLibrary(); await addImage(result.key);
                if (name === 'upload-photo') {
                    const photo = objects().at(-1);
                    const old = objects().find(o => o.editorId === 'photo' && o !== photo); if (old) canvas.remove(old);
                    photo.editorId = 'photo'; changed();
                }
            } else if (name === 'add-library') {
                await addImage($('[data-library]').value);
            } else if (name.startsWith('add-')) {
                const scene = serialize();
                if (scene.elements.length >= 40) throw new Error('Puoi inserire al massimo 40 elementi.');
                const shape = ['add-rect','add-ellipse','add-triangle'].includes(name) ? name.slice(4) : null;
                const asset = name === 'add-photo' ? 'photo' : name === 'add-logo' ? 'logo' : null;
                if (asset && !data.assets[asset]) throw new Error(asset === 'photo' ? 'Carica prima la foto nella pratica.' : 'Carica prima il logo nelle Impostazioni agenzia.');
                scene.elements.push({ id: `extra_${crypto.randomUUID().slice(0, 12)}`, type: shape || (asset ? 'image' : 'text'), ...(shape ? { fill: 'transparent', stroke: '#000000', strokeWidth: 1 } : {}), ...(asset ? { asset } : { text: 'Nuovo testo' }), x: W / 2 - 90, y: H / 2 - 30, width: asset ? 90 : 180, height: asset ? 90 : 30, fontSize: 20, fontFamily: 'DejaVu Serif', bold: false, italic: false, color: '#000000', align: 'center', lineHeight: 1.5, locked: false });
                if (asset) {
                    const image = await FabricImage.fromURL(data.assets[asset]);
                    scene.elements.at(-1).height = 90 * image.height / image.width;
                }
                await load(scene); canvas.setActiveObject(objects().at(-1)); changed();
            } else if (isGroup(object)) {
                const members = canvas.getActiveObjects(); canvas.discardActiveObject();
                if (name === 'delete') canvas.remove(...members);
                else {
                    if (name === 'center' || name === 'center-safe') {
                        const left = Math.min(...members.map(o=>o.left)), right = Math.max(...members.map(o=>o.left+o.getScaledWidth()));
                        const delta = (W - (right-left))/2 - left;
                        members.forEach(o=>{o.set('left',o.left+delta); clamp(o);});
                    }
                    if (['forward','backward','behind'].includes(name)) {
                        const ordered = name === 'forward' ? [...members].reverse() : members;
                        for (const o of ordered) canvas.moveObjectTo(o, name === 'behind' ? (data.assets[currentBackground || 'background'] ? 1 : 0) : Math.max(data.assets[currentBackground || 'background'] ? 1 : 0, Math.min(canvas.getObjects().length-1, canvas.getObjects().indexOf(o)+(name==='forward'?1:-1))));
                    }
                    selectMany(members);
                }
                changed(); canvas.requestRenderAll();
            } else if (object) {
                if (name === 'delete') { canvas.remove(object); canvas.discardActiveObject(); }
                if (name === 'forward') canvas.moveObjectTo(object, Math.min(canvas.getObjects().length - 1, canvas.getObjects().indexOf(object) + 1));
                if (name === 'backward') canvas.moveObjectTo(object, Math.max(data.assets[currentBackground || 'background'] ? 1 : 0, canvas.getObjects().indexOf(object) - 1));
                if (name === 'behind') canvas.moveObjectTo(object, data.assets[currentBackground || 'background'] ? 1 : 0);
                if (name === 'full-page' && object.editorType !== 'text') { object.set({ left: 0, top: 0, scaleX: (W - object.strokeWidth) / object.width, scaleY: (H - object.strokeWidth) / object.height }); }
                if (name === 'center') object.set('left', (W - object.getScaledWidth()) / 2);
                if (name === 'center-safe') {
                    const standard = data.automatic?.elements.find(item => item.type === 'text');
                    object.set('left', standard ? standard.x + (standard.width - object.getScaledWidth()) / 2 : (W - object.getScaledWidth()) / 2);
                }
                clamp(object); changed(); canvas.requestRenderAll();
            }
        } catch (error) { popup?.close(); status(error.message || 'Operazione non riuscita.', true); }
        finally { busy = false; canvas.upperCanvasEl.style.pointerEvents = ''; root.querySelectorAll('input,textarea,select,[data-command]').forEach(input => input.disabled = false); selection(); }
    }

    $('[data-frame]').addEventListener('change', () => { if ($('[data-frame]').value) command('change-frame'); });
    root.querySelectorAll('[data-field]').forEach(input => input.addEventListener('input', () => { if (!busy) fieldChanged(input); }));
    canvas.on('text:changed', changed);
    canvas.on('selection:created', selection); canvas.on('selection:updated', selection); canvas.on('selection:cleared', selection);
    canvas.on('object:moving', ({ target }) => {
        const centers = [W / 2];
        const standard = data?.automatic?.elements.find(item => item.type === 'text');
        if (standard) centers.push(standard.x + standard.width / 2);
        for (const center of centers) if (Math.abs(target.left + target.getScaledWidth() / 2 - center) < 4) target.set('left', center - target.getScaledWidth() / 2);
        clamp(target);
    });
    canvas.on('object:scaling', ({ target }) => {
        if (target?.editorType !== 'text') return;
        const size = Math.min(100, Math.max(6, target.fontSize * target.scaleX));
        $('[data-property="fontSize"]').value = +size.toFixed(2);
        $('[data-property="height"]').value = +(target.height * target.scaleY).toFixed(3);
    });
    canvas.on('object:modified', ({ target }) => {
        if (target && target.editorType === 'text') {
            target.set({ width: target.width * target.scaleX, fontSize: Math.min(100, Math.max(6, target.fontSize * target.scaleX)), scaleY: Math.min(10, Math.max(0.1, target.scaleY / target.scaleX)), scaleX: 1 });
            target.initDimensions();
        }
        if (target) clamp(target);
        changed(); canvas.requestRenderAll();
    });
    canvas.on('text:changed', () => { clearTimeout(timer); timer = setTimeout(changed, 250); });
    root.querySelectorAll('[data-property]').forEach(input => input.addEventListener('input', () => property(input)));
    root.querySelectorAll('[data-command]').forEach(button => button.addEventListener('click', () => command(button.dataset.command)));
    $('[data-frame]').addEventListener('change', () => { if ($('[data-frame]').value) command('change-frame'); });
    root.querySelectorAll('[data-field]').forEach(input => input.addEventListener('input', () => { if (!busy) fieldChanged(input); }));
    root.querySelectorAll('[data-block]').forEach(input => input.addEventListener('input', () => { if (busy) return; updateText(input.dataset.block, input.value); canvas.requestRenderAll(); changed(); }));
    $('[data-element]').addEventListener('change', event => { const object = objects().find(item => item.editorId === event.target.value); object ? canvas.setActiveObject(object) : canvas.discardActiveObject(); selection(); canvas.requestRenderAll(); });
    $('[data-symbol-folder]').addEventListener('change', library);
    $('[data-library]').addEventListener('change', highlightLibrary);
    $('[data-zoom]').addEventListener('change', fit);
    const resizeObserver = new ResizeObserver(() => data && fit()); resizeObserver.observe($('.me-board'));
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    document.addEventListener('keydown', event => {
        if (!root.isConnected || busy || /INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName) || active()?.isEditing) return;
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') { event.preventDefault(); command(event.shiftKey ? 'redo' : 'undo'); }
        if (event.key === 'Delete' && active()) { event.preventDefault(); command('delete'); }
        if (event.key.startsWith('Arrow') && active() && !active().editorLocked) {
            event.preventDefault(); const delta = event.shiftKey ? 10 : 1;
            active().set({ left: active().left + (event.key === 'ArrowRight' ? delta : event.key === 'ArrowLeft' ? -delta : 0), top: active().top + (event.key === 'ArrowDown' ? delta : event.key === 'ArrowUp' ? -delta : 0) });
            clamp(active()); canvas.requestRenderAll(); changed();
        }
    });
    try {
        data = await request(root.dataset.url);
        await Promise.all(['DejaVu Serif', 'DejaVu Sans'].flatMap(family => ['normal', 'bold', 'italic', 'bold italic'].map(style => document.fonts.load(`${style} 16px "${family}"`))));
        catalogs(); populateFields(); await load(data.scene);
        history = [JSON.stringify({scene: serialize(), fields})]; position = 0;
        $('[data-stale]').hidden = !data.stale;
        status(data.revision ? 'Composizione salvata caricata.' : 'Disposizione automatica pronta. Personalizzala e salva.');
        root.querySelectorAll('[data-command]').forEach(button => button.disabled = false);
        selection();
    } catch (error) { status(error.message, true); }
}

function boot() { document.querySelectorAll('[data-manifest-editor]').forEach(start); }
boot();
document.addEventListener('livewire:navigated', boot);
