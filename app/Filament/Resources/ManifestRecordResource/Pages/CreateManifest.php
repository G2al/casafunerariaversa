<?php

namespace App\Filament\Resources\ManifestRecordResource\Pages;

use App\Filament\Resources\ManifestRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateManifest extends CreateRecord
{
    protected static string $resource = ManifestRecordResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
