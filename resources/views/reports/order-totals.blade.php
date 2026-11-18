<h2>Casse confermate: {{ $totalCoffins }}</h2>
<table class="report-table" style="width:100%;text-align:left"><thead><tr><th>Tipologia di confezionamento</th><th>Totale casse</th></tr></thead><tbody>@foreach($typeTotals as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['quantity'] }}</td></tr>@endforeach</tbody></table>
<h2>Dettaglio modelli cassa</h2>
<table class="report-table" style="width:100%;text-align:left"><thead><tr><th>Cassa</th><th>Confezionamento</th><th>Quantità</th></tr></thead><tbody>@foreach($models as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['type'] }}</td><td>{{ $row['quantity'] }}</td></tr>@endforeach</tbody></table>
<h2>Totali materiali — quantità complessiva {{ $totalItems }}</h2>
<table class="report-table" style="width:100%;text-align:left"><thead><tr><th>Elemento</th><th>Quantità totale</th></tr></thead><tbody>@foreach($totals as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['quantity'] }}</td></tr>@endforeach</tbody></table>
<h2>Dettaglio per cassa / ordine</h2>
<table class="report-table" style="width:100%;text-align:left"><thead><tr><th>Pratica / defunto</th><th>Cassa</th><th>Conferma</th><th>Elementi e quantità</th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->case_code }}<br>{{ $order->deceased_name }}</td><td>{{ $order->coffin_name }}</td><td>{{ $order->confirmed_at->format('d/m/Y H:i') }}</td><td>@foreach($order->items as $item){{ $item['name'] }}: {{ $item['quantity'] }}<br>@endforeach</td></tr>@empty<tr><td colspan="4">Nessun ordine confermato nel periodo.</td></tr>@endforelse</tbody></table>
