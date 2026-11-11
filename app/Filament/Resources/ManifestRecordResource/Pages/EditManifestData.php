<?php

namespace App\Filament\Resources\ManifestRecordResource\Pages;

use App\Filament\Resources\ManifestRecordResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditManifestData extends EditRecord
{
    protected static string $resource = ManifestRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('editor')->label('Modifica grafica')->url(fn () => static::getResource()::getUrl('editor', ['record' => $this->getRecord()])), Action::make('preview')->label('Anteprima / stampa')->url(fn () => route('private.manifests.preview', $this->getRecord()))->openUrlInNewTab()];
    }
}
