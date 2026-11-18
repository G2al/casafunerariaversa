<div style="overflow:auto"><table class="report-table" style="width:100%;border-collapse:collapse;text-align:left;font-size:12px">
<thead><tr>@foreach(['Operatore','Pratica / defunto','Procedura','Confezionamento / cassa','Casse','Stato ordine','Elementi inclusi'] as $label)<th style="padding:8px;border-bottom:1px solid #999">{{ $label }}</th>@endforeach</tr></thead>
<tbody>@forelse($cases as $row)<tr>
<td>{{ $row['operator'] }}</td><td>{{ $row['code'] }}<br>{{ $row['name'] }}</td><td>{{ $row['type'] }}</td><td>{{ $row['coffin_type'] }}<br>{{ $row['coffin_name'] }}</td><td>{{ $row['quantity'] }}</td><td>{{ $row['status'] }}</td>
<td>@foreach($row['items'] as $item){{ $item['name'] }}: {{ $item['quantity'] ?? 'Da definire' }}@unless($loop->last)<br>@endunless @endforeach</td>
</tr>@empty<tr><td colspan="7">Nessuna pratica nel periodo.</td></tr>@endforelse</tbody></table></div>
