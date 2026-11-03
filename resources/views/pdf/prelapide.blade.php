<!doctype html>
<html lang="it"><head><meta charset="utf-8"><style>
@page { margin: 20mm; }
body { font-family: 'DejaVu Serif', serif; text-align: center; margin: 0; }
.content { padding-top: 28mm; }
.name { font-size: {{ $data['font_size'] ?? 44 }}pt; font-weight: bold; line-height: 1.25; overflow-wrap: break-word; }
.dates { font-size: 24pt; margin-top: 18mm; }
.label { font-size: 14pt; }
</style></head><body><div class="content">
<div class="name">{{ $data['first_name'] }}<br>{{ $data['last_name'] }}</div>
<div class="dates"><span class="label">Data di nascita</span> {{ $data['birth_date'] ?? '________________' }}<br>
<span class="label">Data di morte</span> {{ $data['death_date'] ?? '________________' }}</div>
</div></body></html>
