<?php

namespace App\Filament\Resources\FuneralCaseResource\Pages;

use App\Filament\Resources\FuneralCaseResource;
use App\Filament\Support\CaseActions;
use App\Filament\Support\CommitsVehicle;
use App\Filament\Support\FuneralCaseDeletionActions;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCase extends EditRecord
{
    use CommitsVehicle;

    protected static string $resource = FuneralCaseResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return FuneralCaseResource::prepareFormData($data);
    }

    protected function getHeaderActions(): array
    {
        return [...CaseActions::make(), FuneralCaseDeletionActions::single(), FuneralCaseDeletionActions::forceSingle(), RestoreAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // I campi delle sezioni nascoste restano disponibili quando si cambia procedura.
        $data['cremation_details'] = array_replace($this->getRecord()->cremation_details ?? [], $data['cremation_details'] ?? []);

        return $data;
    }
}
