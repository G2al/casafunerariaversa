<x-filament-widgets::widget>
    @assets
        @vite(['resources/css/occupancy-live.css','resources/js/occupancy-live.js'])
    @endassets
    <x-filament::section>
        <x-slot name="heading">Mezzi e stanze in tempo reale</x-slot>
        <div data-occupancy-live wire:ignore
             data-config="{{ \Illuminate\Support\Js::encode($this->realtimeConfig()) }}"
             data-snapshot="{{ \Illuminate\Support\Js::encode($this->snapshot()) }}">
            <div class="ol-heading">
                <div><span class="ol-connection" data-connection role="status" aria-live="polite">Connessione in preparazione</span><span class="ol-updated" data-updated></span></div>
                <span class="ol-order-hint">In corso · In programma · Disponibili</span>
            </div>
            <p class="ol-warning" data-warning role="alert" hidden></p>
            <div class="ol-grid" data-resources></div>
            <noscript>Attiva JavaScript per visualizzare occupazione, countdown e aggiornamenti WebSocket.</noscript>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
