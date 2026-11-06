@php
    $vehicle = \App\Models\FuneralVehicle::find($get('vehicle_id'));
    $photos = collect($vehicle?->attachments ?? [])->filter(fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true));
@endphp
<div>
    @if($vehicle)
        <p style="margin-bottom:10px;font-weight:600">Foto del mezzo: {{ $vehicle->name }} · {{ $vehicle->plate }}</p>
        @if($photos->isEmpty())
            <p>Nessuna foto caricata per questo mezzo.</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
                @foreach($photos as $index => $path)
                    <a wire:key="vehicle-photo-{{ $vehicle->id }}-{{ md5($path) }}" href="{{ route('private.vehicle-photo', [$vehicle, $index]) }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ route('private.vehicle-photo', [$vehicle, $index]) }}" alt="Foto {{ $loop->iteration }} del mezzo {{ $vehicle->plate }}" loading="lazy" style="width:100%;height:150px;object-fit:contain;border:1px solid #aaa;border-radius:8px">
                    </a>
                @endforeach
            </div>
        @endif
    @endif
</div>
