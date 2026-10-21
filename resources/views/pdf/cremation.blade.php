<!doctype html>
<html lang="it"><head><meta charset="utf-8"><style>
@page { size: A4 portrait; margin: 9mm 14mm; }
body { margin:0; font-family:'DejaVu Sans',sans-serif; font-size:{{ $data['request']['font_size'] }}pt; line-height:1.12; color:#111; }
.brand { text-align:center; margin-bottom:5pt; }
.brand img { max-width:115pt; max-height:35pt; }
h1 { font-size:12pt; margin:0 0 5pt; text-decoration:underline; }
p { margin:0 0 4pt; } .center { text-align:center; } .strong { font-weight:bold; }
.subject { margin:4pt 0 6pt; font-weight:bold; text-decoration:underline; }
.section { margin-top:5pt; } .declaration { margin:0 0 4pt; }
.staff { width:100%; border-collapse:collapse; margin:3pt 0 5pt; font-size:0.9em; }
.staff th { text-align:left; font-weight:normal; border-bottom:0.5pt solid #999; }
.staff td { padding:1pt 3pt 1pt 0; vertical-align:top; } .staff tr { page-break-inside:avoid; }
.staff .person { width:27%; }
.attachments { margin:0; padding-left:14pt; } .attachments li { margin-bottom:1pt; }
.signature { margin-top:8pt; text-align:right; padding-right:12pt; }
.write-line { display:inline-block; min-width:115pt; border-bottom:0.5pt solid #555; }
.packet-page { page-break-before:always; }
.packet-page h2 { font-size:11pt; text-align:center; margin:0 0 6pt; }
.packet-page .subtitle { text-align:center; font-weight:bold; }
.packet-page .compact { font-size:0.94em; }
.signatures { width:100%; margin-top:12pt; border-collapse:collapse; }
.signatures td { width:50%; text-align:center; vertical-align:top; padding:0 8pt; }
.signatures .line { border-bottom:0.5pt solid #555; height:25pt; }
.mandate { font-size:1.16em; line-height:1.45; }
.mandate h2 { font-size:13pt; }
.mandate .field { margin:13pt 0; border-bottom:0.5pt solid #888; }
.mandate .mandate-text { margin-top:28pt; }
</style></head><body>
@php
    $r = $data['request']; $d = $r['deceased']; $director = $r['director']; $company = $data['company'];
    $packet = $r['packet'];
    $fill = fn ($value) => filled($value) ? $value : '________________';
    foreach (['birth_place', 'birth_date', 'residence_city', 'residence_address'] as $field) {
        $director[$field] = $fill($director[$field]);
    }
@endphp
<div class="brand"><img src="{{ $data['logo'] }}" alt="Casa Funeraria Aversa"></div>
<h1>Domanda di autorizzazione per trasporto di salma</h1>
<p><strong>Al Sig. Sindaco del Comune di {{ $r['competent_city'] }}</strong></p>
<p class="subject">OGGETTO: Richiesta di autorizzazione per trasporto salma<br>
{{ $r['scope'] === 'within_city' ? '☒' : '☐' }} nell’ambito comunale &nbsp;&nbsp; {{ $r['scope'] === 'other_city' ? '☒' : '☐' }} in altro Comune</p>

<p>{{ $d['female'] ? 'Defunta' : 'Defunto' }} <strong>{{ $d['name'] }}</strong>, {{ $d['female'] ? 'nata' : 'nato' }} il <strong>{{ $d['birth_date'] }}</strong> a <strong>{{ $d['birth_place'] }}</strong>, di stato civile {{ $fill($d['marital_status']) }};<br>
cognome e nome del coniuge {{ $fill($d['spouse_name']) }}, residente a {{ $d['residence_city'] }}, {{ $d['residence_address'] }};<br>
{{ $d['female'] ? 'deceduta' : 'deceduto' }} il {{ $d['death_date'] }} alle ore {{ $fill($d['death_time']) }} in {{ $d['death_place'] }}, indirizzo {{ $fill($d['death_address']) }}.</p>

<p class="section"><u>La salma è stata traslata presso {{ $fill($r['prior_transfer']['destination']) }}</u>, su richiesta dei familiari, vista la certificazione del Dott. {{ $fill($r['prior_transfer']['doctor']) }};<br>
<u>la salma è stata traslata in data {{ $fill($r['prior_transfer']['date']) }}</u> dall’impresa funebre {{ $company['legal_name'] }} con auto funebre targato {{ $fill($r['prior_transfer']['vehicle_plate']) }},<br>
<u>in ottemperanza a quanto statuito dall’allegato A, art. 2 comma 3 bis della L.R. 12 del 2001, modificata dalla L.R. 7 del 2013.</u></p>

<p class="section">Il sottoscritto <strong>{{ $director['name'] }}</strong>, nato il {{ $director['birth_date'] }} a {{ $director['birth_place'] }},<br>
residente in {{ $director['residence_city'] }}, {{ $director['residence_address'] }}, in qualità di (1) <strong>DIRETTORE TECNICO</strong>,</p>
<p class="center strong">CHIEDE</p>
<p>ai sensi degli artt. 24 e 34 del D.P.R. 10 settembre 1990 n. 285, l’autorizzazione per il trasporto della salma di cui all’oggetto da questo Comune a quello di <strong>{{ $r['destination_city'] }}</strong>, distante {{ $r['distance_description'] }}, per essere {{ $d['female'] ? 'cremata' : 'cremato' }} presso {{ $r['crematorium'] }}.</p>
<p>Il trasporto funebre sarà effettuato il <strong>{{ $r['transport_date'] }}</strong> alle ore <strong>{{ $r['transport_time'] }}</strong> dall’impresa (2) «<strong>{{ $company['legal_name'] }}</strong>», con sede in {{ $company['company_city'] }}, {{ $company['company_address'] }}, in possesso dell’autorizzazione all’esercizio dell’attività funebre,<br>
<strong>Autorizzazioni/SCIA Prot. n. {{ $company['company_authorization_protocol'] }} del {{ $r['company_authorization_date'] }}</strong>, rilasciata da {{ $company['company_authorization_issuer'] }}, a mezzo (3) autofunebre targato: <strong>{{ $r['vehicle']['plate'] }}</strong>,<br>
utilizzando il seguente personale dipendente, tutti assunti con contratto di lavoro subordinato a tempo pieno:</p>
@include('pdf.cremation-staff')

<p class="declaration">Il sig. (4) <strong>{{ $r['assigned_operator']['name'] }}</strong>, operatore funebre incaricato del trasporto con mansione di necroforo, provvederà a eseguire quanto stabilito dall’art. 9 comma 1 della L.R. 12/2001, modificata dalla L.R. 7/2013 e da quanto disposto dal Regolamento Comunale di Polizia Mortuaria, in veste di incaricato di pubblico servizio ai sensi dell’art. 358 C.P.; in particolare provvederà a verificare l’identità del cadavere, che il feretro sia confezionato in modo corretto, che il carro funebre e il personale utilizzati siano quelli autorizzati; inoltre apporrà i sigilli di chiusura al feretro e redigerà l’apposito verbale.</p>
<p>(5) {{ $fill($r['additional_note']) }}</p>
<p class="strong section">Allega alla presente:</p>
<p>- Autorizzazione di seppellimento rilasciata dall’Ufficiale di Stato Civile;<br>
- Nulla osta del Sanitario della A.S.L.;<br>
- Copie autenticate:</p>
<ol class="attachments" type="a">
<li>dell’autorizzazione rilasciata dal Comune per svolgere l’attività funebre, prevista dall’art. 1 dell’allegato A della L.R. 12/2001, modificata dalla L.R. 7/2013;</li>
<li>dell’idoneità sanitaria e del libretto di circolazione del carro funebre con cui si effettuerà il trasporto;</li>
<li>delle buste paga dei dipendenti utilizzati per effettuare il trasporto, dalle quali si evince il tipo di contratto di lavoro e la qualifica;</li>
<li>di copia degli attestati di formazione con la relativa qualifica dei dipendenti utilizzati per effettuare il trasporto;</li>
<li>attestazione di regolarità contributiva previdenziale e tributaria;</li>
<li>attestazione del pieno rispetto delle norme sulla sicurezza e salute dei lavoratori.</li>
</ol>
<p style="margin-top:5pt">-(6) {{ $fill($r['additional_attachments'][0]) }};<br>-(7) {{ $fill($r['additional_attachments'][1]) }}.</p>
<div class="signature">IL RICHIEDENTE<br><span class="write-line">&nbsp;</span></div>
@foreach([2, 3, 4] as $page)
<div class="packet-page {{ $page === 4 ? 'mandate' : '' }}">
    <div class="brand"><img src="{{ $data['logo'] }}" alt="Casa Funeraria Aversa"></div>
    @include('pdf.cremation-page-'.$page)
</div>
@endforeach
</body></html>
