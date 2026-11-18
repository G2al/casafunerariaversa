@assets
    @vite(['resources/js/service-orders-live.js', 'resources/css/service-orders.css'])
@endassets
<div wire:ignore data-service-orders-live data-mode="{{ $mode }}" data-config="{{ \Illuminate\Support\Js::encode(app(\App\Filament\Widgets\ResourceOccupancy::class)->realtimeConfig()) }}" data-channel="{{ auth()->user()->isAdmin() || auth()->user()->isServiceOrderOperator() ? 'service-orders' : 'service-orders.user.'.auth()->id() }}">
    <span data-order-connection role="status">Connessione ordini in corso…</span>
    <p data-order-notice role="status" class="so-live-notice" hidden></p>
</div>
