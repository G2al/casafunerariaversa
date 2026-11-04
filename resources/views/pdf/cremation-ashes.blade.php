@if(! $isStandard)
<p>Dopo l’avvenuta cremazione le ceneri verranno affidate a <strong>{{ $packet['ashes']['custodian'] }}</strong> e saranno trasportate nel Comune di {{ $packet['ashes']['city'] }}, presso {{ $packet['ashes']['placement'] }}, {{ $packet['ashes']['address'] }}.</p>
@endif