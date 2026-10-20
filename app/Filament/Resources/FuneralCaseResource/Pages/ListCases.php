<?php

namespace App\Filament\Resources\FuneralCaseResource\Pages;

use App\Filament\Resources\FuneralCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCases extends ListRecords
{
    protected static string $resource = FuneralCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
