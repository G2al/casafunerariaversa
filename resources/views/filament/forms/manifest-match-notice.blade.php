@php
$matches=filled($get('last_name')) && !$get('manifest_record_id') ? \App\Models\ManifestRecord::visibleTo(auth()->user())->whereNull('source_case_id')->where('data->last_name',$get('last_name'))->when(filled($get('first_name')),fn($q)=>$q->where('data->first_name',$get('first_name')))->limit(10)->get() : collect();
@endphp
@if($matches->isNotEmpty())<div style="padding:12px;border:1px solid #d97706;border-radius:8px"><strong>Esiste già un manifesto per questo nominativo. Vuoi collegarlo?</strong>
@foreach($matches as $m)<p>{{ $m->code }} · {{ $m->full_name }} · Nascita: {{ $m->data['birth_date']??'non indicata' }} · Decesso: {{ $m->data['death_date']??'non indicato' }}</p>@endforeach
<p>Selezionalo nel campo seguente e salva. Verifica le date: potrebbero esserci omonimi.</p></div>@endif
