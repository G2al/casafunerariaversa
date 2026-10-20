<?php

namespace App\Filament\Resources\FuneralCaseResource\Pages;

use App\Filament\Resources\FuneralCaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCase extends CreateRecord
{
    protected static string $resource = FuneralCaseResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
