<?php

namespace App\Filament\Resources\FuneralCaseResource\Pages;

use App\Filament\Resources\FuneralCaseResource;
use App\Filament\Support\CaseActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCase extends EditRecord
{
    protected static string $resource = FuneralCaseResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return FuneralCaseResource::prepareFormData($data);
    }

    protected function getHeaderActions(): array
    {
        return [...CaseActions::make(), DeleteAction::make(), RestoreAction::make()];
    }
}
