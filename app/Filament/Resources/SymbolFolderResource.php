<?php

namespace App\Filament\Resources;

use App\Filament\Support\DeletionActions;
use App\Models\SymbolFolder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SymbolFolderResource extends Resource
{
    protected static ?string $model = SymbolFolder::class;

    protected static ?string $modelLabel = 'Cartella simboli';

    protected static ?string $pluralModelLabel = 'Cartelle simboli';

    protected static ?string $navigationLabel = 'Simboli';

    protected static string|\UnitEnum|null $navigationGroup = 'Risorse del servizio';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('Nome cartella')->required()->maxLength(255)->unique(ignoreRecord: true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Cartella')->icon('heroicon-o-folder')->searchable()->sortable(), TextColumn::make('symbols_count')->label('Immagini')->counts('symbols')])->defaultSort('name')->recordUrl(fn (SymbolFolder $record) => SymbolResource::getUrl('folder', ['folder' => $record->id]))->recordActions([Action::make('open')->label('Apri cartella')->icon('heroicon-o-folder-open')->url(fn (SymbolFolder $record) => SymbolResource::getUrl('folder', ['folder' => $record->id])), EditAction::make()->label('Rinomina'), DeleteAction::make()->disabled(fn (SymbolFolder $record) => $record->isUsed())->tooltip('Puoi eliminare una cartella dopo aver spostato o eliminato i suoi simboli.')])->toolbarActions([BulkActionGroup::make([DeletionActions::bulk()->modalDescription('Verranno eliminate solo le cartelle vuote. Sposta o elimina prima le immagini delle altre cartelle.')])])->emptyStateHeading('Crea la prima cartella')->emptyStateDescription('Apri una cartella per caricare più immagini insieme.');
    }

    public static function getPages(): array
    {
        return ['index' => SymbolFolderResource\Pages\ManageFolders::route('/')];
    }
}
