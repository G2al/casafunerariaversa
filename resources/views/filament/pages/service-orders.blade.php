<x-filament-panels::page>
    @include('filament.forms.service-order-live', ['mode' => 'list'])
    {{ $this->table }}
</x-filament-panels::page>
