<?php

namespace App\Filament\Resources\SymbolFolderResource\Pages;

use App\Filament\Resources\SymbolFolderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFolders extends ManageRecords
{
    protected static string $resource = SymbolFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuova cartella')];
    }
}
