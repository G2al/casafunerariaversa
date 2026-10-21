<h2>Delega per l’espletamento del servizio<br>ed il trattamento dei dati personali</h2>
<p class="center strong section">{{ $company['legal_name'] }} con sede in {{ $company['company_city'] }}<br>{{ $company['company_address'] }}</p>
<p class="field">Il/La sottoscritto/a <strong>{{ $packet['family']['name'] }}</strong></p>
<p class="field">Nato/a a {{ $packet['family']['birth_place'] }} il {{ $packet['family']['birth_date'] }}</p>
<p class="field">Residente a {{ $packet['family']['city'] }}</p>
<p class="field">Indirizzo e numero civico: {{ $packet['family']['address'] }}</p>
<p class="field">In qualità di {{ $packet['family']['relationship'] }}</p>
<p class="field">Del defunto/a <strong>{{ $d['name'] }}</strong></p>
<p class="field">Deceduto/a in {{ $d['death_place'] }} in data {{ $d['death_date'] }}</p>
<p class="field">Tel./Cell. {{ $fill($packet['family']['phone']) }} — e-Mail {{ $fill($packet['family']['email']) }}</p>
<div class="mandate-text">
<p>Con la presente conferisco ampio mandato all’organizzazione del servizio funebre e all’espletamento di tutte le pratiche inerenti;<br>ad anticipare a mio nome e per mio conto il pagamento di tutti i diritti comunali sanitari.</p>
<p class="section">Inoltre, informato sui diritti e sui limiti di cui alla legge D.L. 196 del 30/06/2003 e successive modificazioni, concernente la “tutela delle persone e di altri soggetti rispetto al trattamento dei dati personali”, esprimo il mio consenso ed autorizzo il trattamento dei miei dati e quelli del mio congiunto defunto, esclusivamente ai fini dell’espletamento del servizio funebre commissionato, ivi compreso la pubblicazione dell’orario ed il luogo del funerale con mezzi divulgativi a disposizione della ditta <strong>{{ $packet['publication_company_name'] }}</strong> con sede in {{ $packet['publication_company_city'] }}.</p>
</div>
<table class="signatures"><tr><td>Data {{ $packet['mandate_date'] }}</td><td>PER ACCETTAZIONE</td></tr><tr><td></td><td class="line">&nbsp;</td></tr></table>
<p class="center" style="margin-top:30pt">Allegare fotocopia documento</p>
