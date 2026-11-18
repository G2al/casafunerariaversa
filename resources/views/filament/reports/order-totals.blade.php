<div class="ops-stats">
    @foreach([
        ['Casse confermate', $totalCoffins, 'heroicon-o-cube', 'Una cassa per ordine confermato'],
        ['Quantità materiali', $totalItems, 'heroicon-o-squares-2x2', 'Somma delle quantità confermate'],
        ['Tipologie materiali', count($totals), 'heroicon-o-tag', 'Elementi diversi utilizzati'],
        ['Modelli / confezionamenti', count($models), 'heroicon-o-archive-box', 'Combinazioni presenti negli ordini'],
    ] as [$label,$value,$icon,$hint])
        <div class="ops-stat"><div class="ops-stat-top"><span>{{ $label }}</span><x-filament::icon :icon="$icon" /></div><strong>{{ number_format($value,0,',','.') }}</strong><small>{{ $hint }}</small></div>
    @endforeach
</div>
<section class="ops-panel">
    <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-cube-transparent" /></span><div><h2>Casse per confezionamento</h2><p>Distribuzione delle casse confermate nel periodo.</p></div></div></header>
    <div class="ops-types">
        @foreach($typeTotals as $row)
            <div class="ops-type"><div class="ops-type-top"><span>{{ ['Legno foderato','Contenitore biodegradabile','Legno e zinco'][$loop->index] ?? $row['name'] }}</span><strong>{{ $row['quantity'] }}</strong></div><p>{{ $row['name'] }}</p><div class="ops-meter" aria-hidden="true"><span style="width:{{ $totalCoffins ? round($row['quantity']/$totalCoffins*100,2) : 0 }}%"></span></div></div>
        @endforeach
    </div>
</section>
<div class="ops-two-columns">
    <section class="ops-panel">
        <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-archive-box" /></span><div><h2>Modelli di cassa</h2><p>Quantità per modello e confezionamento.</p></div></div></header>
        @if(count($models))<div class="ops-scroll" role="region" aria-label="Modelli di cassa" tabindex="0"><table class="ops-table"><thead><tr><th scope="col">Modello</th><th scope="col">Confezionamento</th><th scope="col" class="ops-numeric">Casse</th></tr></thead><tbody>@foreach($models as $row)<tr><th scope="row">{{ $row['name'] }}</th><td class="ops-muted">{{ $row['type'] }}</td><td class="ops-numeric"><span class="ops-count">{{ $row['quantity'] }}</span></td></tr>@endforeach</tbody></table></div>
        @else<div class="ops-empty"><x-filament::icon icon="heroicon-o-archive-box" /><strong>Nessun modello da riepilogare</strong><p>I modelli compariranno alla conferma degli ordini.</p></div>@endif
    </section>
    <section class="ops-panel">
        <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-squares-2x2" /></span><div><h2>Materiali utilizzati</h2><p>Quantità complessive per elemento.</p></div></div><span class="ops-badge">{{ count($totals) }} tipologie</span></header>
        @if(count($totals))<div class="ops-scroll" role="region" aria-label="Materiali utilizzati" tabindex="0"><table class="ops-table"><thead><tr><th scope="col">Elemento</th><th scope="col" class="ops-numeric">Quantità totale</th></tr></thead><tbody>@foreach($totals as $row)<tr><th scope="row">{{ $row['name'] }}</th><td class="ops-numeric"><span class="ops-count">{{ $row['quantity'] }}</span></td></tr>@endforeach</tbody></table></div>
        @else<div class="ops-empty"><x-filament::icon icon="heroicon-o-squares-2x2" /><strong>Nessun materiale conteggiato</strong><p>Qui troverai gli elementi inclusi negli ordini confermati.</p></div>@endif
    </section>
</div>
<section class="ops-panel">
    <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-clipboard-document-check" /></span><div><h2>Dettaglio degli ordini confermati</h2><p>Cassa, data di conferma e materiali per ogni pratica.</p></div></div><span class="ops-badge">{{ $totalCoffins }} ordini</span></header>
    @if(count($orders))<div class="ops-scroll" role="region" aria-label="Dettaglio ordini confermati" tabindex="0"><table class="ops-table ops-order-table"><thead><tr><th scope="col">Pratica / defunto</th><th scope="col">Cassa</th><th scope="col">Conferma</th><th scope="col">Elementi e quantità</th></tr></thead><tbody>@foreach($orders as $order)<tr wire:key="report-order-{{ $order->id }}"><td><span class="ops-code">{{ $order->case_code }}</span><strong class="ops-cell-title">{{ $order->deceased_name }}</strong></td><td><span class="ops-cell-title">{{ $order->coffin_name }}</span><small class="ops-cell-sub">{{ \App\Models\ServiceOrder::coffinTypes()[$order->coffin_type] ?? '' }}</small></td><td><span class="ops-cell-title">{{ $order->confirmed_at->format('d/m/Y') }}</span><small class="ops-cell-sub">{{ $order->confirmed_at->format('H:i') }}</small></td><td>@include('filament.reports.order-items',['items'=>$order->items])</td></tr>@endforeach</tbody></table></div>
    @else<div class="ops-empty ops-empty-large"><x-filament::icon icon="heroicon-o-clipboard-document-check" /><strong>Nessun ordine confermato nel periodo</strong><p>Seleziona un altro intervallo oppure conferma un ordine per visualizzare il dettaglio.</p></div>@endif
</section>
