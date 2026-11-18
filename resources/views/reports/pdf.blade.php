<!doctype html><html lang="it"><head><meta charset="utf-8"><style>
@page{margin:22mm 12mm 16mm}body{font-family:'DejaVu Sans';font-size:9px;color:#17212b}header{position:fixed;top:-17mm;left:0;right:0;height:13mm;border-bottom:1px solid #d5b77e}header img{height:12mm;max-width:38mm;float:left}header span{float:right;font-size:8px;margin-top:4mm}h1{font-size:18px;color:#134c35}h2{font-size:13px;margin-top:20px}table{width:100%;border-collapse:collapse}thead{display:table-header-group}th{background:#ecf3ef;font-size:8px}td,th{padding:7px;text-align:left;border-bottom:1px solid #d6dce1;vertical-align:top}tr{page-break-inside:avoid}footer{position:fixed;bottom:-11mm;color:#667085;font-size:8px}.period{color:#667085}
</style></head><body><header><img src="{{ $logo }}"><span>Casa Funeraria Aversa · {{ $generated }}</span></header><footer>Documento interno riservato · {{ $title }}</footer>
<h1>{{ $title }}</h1><p class="period">Periodo: {{ !empty($filters['from']) ? \Illuminate\Support\Carbon::parse($filters['from'])->format('d/m/Y') : 'Tutti' }} — {{ !empty($filters['until']) ? \Illuminate\Support\Carbon::parse($filters['until'])->format('d/m/Y') : 'Tutti' }}</p>
@isset($operators)
<p>Periodo riferito a {{ ['registered_at'=>'presa in carico','created_at'=>'creazione','death_date'=>'decesso'][$filters['date_field'] ?? 'registered_at'] }}. Sono incluse le pratiche nel cestino. Una cassa selezionata per pratica; la vendita corrisponde alla conferma dell’ordine.</p>
<h2>Riepilogo operatori</h2><table><thead><tr>@foreach(['Operatore','Create','Assegnate','Bozze','In lavorazione','Completate','Archiviate / cestino','Cremazioni','Trasporti'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>@foreach($operators as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody></table>
<h2>Dettaglio pratiche e casse</h2>@include('reports.case-details')
@else
<p>Il periodo si riferisce alla data di conferma. Sono contate esclusivamente le casse confermate, una per ordine; bozze e ordini riaperti sono esclusi.</p>@include('reports.order-totals')
@endisset
</body></html>
