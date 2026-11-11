<?php

namespace App\Filament\Resources\ManifestRecordResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExportsRelationManager extends RelationManager
{
    protected static string $relationship = 'exports';

    protected static ?string $title = 'PDF del manifesto';

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('version')->label('Versione'), TextColumn::make('created_at')->label('Generato')->dateTime('d/m/Y H:i')])->defaultSort('version', 'desc')
            ->recordActions([Action::make('download')->label('Scarica')->url(fn ($record) => route('private.manifests.export', $record))->openUrlInNewTab()]);
    }
}
