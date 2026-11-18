<?php

namespace App\Filament\Resources\ServiceOrderResource\Pages;

use App\Filament\Resources\ServiceOrderResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;

class ManageServiceOrder extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ServiceOrderResource::class;

    protected string $view = 'filament.pages.service-order';

    protected static ?string $title = 'Ordine di servizio';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        Gate::authorize('view', $this->record);
    }
}
