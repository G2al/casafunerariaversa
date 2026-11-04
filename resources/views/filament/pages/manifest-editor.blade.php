<x-filament-panels::page>
    @vite(['resources/css/manifest-editor.css', 'resources/js/manifest-editor.js'])
    <style>
        @foreach(['serif' => 'DejaVu Serif', 'sans' => 'DejaVu Sans'] as $font => $family)
            @foreach(['' => ['normal', 'normal'], '-bold' => ['bold', 'normal'], '-italic' => ['normal', 'italic'], '-bold-italic' => ['bold', 'italic']] as $suffix => [$weight, $style])
                @font-face { font-family:'{{ $family }}'; src:url('{{ route('private.manifest-editor.font', $font.$suffix) }}') format('truetype'); font-weight:{{ $weight }}; font-style:{{ $style }}; font-display:block; }
            @endforeach
        @endforeach
    </style>
    <div class="me" data-manifest-editor data-url="{{ route('private.manifest-editor.show', [$this->getRecord(), 'kind' => $this->editorKind]) }}" wire:ignore>
        <div class="me-top">
            <div><strong>{{ $this->getRecord()->code }} · {{ $this->getRecord()->full_name }}</strong><p>Trascina gli elementi. Fai doppio clic su un testo per modificarlo.</p></div>
            <a class="me-button" href="{{ \App\Filament\Resources\FuneralCaseResource::getUrl('edit', ['record' => $this->getRecord()]) }}">Torna alla pratica</a>
        </div>
        <div class="me-notice" data-stale hidden>I dati della pratica sono cambiati dall’ultimo salvataggio. La composizione salvata resta invariata. Usa “Aggiorna dalla pratica” per ricaricare testi e immagini.</div>
        <div class="me-toolbar" aria-label="Strumenti del manifesto">
            <button type="button" data-command="undo" title="Annulla ultima modifica">↶ Annulla</button>
            <button type="button" data-command="redo" title="Ripeti ultima modifica">↷ Ripeti</button>
            <button type="button" data-command="add-text">+ Testo</button>
            <button type="button" data-command="add-photo">+ Foto</button>
            <button type="button" data-command="add-logo">+ Logo</button>
            <button type="button" data-command="add-rect">+ Rettangolo</button>
            <button type="button" data-command="add-ellipse">+ Cerchio / ovale</button>
            <button type="button" data-command="add-triangle">+ Triangolo</button>
            <button type="button" data-command="refresh">Aggiorna dalla pratica</button>
            <button type="button" data-command="reset">Ripristina automatico</button>
        </div>
        <div class="me-toolbar">
            <label>Cartella simboli<select data-symbol-folder></select></label>
            <label>Immagine<select data-library></select></label><button type="button" data-command="add-library">Inserisci</button>
            <label>Cornice<select data-frame></select></label><button type="button" data-command="change-frame">Cambia cornice</button>
            <label>Immagine aggiuntiva<input data-upload type="file" accept="image/jpeg,image/png,image/webp"></label><button type="button" data-command="upload">Carica e inserisci</button>
        </div>
        <div class="me-symbol-gallery" data-symbol-gallery aria-label="Immagini della cartella"></div>
        <div class="me-workspace">
            <section class="me-board" aria-label="Foglio A4 orizzontale">
                <div class="me-zoom"><label>Zoom <select data-zoom><option value="fit">Adatta al foglio</option><option value="0.5">50%</option><option value="0.75">75%</option><option value="1">100%</option><option value="1.5">150%</option></select></label><span>A4 orizzontale · 297 × 210 mm</span></div>
                <div class="me-scroll"><div class="me-canvas"><canvas data-canvas aria-label="Composizione del manifesto"></canvas></div></div>
            </section>
            <aside class="me-properties" aria-label="Proprietà elemento">
                <label>Elemento<select data-element><option value="">Seleziona sul foglio</option></select></label>
                <p class="me-hint" data-selection-hint>Seleziona un testo, la foto o il logo per modificarlo. Puoi cambiare la cornice dalla barra superiore.</p>
                <fieldset data-properties disabled>
                    <label data-text-control>Testo<textarea data-property="text" rows="5"></textarea></label>
                    <div data-text-control>
                        <label>Carattere<select data-property="fontFamily"><option value="DejaVu Serif">Classico</option><option value="DejaVu Sans">Lineare</option></select></label>
                        <div class="me-pair"><label>Dimensione<input data-property="fontSize" type="number" min="6" max="100" step="0.5"></label><label>Colore<input data-property="color" type="color"></label></div>
                        <div class="me-pair"><label class="me-check"><input data-property="bold" type="checkbox"> Grassetto</label><label class="me-check"><input data-property="italic" type="checkbox"> Corsivo</label></div>
                        <label>Allineamento<select data-property="align"><option value="left">Sinistra</option><option value="center">Centro</option><option value="right">Destra</option></select></label>
                        <label>Interlinea<input data-property="lineHeight" type="number" min="1" max="2" step="0.05"></label>
                    </div>
                    <div data-shape-control hidden>
                        <label class="me-check"><input data-property="transparent" type="checkbox"> Senza riempimento</label>
                        <label>Riempimento<input data-property="fill" type="color"></label>
                        <label>Colore bordo<input data-property="stroke" type="color"></label>
                        <label>Spessore bordo<input data-property="strokeWidth" type="number" min="0" max="40" step="0.5"></label>
                    </div>
                    <div class="me-pair"><label>Posizione X<input data-property="x" type="number" min="0" max="841" step="1"></label><label>Posizione Y<input data-property="y" type="number" min="0" max="595" step="1"></label></div>
                    <div class="me-pair"><label>Larghezza<input data-property="width" type="number" min="10" max="841" step="1"></label><label>Altezza<input data-property="height" type="number" min="5" max="595" step="1"></label></div>
                    <label class="me-check"><input data-property="locked" type="checkbox"> Blocca posizione e dimensioni</label>
                    <div class="me-pair"><button type="button" data-command="center">Centra sul foglio</button><button type="button" data-command="center-safe">Centra nell’area testo</button></div>
                    <div class="me-pair"><button type="button" data-command="forward">Porta avanti</button><button type="button" data-command="backward">Porta indietro</button></div>
                    <div class="me-pair"><button type="button" data-command="full-page">Adatta al foglio</button><button type="button" data-command="behind">Porta sul fondo</button></div>
                    <button type="button" data-command="delete" class="me-danger">Rimuovi elemento</button>
                </fieldset>
                <p class="me-hint">Puoi usare le frecce della tastiera per piccoli spostamenti. Trascina gli angoli per ingrandire testi, immagini e forme.</p>
            </aside>
        </div>
        <div class="me-bottom">
            <span data-status role="status" aria-live="polite">Caricamento del manifesto…</span>
            <div class="me-toolbar"><button type="button" data-command="preview">Anteprima PDF</button><button type="button" data-command="save" class="me-primary">Salva composizione</button><button type="button" data-command="generate" class="me-primary">Salva e scarica PDF</button></div>
        </div>
    </div>
</x-filament-panels::page>
