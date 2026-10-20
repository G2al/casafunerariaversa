<?php

namespace App\Filament\Resources\FuneralCaseResource\Pages;

use App\Filament\Resources\FuneralCaseResource;
use App\Filament\Support\CaseActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCase extends ViewRecord
{
    protected static string $resource = FuneralCaseResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return FuneralCaseResource::prepareFormData($data);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), ...CaseActions::make()];
    }
}
