<?php

namespace App\Filament\Resources;

use App\Models\Room;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $modelLabel = 'Stanza';

    protected static ?string $pluralModelLabel = 'Stanze';

    protected static string|\UnitEnum|null $navigationGroup = 'Risorse del servizio';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('Nome stanza')->required()->maxLength(255)->unique(ignoreRecord: true)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Stanza')->searchable()->sortable()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => RoomResource\Pages\ManageRooms::route('/')];
    }
}
