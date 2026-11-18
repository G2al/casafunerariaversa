<?php

namespace App\Livewire;

use App\Models\FuneralCase;
use App\Models\ServiceOrder;
use App\Services\ServiceOrders;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ServiceOrderPanel extends Component
{
    #[Locked]
    public int $caseId;

    #[Locked]
    public ?int $revision = null;

    #[Locked]
    public string $state = 'draft';

    public array $data = [];

    public bool $remoteChanged = false;

    public function mount(int $caseId): void
    {
        $this->caseId = $caseId;
        $this->reload();
    }

    private function authorizedOrder(): ?ServiceOrder
    {
        $order = ServiceOrder::where('funeral_case_id', $this->caseId)->first();
        if (auth()->user()->isServiceOrderOperator()) {
            abort_unless($order, 403);
            Gate::authorize('view', $order);
        } else {
            Gate::authorize('update', FuneralCase::withTrashed()->findOrFail($this->caseId));
        }

        return $order;
    }

    public function reload(): void
    {
        $order = $this->authorizedOrder();
        $this->revision = $order?->revision;
        $this->state = $order?->status ?? 'draft';
        $defaults = ServiceOrder::defaults();
        $items = $order?->items ?? [];
        $names = array_column($items, 'name');
        $this->data = ['coffin_name' => $order?->coffin_name ?? '', 'coffin_type' => $order?->coffin_type ?? (FuneralCase::find($this->caseId)?->cremation_details['coffin_type'] ?? ''),
            'items' => array_merge($items, array_values(array_filter($defaults, fn ($i) => ! in_array($i['name'], $names, true))))];
        $this->remoteChanged = false;
        $this->resetErrorBag();
        $this->dispatch('service-order-saved');
    }

    public function remoteUpdate(): void
    {
        $order = $this->authorizedOrder();
        $this->remoteChanged = $order?->revision !== $this->revision;
    }

    public function addItem(): void
    {
        $this->authorizedOrder();
        abort_if(count($this->data['items'] ?? []) >= 100, 422);
        $this->data['items'][] = ['name' => 'Nuovo elemento', 'selected' => true, 'quantity' => null];
    }

    public function saveOrder(string $action = 'save'): void
    {
        $this->authorizedOrder();
        app(ServiceOrders::class)->save($this->caseId, $this->data, $this->revision, $action);
        $this->reload();
        Notification::make()->success()->title(match ($action) {
            'submit' => 'Ordine inviato', 'confirm' => 'Ordine confermato: cassa conteggiata', 'reopen' => 'Ordine riaperto', default => 'Ordine salvato'
        })->send();
    }

    public function render()
    {
        $order = $this->authorizedOrder();

        return view('livewire.service-order-panel', ['order' => $order, 'types' => ServiceOrder::coffinTypes()]);
    }
}
