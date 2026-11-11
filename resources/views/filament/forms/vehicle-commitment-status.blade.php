@php $entry=\App\Filament\Support\VehicleCommitmentForm::entry($getRecord()); @endphp
<div style="padding:14px;border:1px solid #aaa;border-radius:8px">
@if($entry?->status==='committed')
<p><strong>Impegno registrato:</strong> {{ $entry->snapshot['vehicle_name'] }} · {{ $entry->snapshot['plate'] }} · {{ $entry->starts_at?->format('d/m/Y H:i') }} · {{ $entry->ends_at?->format('d/m/Y H:i') }}</p>
<p>Direttore tecnico: {{ $entry->snapshot['director'] ?? '' }}. L’apertura di WhatsApp non certifica l’invio del messaggio.</p>
@if((string)$get('vehicle_id')!==(string)$entry->vehicle_id || (string)$get('transport_at')!==$entry->starts_at?->format('Y-m-d H:i:s') || (string)$get('transport_ends_at')!==$entry->ends_at?->format('Y-m-d H:i:s'))
<p>Per applicare e comunicare variazioni, conferma nuovamente l’impegno. Il semplice salvataggio non modifica una prenotazione già confermata.</p>
@endif
@else
<p>Salva la pratica, poi usa «Conferma impegno e apri WhatsApp» per prenotare il mezzo.</p>
@endif
</div>
