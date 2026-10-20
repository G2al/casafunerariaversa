<x-filament-panels::page>
    {{ $this->form }}
    <p>«Create» conta l’autore originario; «Assegnate» il proprietario attuale. I conteggi includono le pratiche nel cestino. Il riepilogo mensile segue l’intervallo scelto.</p>
    <div style="overflow:auto;border:1px solid #ccc;border-radius:10px"><table style="width:100%;border-collapse:collapse;text-align:left">
        <thead><tr>@foreach(['Operatore','Create','Assegnate','Bozze','In lavorazione','Completate','Archiviate / cestino','Cremazioni','Trasporti'] as $title)<th style="padding:12px;border-bottom:1px solid #ccc">{{ $title }}</th>@endforeach</tr></thead>
        <tbody>@foreach($this->rows() as $row)<tr>@foreach($row as $value)<td style="padding:12px;border-bottom:1px solid #ddd">{{ $value }}</td>@endforeach</tr>@endforeach</tbody>
    </table></div>
    <x-filament::section heading="Riepilogo mensile"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px">@foreach($this->months() as $month => $count)<div><span>{{ \Illuminate\Support\Carbon::parse($month.'-01')->translatedFormat('F Y') }}</span><p style="font-weight:bold;font-size:22px">{{ $count }}</p></div>@endforeach</div></x-filament::section>
</x-filament-panels::page>
