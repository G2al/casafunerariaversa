<div>
    @if($getRecord()?->exists)
        <p style="margin-bottom:16px">L’ordine ha un salvataggio dedicato. Salva prima gli altri dati della pratica, poi prepara e invia l’ordine.</p>
        @livewire('service-order-panel', ['caseId' => $getRecord()->id], key('service-order-'.$getRecord()->id))
    @else
        <p>Salva la pratica per preparare e inviare il suo ordine di servizio.</p>
    @endif
</div>
