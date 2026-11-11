@php
$owner=$getRecord();
$isManifest=$owner instanceof \App\Models\ManifestRecord;
$files=$owner?->exists ? \Illuminate\Support\Facades\DB::table('editor_attachments')->where($isManifest?'manifest_record_id':'funeral_case_id',$owner->id)->get() : collect();
@endphp
@if($files->isNotEmpty())<details style="padding:12px;border:1px solid #aaa;border-radius:8px"><summary>Allegati grafici caricati nell’editor</summary>
@foreach($files as $file)<p style="margin-top:8px">Immagine {{ $loop->iteration }} · <a style="text-decoration:underline" href="{{ route('private.file-present',[$isManifest?'editor-manifest':'editor-case',$owner->id,$file->id]) }}" target="_blank">Stampa</a> · <a style="text-decoration:underline" href="{{ route('private.file-present',[$isManifest?'editor-manifest':'editor-case',$owner->id,$file->id,'download'=>1]) }}" target="_blank">Scarica</a></p>@endforeach
</details>@endif
