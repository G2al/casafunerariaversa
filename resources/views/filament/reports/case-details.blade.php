<div class="ops-scroll" role="region" aria-label="Pratiche, casse ed elementi" tabindex="0">
    <table class="ops-table ops-case-table">
        <thead><tr>@foreach(['Operatore','Pratica / defunto','Procedura','Cassa / confezionamento','Casse','Stato ordine','Elementi inclusi'] as $label)<th scope="col" @class(['ops-numeric' => $label === 'Casse'])>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>@forelse($cases as $row)
            <tr wire:key="report-case-{{ $row['code'] }}">
                <td><span class="ops-operator-name">{{ $row['operator'] }}</span></td>
                <td><span class="ops-code">{{ $row['code'] }}</span><strong class="ops-cell-title">{{ $row['name'] }}</strong></td>
                <td><span class="ops-badge {{ $row['type'] === 'Cremazione' ? 'ops-info' : '' }}" title="{{ $row['type'] }}">{{ $row['type'] === 'Cremazione' ? 'Cremazione' : 'Trasporto' }}</span></td>
                <td><span class="ops-cell-title {{ $row['coffin_name'] === 'Da indicare nell’ordine' ? 'ops-muted' : '' }}">{{ $row['coffin_name'] }}</span><small class="ops-cell-sub">{{ $row['coffin_type'] }}</small></td>
                <td class="ops-numeric"><span class="ops-count {{ !$row['quantity'] ? 'ops-zero' : '' }}">{{ $row['quantity'] }}</span></td>
                <td><span class="ops-badge {{ ['Confermato'=>'ops-success','Inviato'=>'ops-warning','Bozza'=>'','Nessun ordine'=>'ops-muted'][$row['status']] ?? '' }}">{{ $row['status'] }}</span></td>
                <td>@include('filament.reports.order-items', ['items' => $row['items']])</td>
            </tr>
        @empty<tr><td colspan="7"><div class="ops-empty"><x-filament::icon icon="heroicon-o-folder-open" /><strong>Nessuna pratica nel periodo</strong><p>Modifica le date per consultare altre pratiche.</p></div></td></tr>@endforelse</tbody>
    </table>
</div>
