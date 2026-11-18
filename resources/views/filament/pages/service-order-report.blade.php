<x-filament-panels::page>
    @php($report = $this->report())
    <div class="ops-ui ops-report">
        <section class="ops-panel">
            <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-calendar-days" /></span><div><h2>Periodo delle conferme</h2><p>I conteggi seguono la data di conferma dell’ordine.</p></div></div>@include('filament.forms.service-order-live', ['mode' => 'list'])</header>
            <div class="ops-panel-body">{{ $this->form }}</div>
            <div class="ops-panel-foot"><x-filament::icon icon="heroicon-o-information-circle" /><span>Sono inclusi solo gli ordini confermati. Bozze, ordini in preparazione e riaperti sono esclusi.</span></div>
        </section>
        @include('filament.reports.order-totals', $report)
    </div>
</x-filament-panels::page>
