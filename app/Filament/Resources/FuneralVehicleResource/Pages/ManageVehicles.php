<?php

namespace App\Filament\Resources\FuneralVehicleResource\Pages;

use App\Filament\Resources\FuneralVehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVehicles extends ManageRecords
{
    protected static string $resource = FuneralVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
