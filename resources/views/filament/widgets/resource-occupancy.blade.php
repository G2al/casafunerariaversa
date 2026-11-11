<x-filament-widgets::widget>
<x-filament::section>
<x-slot name="heading">Occupazione mezzi e stanze</x-slot>
<p wire:offline style="color:#dc2626;font-weight:bold">Connessione assente: disponibilità non aggiornata.</p>
<div wire:poll.5s style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px">
@foreach($this->resources() as $group=>$resources)
<div><h3 style="font-size:18px;font-weight:bold;margin-bottom:12px">{{ $group }}</h3>
@forelse($resources as $resource)
@php $active=collect($resource['rows'])->where('current',true)->count(); @endphp
<div style="border:1px solid {{ $active>1?'#dc2626':'#aaa' }};border-radius:10px;padding:12px;margin-bottom:12px">
<p style="font-weight:bold">{{ $resource['name'] }} · {{ $active>1?'Sovrapposizione':($active?'Occupato ora':'Libero ora') }}</p>
@forelse($resource['rows'] as $row)
<div style="margin-top:12px">
<p>@if($row['url'])<a href="{{ $row['url'] }}" style="text-decoration:underline">{{ $row['label'] }}</a>@else{{ $row['label'] }}@endif</p>
<p>{{ $row['current']?'In corso':'Prenotato' }} · {{ $row['start'] }} · {{ $row['end'] }}</p>
@if($row['director'])<p>Direttore tecnico: {{ $row['director'] }}</p>@endif
<div role="progressbar" aria-label="Avanzamento occupazione" aria-valuenow="{{ $row['percent'] }}" aria-valuemin="0" aria-valuemax="100" style="height:12px;background:#e5e7eb;border-radius:6px;overflow:hidden;margin-top:6px">
<div style="height:100%;width:{{ $row['percent'] }}%;background:{{ $active>1?'#dc2626':'#d97706' }};transition:width 1s"></div>
</div>
</div>
@empty<p>Nessun impegno futuro.</p>@endforelse
</div>
@empty<p>Nessuna risorsa inserita.</p>@endforelse
</div>
@endforeach
</div>
<p style="margin-top:10px;font-size:12px">Aggiornato alle {{ now()->format('H:i:s') }}. Aggiornamento ogni 5 secondi mentre la pagina è visibile. Gli intervalli terminati lasciano libera la risorsa.</p>
</x-filament::section>
</x-filament-widgets::widget>
