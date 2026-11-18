<div x-data="{orderDirty:false}" x-effect="$el.dataset.dirty=String(orderDirty)" x-on:input="orderDirty=true" x-on:change="orderDirty=true" x-on:service-order-saved="orderDirty=false" x-on:beforeunload.window="if(orderDirty){$event.preventDefault();$event.returnValue='';}" data-service-order-form>
    @include('filament.forms.service-order-live', ['mode' => 'detail'])
    <h3 style="font-size:18px;font-weight:700;margin:12px 0">{{ $order?->case_code }} · {{ $order?->deceased_name }} — {{ ['draft'=>'Bozza','submitted'=>'Da preparare','confirmed'=>'Confermato'][$state] }}</h3>
    @if($remoteChanged)
        <p role="alert" style="color:#d97706;margin:12px 0">L’ordine è stato aggiornato da un altro utente. Le tue modifiche restano qui; ricarica prima di salvare.</p>
        <x-filament::button type="button" wire:click="reload" wire:confirm="Ricaricare l’ordine? Le modifiche locali non salvate saranno perse.">Ricarica ordine aggiornato</x-filament::button>
    @endif
    @if($errors->any())<div role="alert" style="color:#dc2626;margin:12px 0">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
    <fieldset @disabled($state === 'confirmed') style="display:grid;gap:16px;margin:16px 0">
        <label>Nome / modello cassa<input class="so-input" wire:model="data.coffin_name" maxlength="255" placeholder="Nome della cassa"></label>
        <label>Confezionamento del feretro<select class="so-input" wire:model="data.coffin_type"><option value="">Seleziona</option>@foreach($types as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        <p>Una cassa per pratica. Seleziona gli elementi da preparare; la quantità può essere completata dall’operatore prima della conferma.</p>
        <div style="display:grid;gap:10px">
        @foreach($data['items'] as $index=>$item)
            <div wire:key="order-item-{{ $index }}" style="display:grid;grid-template-columns:24px minmax(100px,1fr) 100px;gap:10px;align-items:center">
                <input type="checkbox" wire:model="data.items.{{ $index }}.selected" x-on:change="if($event.target.checked && !$wire.data.items[{{ $index }}].quantity){$wire.set('data.items.{{ $index }}.quantity',1,false)}" aria-label="Includi {{ $item['name'] }}">
                <input class="so-input" wire:model="data.items.{{ $index }}.name" aria-label="Nome elemento" maxlength="150">
                <input class="so-input" type="number" min="1" max="100000" wire:model="data.items.{{ $index }}.quantity" aria-label="Quantità {{ $item['name'] }}" placeholder="Quantità">
            </div>
        @endforeach
        </div>
        <x-filament::button type="button" color="gray" wire:click="addItem" x-on:click="orderDirty=true">Aggiungi elemento</x-filament::button>
    </fieldset>
    <div style="display:flex;gap:12px;flex-wrap:wrap" wire:loading.attr="inert">
        @if($state !== 'confirmed')
            <x-filament::button type="button" color="gray" wire:click="saveOrder('save')">Salva ordine</x-filament::button>
            @unless(auth()->user()->isServiceOrderOperator())<x-filament::button type="button" wire:click="saveOrder('submit')" wire:confirm="Inviare l’ordine all’operatore?">Invia ordine</x-filament::button>@endunless
            @if($state === 'submitted' && (auth()->user()->isAdmin() || auth()->user()->isServiceOrderOperator()))<x-filament::button type="button" color="success" wire:click="saveOrder('confirm')" wire:confirm="Confermare la preparazione? La cassa e gli elementi entreranno nei conteggi.">Conferma ordine</x-filament::button>@endif
        @elseif(auth()->user()->isAdmin())
            <x-filament::button type="button" color="warning" wire:click="saveOrder('reopen')" wire:confirm="Riaprire l’ordine? Sarà escluso dai conteggi delle casse confermate fino alla nuova conferma.">Riapri per correzione</x-filament::button>
        @endif
    </div>
    @if($order)<p style="margin-top:16px;font-size:12px">Revisione {{ $revision }} @if($order->confirmed_at) · Confermato il {{ $order->confirmed_at->format('d/m/Y H:i') }} @endif</p>@endif
</div>
