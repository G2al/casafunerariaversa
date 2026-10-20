<!doctype html>
<html lang="it"><head><meta charset="utf-8"><style>
@page { margin: 0; size: A4 landscape; }
body { margin: 0; font-family: 'DejaVu Serif', serif; }
.background { position: absolute; }
.line { position: absolute; text-align: center; line-height: 1.25; white-space: nowrap; }
</style></head><body>
{{-- Le cornici devono essere A4 landscape: contain conserva integralmente proporzioni e bordi. --}}
<div class="background" style="left:{{ $data['layout']['page']['margin'] }}pt;top:{{ $data['layout']['page']['margin'] }}pt;width:{{ $data['layout']['page']['width'] }}pt;height:{{ $data['layout']['page']['height'] }}pt;text-align:center;line-height:0"><img src="{{ $data['background'] }}" style="max-width:{{ $data['layout']['page']['width'] }}pt;max-height:{{ $data['layout']['page']['height'] }}pt;width:auto;height:auto"></div>
@if($data['photo'])<img src="{{ $data['photo'] }}" style="position:absolute; left:{{ $data['layout']['area']['x'] }}pt; top:{{ $data['layout']['area']['y'] }}pt; max-width:76pt; max-height:96pt">@endif
@foreach($data['layout']['lines'] as $line)
<div class="line" style="left:{{ $line['x'] }}pt; top:{{ $line['y'] }}pt; width:{{ $line['width'] }}pt; font-size:{{ $line['size'] }}pt; font-weight:{{ $line['bold'] ? 'bold' : 'normal' }}">{{ $line['text'] }}</div>
@endforeach
@if($data['logo'])<div style="position:absolute;left:{{ $data['layout']['area']['x'] }}pt;top:{{ $data['layout']['logo_y'] }}pt;width:{{ $data['layout']['area']['w'] }}pt;text-align:center"><img src="{{ $data['logo'] }}" style="max-width:80pt;max-height:26pt"></div>@endif
</body></html>
