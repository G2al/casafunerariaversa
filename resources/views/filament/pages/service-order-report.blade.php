<x-filament-panels::page>
    @include('filament.forms.service-order-live', ['mode' => 'list'])
    {{ $this->form }}
    <p>Conteggi per data di conferma: una cassa per ordine confermato. Ordini in bozza, in preparazione o riaperti sono esclusi.</p>
    <x-filament::section heading="Casse e materiali">@include('reports.order-totals', $this->report())</x-filament::section>
</x-filament-panels::page>
