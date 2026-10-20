<?php

namespace App\Filament\Resources\FuneralStaffMemberResource\Pages;

use App\Filament\Resources\FuneralStaffMemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStaff extends ManageRecords
{
    protected static string $resource = FuneralStaffMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
