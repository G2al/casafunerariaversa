<?php

namespace App\Filament\Resources\FuneralCaseResource\RelationManagers;

use App\Enums\CaseType;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Storico documenti e manifesti';

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->whereHas('funeralCase', fn ($cases) => $cases->visibleTo(auth()->user())))->columns([
            TextColumn::make('kind')->label('Contenuto')->formatStateUsing(fn ($state) => $state === 'manifest' ? 'Manifesto' : 'Documento'),
            TextColumn::make('case_type')->label('Procedura al momento della stampa')->formatStateUsing(fn ($state) => CaseType::from($state)->getLabel()),
            TextColumn::make('version')->label('Versione'), TextColumn::make('created_at')->label('Generato il')->dateTime('d/m/Y H:i'),
            TextColumn::make('author.name')->label('Autore'),
        ])->recordActions([Action::make('download')->label('Scarica PDF')->url(fn ($record) => route('private.document', $record))->openUrlInNewTab()])->defaultSort('created_at', 'desc')->emptyStateHeading('Nessun PDF generato');
    }
}
