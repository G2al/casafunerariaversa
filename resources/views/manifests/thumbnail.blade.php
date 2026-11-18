<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 841.89 595.28">
<rect width="841.89" height="595.28" fill="white"/>
@if($bg = $assets[$scene['backgroundAsset'] ?? 'background'] ?? null)<image href="{{ $bg }}" x="14.17" y="14.17" width="813.55" height="566.94" preserveAspectRatio="xMidYMin meet"/>@endif
@foreach($scene['elements'] as $e)
@if($e['type'] === 'text')
<text fill="{{ $e['color'] }}" font-family="{{ $e['fontFamily'] }}" font-size="{{ $e['fontSize'] }}" font-weight="{{ $e['bold'] ? 'bold' : 'normal' }}" font-style="{{ $e['italic'] ? 'italic' : 'normal' }}" text-anchor="{{ $e['align'] === 'center' ? 'middle' : ($e['align'] === 'right' ? 'end' : 'start') }}">
@foreach(explode("\n", $e['text'] ?? '') as $index => $line)<tspan x="{{ $e['x'] + ($e['align'] === 'center' ? $e['width']/2 : ($e['align'] === 'right' ? $e['width'] : 0)) }}" y="{{ $e['y'] + $e['fontSize'] + $index * $e['fontSize'] * $e['lineHeight'] }}">{{ $line }}</tspan>@endforeach
</text>
@elseif($e['type'] === 'image' && isset($assets[$e['asset']]))<image href="{{ $assets[$e['asset']] }}" x="{{ $e['x'] }}" y="{{ $e['y'] }}" width="{{ $e['width'] }}" height="{{ $e['height'] }}"/>
@endif
@endforeach
</svg>
