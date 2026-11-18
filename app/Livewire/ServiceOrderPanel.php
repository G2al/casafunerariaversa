<?php

namespace App\Livewire;

use App\Filament\Resources\ServiceOrderResource;
use App\Models\FuneralCase;
use App\Models\ServiceOrder;
use App\Services\ServiceOrders;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class ServiceOrderPanel extends Component
{
    use WithFileUploads;

    public $photo;

    #[Locked]
    public bool $removePhoto = false;

    #[Locked]
    public ?int $orderId = null;

    #[Locked]
    public bool $returnToList = false;

    #[Locked]
    public int $caseId;

    #[Locked]
    public ?int $revision = null;

    #[Locked]
    public string $state = 'draft';

    public array $data = [];

    public bool $remoteChanged = false;

    public function mount(int $caseId, bool $returnToList = false): void
    {
        $this->caseId = $caseId;
        $this->returnToList = $returnToList;
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
        $this->orderId = $order?->id;
        $this->photo = null;
        $this->removePhoto = false;
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
        if (auth()->user()->isServiceOrderOperator() && ! ServiceOrder::where('funeral_case_id', $this->caseId)->where('status', '!=', 'draft')->exists()) {
            $this->redirect(ServiceOrderResource::getUrl('index'));

            return;
        }
        $order = $this->authorizedOrder();
        $this->remoteChanged = $order?->id !== $this->orderId || $order?->revision !== $this->revision;
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
        $this->validate(['photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240']);
        app(ServiceOrders::class)->save($this->caseId, $this->data, $this->revision, $action, $this->photo, $this->removePhoto, $this->orderId);
        $this->reload();
        Notification::make()->success()->title(match ($action) {
            'submit' => 'Ordine inviato', 'confirm' => 'Ordine confermato: cassa conteggiata', 'reopen' => 'Ordine riaperto', default => 'Ordine salvato'
        })->send();
    }

    public function updatedPhoto(): void
    {
        $order = $this->authorizedOrder();
        abort_if($order?->status === 'confirmed', 403);
        $this->validate(['photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240']);
        $this->removePhoto = false;
    }

    public function clearPhoto(): void
    {
        $order = $this->authorizedOrder();
        abort_if($order?->status === 'confirmed', 403);
        $this->photo = null;
        $this->removePhoto = true;
        $this->resetErrorBag('photo');
    }

    public function deleteOrder(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $order = $this->authorizedOrder();
        if ($order && ($order->id !== $this->orderId || $order->revision !== $this->revision)) {
            throw ValidationException::withMessages(['order' => 'L’ordine è cambiato. Ricaricalo prima di eliminarlo.']);
        }
        if ($order) {
            app(ServiceOrders::class)->delete($order->id, $this->revision);
        }
        Notification::make()->success()->title('Ordine eliminato e azzerato')->send();
        if ($this->returnToList) {
            $this->redirect(ServiceOrderResource::getUrl('index'));

            return;
        }
        $this->reload();
    }

    public function render()
    {
        $order = $this->authorizedOrder();

        return view('livewire.service-order-panel', ['order' => $order, 'types' => ServiceOrder::coffinTypes()]);
    }
}
