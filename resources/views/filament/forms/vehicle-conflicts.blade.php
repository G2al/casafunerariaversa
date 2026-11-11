@php $conflicts=app(\App\Services\ReservationConflicts::class)->vehicles($get('vehicle_id'),$get('transport_at'),$get('transport_ends_at'),$getRecord()?->id); @endphp
<div>@if($conflicts->isNotEmpty())<p style="color:#dc2626;font-weight:bold">Attenzione: mezzo già impegnato.</p>
@foreach($conflicts as $other)<p>{{ $other->starts_at?->format('d/m/Y H:i') }} · {{ $other->ends_at?->format('d/m/Y H:i') }} · {{ auth()->user()->can('view',$other->funeralCase)?$other->funeralCase->code.' '.$other->funeralCase->full_name:'Altra pratica' }}</p>@endforeach
<p>Cambia mezzo o orari, oppure conferma la forzatura a tua responsabilità.</p>@endif</div>
