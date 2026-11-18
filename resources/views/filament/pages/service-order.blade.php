<x-filament-panels::page>
    @if($this->getRecord()->funeral_case_id)
        @livewire('service-order-panel', ['caseId' => $this->getRecord()->funeral_case_id, 'returnToList' => true])
    @else
        <x-filament::section heading="Ordine storico — pratica eliminata">
            <p>{{ $this->getRecord()->case_code }} · {{ $this->getRecord()->deceased_name }}</p>
            <p>Cassa: {{ $this->getRecord()->coffin_name }} — {{ \App\Models\ServiceOrder::coffinTypes()[$this->getRecord()->coffin_type] ?? '' }}</p>
            @if($this->getRecord()->photo_path)<a href="{{ route('private.service-orders.photo', $this->getRecord()) }}" target="_blank" rel="noopener">Apri foto dell’ordine</a>@endif
            @foreach($this->getRecord()->items as $item)<p>{{ $item['name'] }}: {{ $item['quantity'] ?? 'Da definire' }}</p>@endforeach
        </x-filament::section>
    @endif
</x-filament-panels::page>
