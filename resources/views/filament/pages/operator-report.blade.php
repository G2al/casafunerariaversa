<x-filament-panels::page>
    @php
        $operators = $this->rows();
        $months = $this->months();
        $cases = $this->details();
        $monthMax = max([1, ...array_values($months)]);
    @endphp
    <div class="ops-ui ops-report">
        <section class="ops-panel">
            <header class="ops-panel-head">
                <div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-adjustments-horizontal" /></span><div><h2>Periodo di analisi</h2><p>Seleziona le date e il riferimento del report.</p></div></div>
                @include('filament.forms.service-order-live', ['mode' => 'list'])
            </header>
            <div class="ops-panel-body">{{ $this->form }}</div>
        </section>
        <div class="ops-stats">
            @foreach([
                ['Pratiche nel periodo', count($cases), 'heroicon-o-folder-open', 'Pratiche incluse nei filtri'],
                ['Completate', array_sum(array_column($operators, 'completed')), 'heroicon-o-check-circle', 'Pratiche completate attive'],
                ['Casse selezionate', array_sum(array_column($cases, 'quantity')), 'heroicon-o-cube', 'Una cassa per pratica'],
                ['Operatori con pratiche', count(array_filter($operators, fn ($row) => $row['managed'] > 0)), 'heroicon-o-user-group', 'Con almeno una pratica assegnata'],
            ] as [$label, $value, $icon, $hint])
                <div class="ops-stat"><div class="ops-stat-top"><span>{{ $label }}</span><x-filament::icon :icon="$icon" /></div><strong>{{ number_format($value, 0, ',', '.') }}</strong><small>{{ $hint }}</small></div>
            @endforeach
        </div>
        <section class="ops-panel">
            <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-users" /></span><div><h2>Riepilogo operatori</h2><p>«Create» indica l’autore originario; «Assegnate» il proprietario attuale. Cestino incluso.</p></div></div><span class="ops-badge">{{ count($operators) }} operatori</span></header>
            <div class="ops-scroll" role="region" aria-label="Riepilogo operatori" tabindex="0">
                <table class="ops-table ops-operator-table"><thead><tr><th scope="col">Operatore</th>@foreach(['Create','Assegnate','Bozze','In lavorazione','Completate','Archiviate / cestino','Cremazioni','Trasporti'] as $label)<th scope="col" class="ops-numeric">{{ $label }}</th>@endforeach</tr></thead>
                <tbody>@forelse($operators as $row)<tr>
                    <th scope="row"><div class="ops-person"><span class="ops-avatar">{{ mb_strtoupper(mb_substr($row['name'], 0, 1)) }}</span><span>{{ $row['name'] }}</span></div></th>
                    @foreach(['created','managed','draft','in_progress','completed','archived','cremation','standard_transport'] as $key)
                        <td class="ops-numeric"><span class="ops-count {{ !$row[$key] ? 'ops-zero' : ($key === 'completed' ? 'ops-success' : ($key === 'in_progress' ? 'ops-warning' : '')) }}">{{ $row[$key] }}</span></td>
                    @endforeach
                </tr>@empty<tr><td colspan="9" class="ops-empty-cell">Nessun operatore da visualizzare.</td></tr>@endforelse</tbody></table>
            </div>
        </section>
        <section class="ops-panel">
            <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-chart-bar" /></span><div><h2>Andamento mensile</h2><p>Distribuzione delle pratiche nell’intervallo selezionato.</p></div></div><span class="ops-badge">{{ array_sum($months) }} pratiche</span></header>
            <div class="ops-months">
                @foreach($months as $month => $count)
                    <div class="ops-month {{ $count > 0 && $count === $monthMax ? 'ops-month-peak' : '' }}"><time datetime="{{ $month }}">{{ \Illuminate\Support\Carbon::parse($month.'-01')->translatedFormat('F Y') }}</time><strong class="{{ !$count ? 'ops-zero' : '' }}">{{ $count }}</strong><div class="ops-meter" aria-hidden="true"><span style="width:{{ round($count / $monthMax * 100, 2) }}%"></span></div></div>
                @endforeach
            </div>
        </section>
        <section class="ops-panel">
            <header class="ops-panel-head"><div class="ops-title"><span class="ops-icon"><x-filament::icon icon="heroicon-o-clipboard-document-list" /></span><div><h2>Pratiche, casse ed elementi</h2><p>Dettaglio delle casse selezionate e dei materiali inclusi nei singoli ordini.</p></div></div><span class="ops-badge">{{ count($cases) }} pratiche</span></header>
            @include('filament.reports.case-details', ['cases' => $cases])
            <div class="ops-panel-foot"><x-filament::icon icon="heroicon-o-information-circle" /><span>Le quantità non ancora compilate sono indicate come «Da definire». Le vendite corrispondono agli ordini confermati.</span></div>
        </section>
    </div>
</x-filament-panels::page>
