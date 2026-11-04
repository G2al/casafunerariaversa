<?php

namespace App\Filament\Resources;

use App\Filament\Support\DeletionActions;
use App\Filament\Support\PrivateUpload;
use App\Models\Symbol;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SymbolResource extends Resource
{
    protected static ?string $model = Symbol::class;

    protected static ?string $modelLabel = 'Simbolo';

    protected static ?string $pluralModelLabel = 'Simboli';

    protected static string|\UnitEnum|null $navigationGroup = 'Risorse del servizio';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('symbol_folder_id')->label('Cartella')->relationship('folder', 'name')->required()->searchable()->preload()->default(fn ($livewire) => $livewire->folderId),
            TextInput::make('title')->label('Titolo')->required()->maxLength(255), PrivateUpload::make('image_path', 'Immagine del simbolo', 'symbols')->required(), TextInput::make('sort_order')->label('Ordine')->integer()->minValue(0)->default(0)->required(), Toggle::make('is_active')->label('Attivo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query, $livewire) => $query->where('symbol_folder_id', $livewire->folderId))->columns([ImageColumn::make('preview')->label('Anteprima')->state(fn (Symbol $record) => route('private.symbol', $record)), TextColumn::make('title')->label('Titolo')->searchable(), TextColumn::make('sort_order')->label('Ordine')->sortable(), IconColumn::make('is_active')->label('Attivo')->boolean()])->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()])->toolbarActions([BulkActionGroup::make([DeletionActions::bulk()])]);
    }

    public static function getPages(): array
    {
        return ['index' => SymbolResource\Pages\ManageSymbols::route('/'), 'folder' => SymbolResource\Pages\ManageSymbols::route('/folders/{folder}')];
    }
}
