@php $conflicts=app(\App\Services\ReservationConflicts::class)->rooms($get('room_id'),$get('viewing_starts_at'),$get('viewing_ends_at'),$getRecord()?->id); @endphp
<div>@if($conflicts->isNotEmpty())<p style="color:#dc2626;font-weight:bold">Attenzione: stanza già impegnata.</p>
@foreach($conflicts as $other)<p>{{ $other->viewing_starts_at?->format('d/m/Y H:i') }} · {{ $other->viewing_ends_at?->format('d/m/Y H:i') }} · {{ auth()->user()->can('view',$other)?$other->code.' '.$other->full_name:'Altra pratica' }}</p>@endforeach
<p>Cambia stanza o orari, oppure conferma la forzatura a tua responsabilità.</p>@endif</div>
