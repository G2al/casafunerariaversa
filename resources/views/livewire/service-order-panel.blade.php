<div class="ops-ui so-editor" x-data="{orderDirty:false,photoUploading:false}" x-on:livewire-upload-start="photoUploading=true" x-on:livewire-upload-finish="photoUploading=false" x-on:livewire-upload-error="photoUploading=false" x-on:livewire-upload-cancel="photoUploading=false" x-effect="$el.dataset.dirty=String(orderDirty)" x-on:input="orderDirty=true" x-on:change="orderDirty=true" x-on:service-order-saved="orderDirty=false" x-on:beforeunload.window="if(orderDirty){$event.preventDefault();$event.returnValue='';}" data-service-order-form>
    <header class="so-heading">
        <div class="ops-title"><span class="ops-icon ops-icon-large"><x-filament::icon icon="heroicon-o-clipboard-document-list" /></span><div><h2>Ordine di servizio</h2><p>@if($order){{ $order->case_code }} <span aria-hidden="true">·</span> {{ $order->deceased_name }}@else Prepara la cassa e gli elementi per questa pratica.@endif</p></div></div>
        <div class="so-heading-status"><span class="ops-badge {{ ['draft'=>'','submitted'=>'ops-warning','confirmed'=>'ops-success'][$state] }}"><span class="ops-status-dot" aria-hidden="true"></span>{{ ['draft'=>'Bozza','submitted'=>'Da preparare','confirmed'=>'Confermato'][$state] }}</span>@include('filament.forms.service-order-live', ['mode' => 'detail'])</div>
    </header>
    <ol class="so-steps" aria-label="Avanzamento ordine">
        @foreach(['draft'=>'Preparazione','submitted'=>'Invio all’operatore','confirmed'=>'Conferma'] as $step=>$label)
            @php($stepIndex = array_search($step, ['draft','submitted','confirmed']))
            @php($currentIndex = array_search($state, ['draft','submitted','confirmed']))
            <li class="{{ $stepIndex < $currentIndex ? 'so-step-done' : ($stepIndex === $currentIndex ? 'so-step-current' : '') }}" @if($step === $state) aria-current="step" @endif><span class="so-step-number">@if($stepIndex < $currentIndex)<x-filament::icon icon="heroicon-o-check" />@else{{ $loop->iteration }}@endif</span><span>{{ $label }}</span></li>
        @endforeach
    </ol>
    @if($remoteChanged)
        <div class="ops-alert ops-alert-warning" role="alert"><x-filament::icon icon="heroicon-o-arrow-path" /><div><strong>È disponibile una nuova versione</strong><p>Un altro utente ha aggiornato l’ordine. Ricaricalo prima di salvare; le modifiche locali saranno sostituite.</p><x-filament::button type="button" icon="heroicon-o-arrow-path" size="sm" color="gray" wire:click="reload" wire:confirm="Ricaricare l’ordine? Le modifiche locali non salvate saranno perse.">Ricarica ordine</x-filament::button></div></div>
    @endif
    @if($errors->any())<div class="ops-alert ops-alert-danger" role="alert"><x-filament::icon icon="heroicon-o-exclamation-circle" /><div><strong>Verifica i dati dell’ordine</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div></div>@endif
    @if($state === 'confirmed')<div class="ops-alert ops-alert-success"><x-filament::icon icon="heroicon-o-check-circle" /><div><strong>Preparazione confermata</strong><p>La cassa e i materiali sono inclusi nei conteggi.@if(auth()->user()->isAdmin()) Puoi riaprire l’ordine dal pulsante in fondo per correggerlo.@endif</p></div></div>@endif
    <fieldset @disabled($state === 'confirmed') class="so-fieldset">
        <section class="ops-panel so-section">
            <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-cube" /></span><div><h3>Dati della cassa</h3><p>Un modello e un confezionamento per pratica.</p></div></div><span class="ops-badge">1 cassa</span></header>
            <div class="so-fields-grid ops-panel-body">
                <label class="so-label">Nome / modello cassa<input class="so-input" wire:model="data.coffin_name" maxlength="255" placeholder="Inserisci il modello della cassa"></label>
                <label class="so-label">Confezionamento del feretro<select class="so-input" wire:model="data.coffin_type"><option value="">Seleziona il confezionamento</option>@foreach($types as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            </div>
        </section>
        <section class="ops-panel so-section">
            <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-photo" /></span><div><h3>Foto dell’ordine</h3><p>Una foto della cassa o della preparazione, visibile agli utenti autorizzati all’ordine.</p></div></div><span class="ops-badge">Facoltativa</span></header>
            <div class="so-photo-body">
                @php($pendingPhoto = $photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile && !$errors->has('photo'))
                @php($savedPhoto = $order?->photo_path && !$removePhoto)
                @if($pendingPhoto || $savedPhoto)
                    <a class="so-photo-preview" href="{{ $pendingPhoto ? $photo->temporaryUrl() : route('private.service-orders.photo', [$order, 'v'=>$order->revision]) }}" target="_blank" rel="noopener"><img src="{{ $pendingPhoto ? $photo->temporaryUrl() : route('private.service-orders.photo', [$order, 'v'=>$order->revision]) }}" alt="Foto dell’ordine di servizio"></a>
                @else
                    <div class="so-photo-placeholder"><x-filament::icon icon="heroicon-o-photo" /><span>{{ $removePhoto ? 'Foto da rimuovere al salvataggio' : 'Nessuna foto aggiunta' }}</span></div>
                @endif
                <div class="so-photo-tools">
                    @if($state !== 'confirmed')
                        <label class="so-label">{{ $pendingPhoto || $savedPhoto ? 'Sostituisci foto' : 'Aggiungi foto' }}<input class="so-input so-photo-input" type="file" wire:model="photo" wire:key="order-photo-{{ $order?->id ?? 'new' }}-{{ $revision ?? 'new' }}" accept="image/jpeg,image/png,image/webp"></label>
                        <p class="ops-muted">JPEG, PNG o WebP, massimo 10 MB. La foto viene ottimizzata e registrata quando salvi o invii l’ordine.</p>
                        <p class="ops-text-warning" wire:loading wire:target="photo" role="status">Caricamento foto in corso…</p>
                        @if($pendingPhoto || $savedPhoto)<x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-trash" wire:click="clearPhoto" x-on:click="orderDirty=true">Rimuovi foto</x-filament::button>@endif
                    @endif
                    @if($savedPhoto && !$pendingPhoto)
                        <div class="so-action-buttons"><a class="so-photo-link" href="{{ route('private.service-orders.photo', $order) }}" target="_blank" rel="noopener"><x-filament::icon icon="heroicon-o-eye" />Apri / stampa</a><a class="so-photo-link" href="{{ route('private.service-orders.photo', [$order, 'download'=>1]) }}"><x-filament::icon icon="heroicon-o-arrow-down-tray" />Scarica foto</a></div>
                    @elseif($pendingPhoto)<p class="ops-text-warning">Nuova foto pronta: salva l’ordine per confermarla.</p>@endif
                </div>
            </div>
        </section>
        <section class="ops-panel so-section">
            <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-squares-2x2" /></span><div><h3>Elementi dell’ordine</h3><p>Seleziona i materiali da preparare e indica le quantità.</p></div></div><span class="ops-badge" x-text="$wire.data.items.filter(item => item.selected).length + ' selezionati'"></span></header>
            <div class="so-materials">
                @foreach($data['items'] as $index=>$item)
                    <div class="so-item" wire:key="order-item-{{ $index }}" x-bind:class="{'so-item-selected':$wire.data.items[{{ $index }}].selected, 'so-item-pending':$wire.data.items[{{ $index }}].selected && !$wire.data.items[{{ $index }}].quantity}">
                        <input class="so-check" type="checkbox" wire:model="data.items.{{ $index }}.selected" x-on:change="if($event.target.checked && !$wire.data.items[{{ $index }}].quantity){$wire.set('data.items.{{ $index }}.quantity',1,false)}" aria-label="Includi {{ $item['name'] }}">
                        <label class="so-item-name"><span>Elemento</span><input class="so-input" wire:model="data.items.{{ $index }}.name" maxlength="150" aria-label="Nome elemento"></label>
                        <label class="so-item-quantity"><span>Quantità</span><input class="so-input" type="number" min="1" max="100000" wire:model="data.items.{{ $index }}.quantity" x-bind:disabled="!$wire.data.items[{{ $index }}].selected" aria-label="Quantità {{ $item['name'] }}" placeholder="—"></label>
                    </div>
                @endforeach
            </div>
            <div class="so-materials-footer"><p class="ops-muted"><x-filament::icon icon="heroicon-o-information-circle" />Puoi inviare gli elementi senza quantità. L’operatore le completerà prima della conferma.</p><x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-plus" wire:click="addItem" x-on:click="orderDirty=true">Aggiungi elemento</x-filament::button></div>
        </section>
    </fieldset>
    <footer class="so-actions" wire:loading.attr="inert" x-bind:inert="photoUploading">
        <div class="so-save-state"><span class="ops-text-warning" x-show="orderDirty" x-cloak><span class="ops-status-dot" aria-hidden="true"></span>Modifiche da salvare</span><span x-show="!orderDirty">@if($order)Revisione {{ $revision }}@else Ordine non ancora salvato @endif</span>@if($order?->confirmed_at)<small>Confermato il {{ $order->confirmed_at->format('d/m/Y H:i') }}</small>@endif</div>
        <div class="so-action-buttons">
            @if($state !== 'confirmed')
                <x-filament::button type="button" color="gray" icon="heroicon-o-bookmark" wire:click="saveOrder('save')">Salva ordine</x-filament::button>
                @unless(auth()->user()->isServiceOrderOperator())<x-filament::button type="button" icon="heroicon-o-paper-airplane" wire:click="saveOrder('submit')" wire:confirm="Inviare l’ordine all’operatore?">Invia ordine</x-filament::button>@endunless
                @if($state === 'submitted' && (auth()->user()->isAdmin() || auth()->user()->isServiceOrderOperator()))<x-filament::button type="button" color="success" icon="heroicon-o-check-circle" wire:click="saveOrder('confirm')" wire:confirm="Confermare la preparazione? La cassa e gli elementi entreranno nei conteggi.">Conferma ordine</x-filament::button>@endif
            @elseif(auth()->user()->isAdmin())
                <x-filament::button type="button" color="warning" icon="heroicon-o-arrow-path" wire:click="saveOrder('reopen')" wire:confirm="Riaprire l’ordine? Sarà escluso dai conteggi delle casse confermate fino alla nuova conferma.">Riapri per correzione</x-filament::button>
            @endif
            @if($order && auth()->user()->isAdmin())
                <x-filament::button type="button" color="danger" icon="heroicon-o-trash" wire:click="deleteOrder" wire:confirm="Eliminare definitivamente ordine, foto e storico? L’ordine sarà escluso dai conteggi e potrai ricrearlo da zero nella stessa pratica.">Elimina e azzera</x-filament::button>
            @endif
        </div>
    </footer>
</div>
