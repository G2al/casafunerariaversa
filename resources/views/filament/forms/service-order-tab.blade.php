<div>
    @if($getRecord()?->exists)
        <p class="so-tab-hint">Salva i dati della pratica prima di preparare l’ordine. Usa poi «Salva ordine» o «Invia ordine» per registrare la preparazione.</p>
        @livewire('service-order-panel', ['caseId' => $getRecord()->id], key('service-order-'.$getRecord()->id))
    @else
        <div class="so-tab-hint">Salva la pratica per preparare e inviare il suo ordine di servizio.</div>
    @endif
</div>
