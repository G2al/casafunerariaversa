<script>
if (!window.__funeralUnsavedActionGuard) {
    window.__funeralUnsavedActionGuard = true;
    document.addEventListener('click', function(event) {
        const target=event.target.closest('a,button');
        if(!target) return;
        const action=target.getAttribute('wire:click')||'';
        if(!(target.tagName==='A' && target.target==='_blank') && !action.includes('mountAction(')) return;
        for(const root of document.querySelectorAll('[wire\\:id]')) {
            const wire=window.Livewire?.find(root.getAttribute('wire:id'));
            const canonical=wire?.__instance?.canonical;
            if(!canonical || !Object.hasOwn(canonical,'data') || !Object.hasOwn(canonical,'savedDataHash') || !window.jsMd5) continue;
            const hash=window.jsMd5(JSON.stringify(wire.data).replace(/\\/g,''));
            if(hash!==wire.savedDataHash && !window.confirm('Ci sono modifiche non salvate. Questa azione potrebbe utilizzare i dati precedenti. Vuoi continuare?')) {
                event.preventDefault();event.stopImmediatePropagation();
            }
            break;
        }
    },true);
}
</script>
