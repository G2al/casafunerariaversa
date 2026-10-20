@php
    $templates = \App\Models\ManifestTemplate::where('is_active', true)->orderBy('sort_order')->get();
    $selected = $get('manifest_template_id');
@endphp
<div>
    @if($templates->isEmpty())
        <p style="padding:16px;border:1px dashed #aaa;border-radius:10px">Il catalogo cornici è vuoto. L’amministratore può caricare le immagini da «Cornici manifesti».</p>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;max-height:330px;overflow:auto">
        @foreach($templates as $template)
            <button type="button" @if($isDisabled()) disabled @endif wire:click="$set('data.manifest_template_id', {{ $template->id }})" style="padding:8px;border:{{ (string)$selected === (string)$template->id ? '3px solid #b78b35' : '1px solid #aaa' }};border-radius:8px;text-align:left">
                <img src="{{ route('private.template', $template) }}" alt="" loading="lazy" style="width:100%;aspect-ratio:1.414;object-fit:contain"><span>{{ $template->title }}</span>
            </button>
        @endforeach
        </div>
    @endif
</div>
