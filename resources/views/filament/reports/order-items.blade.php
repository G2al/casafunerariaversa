@if(count($items))
    <details class="ops-items" wire:ignore.self><summary>{{ count($items) }} {{ count($items) === 1 ? 'elemento' : 'elementi' }}</summary><ul>@foreach($items as $item)<li><span>{{ $item['name'] }}</span><strong @class(['ops-text-warning'=>blank($item['quantity'])])>{{ $item['quantity'] ?? 'Da definire' }}</strong></li>@endforeach</ul></details>
@else
    <span class="ops-zero" aria-label="Nessun elemento">—</span>
@endif
