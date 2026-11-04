<!doctype html><html lang="it"><head><meta charset="utf-8"><style>
@page {margin:0;size:A4 landscape} body {margin:0;font-family:'DejaVu Sans';} .element {position:absolute;box-sizing:border-box;text-align:center;line-height:1.2;}
</style></head><body>
@foreach($data['layout']['elements'] as $e)
@if($e['type'] === 'rect')<div class="element" style="left:{{ $e['x'] }}pt;top:{{ $e['y'] }}pt;width:{{ $e['width'] }}pt;height:{{ $e['height'] }}pt;border:{{ $e['strokeWidth'] }}pt solid black"></div>
@elseif($e['type'] === 'image')<img class="element" src="{{ $data[$e['asset']] }}" style="left:{{ $e['x'] }}pt;top:{{ $e['y'] }}pt;width:{{ $e['width'] }}pt;height:{{ $e['height'] }}pt">
@else<div class="element" style="left:{{ $e['x'] }}pt;top:{{ $e['y'] }}pt;width:{{ $e['width'] }}pt;font-size:{{ $e['fontSize'] }}pt;font-weight:bold">{{ $e['text'] }}</div>@endif
@endforeach
</body></html>