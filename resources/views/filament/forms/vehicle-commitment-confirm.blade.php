<div style="display:grid;gap:12px">
<p><strong>Defunto:</strong> {{ $deceased ?: 'Da compilare' }}</p>
<p><strong>Mezzo:</strong> {{ $vehicle?->name }} · {{ $vehicle?->plate }}</p>
<p><strong>Direttore tecnico:</strong> {{ $director ?: 'Da selezionare' }}</p>
<p><strong>Inizio:</strong> {{ $transportAt ?: 'Da compilare' }} · <strong>Fine:</strong> {{ $endsAt ?: 'Da compilare' }}</p>
<p>Verranno salvate le modifiche della pratica e registrato l’impegno, poi si aprirà WhatsApp con il messaggio per <strong>+39 329 643 0362</strong>. Il messaggio sarà inviato dal tuo account solo quando premi Invia in WhatsApp.</p>
@if($forced)<p><strong>Confermi a tua responsabilità anche eventuali sovrapposizioni con altri impegni.</strong></p>@endif
</div>
