@php
    $entry = \App\Filament\Support\VehicleCommitmentForm::entry($getRecord());
    $snapshot = $entry?->snapshot ?? [];
    $confirmed = $entry?->confirmed_snapshot;
@endphp
<div style="padding:14px;border:1px solid #aaa;border-radius:8px;display:grid;gap:8px">
    @if($entry)
        <p><strong>Ultimo impegno registrato:</strong> {{ $snapshot['vehicle_name'] ?? '' }} · {{ $snapshot['plate'] ?? '' }} · {{ $snapshot['transport_at'] ?? '' }}</p>
        <p><strong>WhatsApp:</strong>
            @switch($entry->status)
                @case('sent') Accettato da WAHA il {{ $entry->sent_at?->format('d/m/Y H:i') }}. @break
                @case('sending') Invio avviato il {{ $entry->attempted_at->format('d/m/Y H:i') }}. @break
                @case('failed') Invio non riuscito. @break
                @default Esito da verificare su WhatsApp.
            @endswitch
        </p>
        @if($entry->error)<p>{{ $entry->error }}</p>@endif
        @if($confirmed && $entry->status !== 'sent')
            <p>Ultimo impegno comunicato con conferma WAHA: {{ $confirmed['plate'] }} · {{ $confirmed['transport_at'] }}.</p>
        @endif
        <p>Se modifichi i dati, premi «Aggiorna impegno» per comunicarli. Salvare la pratica da solo non invia messaggi.</p>
    @else
        <p>Nessun mezzo ancora impegnato per questa pratica. Seleziona il mezzo, compila data e ora trasporto e premi «Impegna mezzo».</p>
    @endif
</div>
