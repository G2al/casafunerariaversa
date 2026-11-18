<div style="width:100%;aspect-ratio:297/210;overflow:hidden;border-radius:9px;background:#f1f5f9;border:1px solid #cbd5e1;margin-bottom:10px">
    <img src="{{ route('private.manifests.thumbnail', [$getRecord(), 'v' => $getRecord()->updated_at?->getTimestamp()]) }}" alt="Anteprima {{ $getRecord()->full_name ?: 'Nuovo manifesto' }}" loading="lazy" style="display:block;width:100%;height:100%;object-fit:contain">
</div>
