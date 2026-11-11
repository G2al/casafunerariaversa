<?php

namespace App\Filament\Resources\FuneralCaseResource\Pages;

use App\Filament\Resources\FuneralCaseResource;
use App\Filament\Support\CommitsVehicle;
use App\Models\FuneralCase;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCase extends CreateRecord
{
    use CommitsVehicle;

    protected function handleRecordCreation(array $data): Model
    {
        $record = new FuneralCase($data);
        $record->allowRoomOverlap = (bool) ($this->data['override_room_conflict'] ?? false);
        $record->save();

        return $record;
    }

    protected static string $resource = FuneralCaseResource::class;

    protected static ?string $title = 'Nuova pratica funebre';

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
