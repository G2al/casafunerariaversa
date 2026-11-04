import { Canvas, Textbox, FabricImage, Rect, Ellipse, Triangle, controlsUtils } from 'fabric';

const W = 841.89, H = 595.28;
const labels = { intro: 'Introduzione', name: 'Nome e cognome', age: 'Età', family: 'Annuncio familiari', viewing: 'Camera ardente', transfer: 'Trasferimento', ceremony: 'Esequie', closing: 'Ringraziamento', date: 'Luogo e data', agency: 'Firma agenzia', photo: 'Fotografia', logo: 'Logo' };

async function start(root) {
    if (root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const $ = selector => root.querySelector(selector);
    const status = (message, error = false) => { $('[data-status]').textContent = message; $('[data-status]').dataset.error = String(error); };
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let data, busy = false, dirty = false, restoring = false, history = [], position = -1, timer, currentBackground = null, freshAssets = new Set();
    const canvas = new Canvas($('[data-canvas]'), { width: W, height: H, backgroundColor: '#ffffff', preserveObjectStacking: true, selection: false, selectionKey: [], enableRetinaScaling: false, uniformScaling: true, uniScaleKey: null });
    root.querySelectorAll('[data-command]').forEach(button => button.disabled = true);
    const objects = () => canvas.getObjects().filter(object => object.editorId);
    const active = () => canvas.getActiveObject();

    async function request(url, options = {}) {
        const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) } });
        const result = await response.json().catch(() => ({}));
        if (! response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || `Operazione non riuscita (${response.status}).`);
        return result;
    }

    function lock(object, value) {
        object.set({ editorLocked: value, lockMovementX: value, lockMovementY: value, lockScalingX: value, lockScalingY: value, editable: !value, hasControls: !value });
    }

    function clamp(object) {
        object.set({ left: Math.max(0, Math.min(object.left, W - object.getScaledWidth())), top: Math.max(0, Math.min(object.top, H - object.getScaledHeight())) });
        object.setCoords();
    }

    function serialize() {
        return { version: 1, ...(currentBackground ? { backgroundAsset: currentBackground } : {}), elements: objects().map(object => ({
            id: object.editorId, type: object.editorType || (object.editorAsset ? 'image' : 'text'),
            ...(object.editorAsset ? { asset: object.editorAsset } : object.editorType === 'text' ? { text: object.text, stretchY: +object.scaleY.toFixed(6) } : { fill: object.fill || 'transparent', stroke: object.stroke || '#000000', strokeWidth: object.strokeWidth || 0 }),
            x: +object.left.toFixed(3), y: +object.top.toFixed(3), width: +(object.width * object.scaleX).toFixed(3), height: +(object.height * object.scaleY).toFixed(3),
            fontSize: object.fontSize || 12, fontFamily: object.fontFamily || 'DejaVu Serif', bold: object.fontWeight === 'bold', italic: object.fontStyle === 'italic',
            color: typeof object.fill === 'string' && /^#[a-f\d]{6}$/i.test(object.fill) ? object.fill : '#000000', align: object.textAlign || 'center', lineHeight: object.lineHeight || 1.5, locked: !!object.editorLocked,
        })) };
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
        if (!object) return;
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
        const json = JSON.stringify(serialize());
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
        fit(); selection();
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
        await load(JSON.parse(history[position]));
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
        const selected = active(), zoom = canvas.getZoom();
        canvas.discardActiveObject();
        canvas.setDimensions({ width: W, height: H }); canvas.setZoom(1);
        const exported = canvas.toCanvasElement(3508 / W);
        const output = document.createElement('canvas'); output.width = 3508; output.height = 2480;
        output.getContext('2d').drawImage(exported, 0, 0, 3508, 2480);
        const image = output.toDataURL('image/jpeg', 0.97);
        canvas.setDimensions({ width: W * zoom, height: H * zoom }); canvas.setZoom(zoom);
        if (selected) canvas.setActiveObject(selected);
        canvas.requestRenderAll();
        return { scene, image };
    }

    async function save(generate = false) {
        clearTimeout(timer);
        if (data.stale) throw new Error('I dati della pratica sono cambiati. Usa Aggiorna dalla pratica prima di salvare.');
        const payload = imageExport();
        const result = await request(data.saveUrl, { method: 'POST', body: JSON.stringify({ ...payload, freshAssets: [...freshAssets].filter(key => key === (payload.scene.backgroundAsset || 'background') || payload.scene.elements.some(item => item.asset === key)), source: data.source, revision: data.revision, generate }) });
        data.revision = result.revision; dirty = false; freshAssets.clear();
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
                catalogs(); await load(data.scene); history = [JSON.stringify(serialize())]; position = 0; dirty = false;
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
                data = updated; data.assets = updated.currentAssets; catalogs(); freshAssets = new Set(Object.keys(data.assets)); data.source = updated.currentSource; data.stale = false; await load({ version: 1, ...(current.backgroundAsset ? { backgroundAsset: current.backgroundAsset } : {}), elements }); changed();
                $('[data-stale]').hidden = true;
            } else if (name === 'change-frame') {
                const key = $('[data-frame]').value;
                if (!key) throw new Error('Seleziona una cornice.');
                if (key !== 'none') { data.assets[key] = data.catalog.frames.find(item => item.key === key).url; freshAssets.add(key); }
                const scene = serialize(); scene.backgroundAsset = key; await load(scene); changed();
            } else if (name === 'upload') {
                const file = $('[data-upload]').files[0]; if (!file) throw new Error('Seleziona prima una immagine JPEG, PNG o WebP.');
                const body = new FormData(); body.append('image', file);
                const response = await fetch(data.uploadUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body });
                const result = await response.json(); if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message);
                data.assets[result.key] = result.url; data.catalog.uploads.push(result); catalogs();
                $('[data-symbol-folder]').value = 'uploads'; library(); $('[data-library]').value = result.key; highlightLibrary(); await addImage(result.key);
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
        catalogs(); await load(data.scene);
        history = [JSON.stringify(serialize())]; position = 0;
        $('[data-stale]').hidden = !data.stale;
        status(data.revision ? 'Composizione salvata caricata.' : 'Disposizione automatica pronta. Personalizzala e salva.');
        root.querySelectorAll('[data-command]').forEach(button => button.disabled = false);
        selection();
    } catch (error) { status(error.message, true); }
}

function boot() { document.querySelectorAll('[data-manifest-editor]').forEach(start); }
boot();
document.addEventListener('livewire:navigated', boot);
