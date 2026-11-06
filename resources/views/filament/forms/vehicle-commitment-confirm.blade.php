@php
    try {
        $displayTime = $transportAt ? \Illuminate\Support\Carbon::parse($transportAt)->format('d/m/Y H:i') : 'Da compilare';
    } catch (\Throwable) {
        $displayTime = 'Data e ora non valide: correggile nella scheda Trasporto';
    }
@endphp
<div style="display:grid;gap:12px">
    <p><strong>Defunto:</strong> {{ $deceased ?: 'Da compilare' }}</p>
    <p><strong>Mezzo:</strong> {{ $vehicle?->name ?? 'Da selezionare' }} · <strong>Targa:</strong> {{ $vehicle?->plate ?? '—' }}</p>
    <p><strong>Data e ora trasporto:</strong> {{ $displayTime }}</p>
    <p>La conferma salva tutti i dati della pratica e comunica l’impegno tramite WhatsApp al <strong>+39 335 737 8138</strong>. Lo stato della pratica rimane quello scelto nel form.</p>
    @if(in_array($entry?->status, ['uncertain', 'sending', 'failed'], true))
        <p style="font-weight:600">Verifica prima WhatsApp: il precedente messaggio potrebbe essere già arrivato. Confermando autorizzi un nuovo tentativo. Se l’invio è ancora in corso, attendi.</p>
    @else
        <p>Se questi dati sono già stati comunicati, non verrà inviato un duplicato. Le modifiche produrranno un messaggio di aggiornamento.</p>
    @endif
</div>
