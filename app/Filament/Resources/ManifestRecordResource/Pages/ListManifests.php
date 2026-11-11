<?php

namespace App\Filament\Resources\ManifestRecordResource\Pages;

use App\Filament\Resources\ManifestRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListManifests extends ListRecords
{
    protected static string $resource = ManifestRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuovo manifesto senza pratica')];
    }
}
