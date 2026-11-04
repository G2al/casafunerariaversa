<?php

namespace App\Filament\Resources\SymbolResource\Pages;

use App\Filament\Resources\SymbolResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSymbols extends ManageRecords
{
    protected static string $resource = SymbolResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
