<?php

namespace App\Filament\Resources\FuneralCaseResource\Pages;

use App\Filament\Resources\FuneralCaseResource;
use App\Filament\Support\CaseActions;
use App\Filament\Support\CommitsVehicle;
use App\Filament\Support\FuneralCaseDeletionActions;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCase extends EditRecord
{
    use CommitsVehicle;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->allowRoomOverlap = (bool) ($this->data['override_room_conflict'] ?? false);
        $record->update($data);

        return $record;
    }

    protected static string $resource = FuneralCaseResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return FuneralCaseResource::prepareFormData($data);
    }

    protected function getHeaderActions(): array
    {
        return [...CaseActions::make(), FuneralCaseDeletionActions::single()->unsavedChangesAlert(false), FuneralCaseDeletionActions::forceSingle()->unsavedChangesAlert(false), RestoreAction::make()->unsavedChangesAlert(false)];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // I campi delle sezioni nascoste restano disponibili quando si cambia procedura.
        $data['cremation_details'] = array_replace($this->getRecord()->cremation_details ?? [], $data['cremation_details'] ?? []);

        return $data;
    }
}
