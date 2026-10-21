<!doctype html>
<html lang="it"><head><meta charset="utf-8"><style>
@page { margin: 16mm 14mm; } body { font-family:'DejaVu Serif',serif; font-size:9pt; color:#222; }
h1 { font-size:16pt; } h2 { font-size:11pt; margin:14pt 0 5pt; }
.draft { border:2px solid #555; text-align:center; padding:7pt; font-weight:bold; }
table { width:100%; border-collapse:collapse; table-layout:fixed; } td { padding:4pt; border-bottom:1px solid #ddd; vertical-align:top; overflow-wrap:break-word; } td.label { width:22%; color:#444; font-size:8pt; } td.value { width:28%; }
.footer { font-size:8pt; color:#555; margin-top:18pt; }
</style></head><body>
<div class="draft">BOZZA DA VERIFICARE</div>
@if($data['logo'])<img src="{{ $data['logo'] }}" style="max-height:40pt;max-width:120pt">@endif
<h2>{{ $data['company']['legal_name'] ?? $data['company']['trade_name'] ?? '' }}</h2>
<p>{{ $data['company']['company_address'] ?? '' }}<br>{{ $data['company']['phone'] ?? '' }} {{ $data['company']['email'] ?? '' }}</p>
@if(!empty($data['company']['tax_number']))<p>P. IVA / C.F. {{ $data['company']['tax_number'] }}</p>@endif
<h1>{{ $title }}</h1>
<p>Pratica {{ $data['code'] }} · {{ $data['type_label'] }}<br><strong>{{ $data['name'] }}</strong></p>
<p>{{ $description }}</p>
@foreach($data['rows'] as $section => $rows)
<h2>{{ $section }}</h2><table>@foreach(array_chunk($rows, 2, true) as $pair)<tr>@foreach($pair as $label => $value)<td class="label">{{ $label }}</td><td class="value">{{ $value }}</td>@endforeach @if(count($pair) === 1)<td class="label"></td><td class="value"></td>@endif</tr>@endforeach</table>
@endforeach
@if(!empty($data['staff_list']))<h2>Personale incaricato</h2>@foreach($data['staff_list'] as $member)<p>{{ implode(' · ', array_filter($member)) }}</p>@endforeach
@elseif($data['staff'])<h2>Personale incaricato</h2><p>{{ implode(' · ', array_filter($data['staff'])) }}</p>@endif
@if($data['vehicle'])<h2>Mezzo incaricato</h2><p>{{ implode(' · ', array_filter($data['vehicle'])) }}</p>@endif
@if(!empty($data['company']['legal_details']))<h2>Dati aziendali e autorizzazioni</h2><p>{{ $data['company']['legal_details'] }}</p>@endif
<p style="margin-top:28pt">Luogo e data ____________________ &nbsp; Firma ____________________</p>
<p class="footer">Generato il {{ $data['generated_at'] }}. Ricostruzione operativa da sottoporre a verifica dell’agenzia prima dell’uso amministrativo ufficiale.</p>
</body></html>
