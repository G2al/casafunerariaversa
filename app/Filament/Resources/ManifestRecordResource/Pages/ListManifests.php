<?php

namespace App\Filament\Resources\ManifestRecordResource\Pages;

use App\Filament\Resources\ManifestRecordResource;
use App\Services\ManifestRecords;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListManifests extends ListRecords
{
    protected static string $resource = ManifestRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('create')->label('Nuovo manifesto')->icon('heroicon-o-plus')->action(fn () => redirect(ManifestRecordResource::getUrl('editor', ['record' => app(ManifestRecords::class)->createDraft()])))];
    }
}
