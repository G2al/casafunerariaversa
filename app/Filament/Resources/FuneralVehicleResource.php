<?php

namespace App\Filament\Resources;

use App\Models\FuneralVehicle;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FuneralVehicleResource extends Resource
{
    protected static ?string $model = FuneralVehicle::class;

    protected static ?string $modelLabel = 'Mezzo';

    protected static ?string $pluralModelLabel = 'Mezzi';

    protected static string|\UnitEnum|null $navigationGroup = 'Risorse del servizio';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label('Descrizione')->required()->maxLength(255), TextInput::make('plate')->label('Targa')->required()->maxLength(30), TextInput::make('credentials')->label('Estremi idoneità sanitaria')->maxLength(255), Toggle::make('is_active')->label('Attivo')->default(true)->helperText('Disattiva i mezzi già associati a pratiche per conservarne lo storico.')]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withExists('funeralCases'))->columns([TextColumn::make('name')->label('Descrizione')->searchable(), TextColumn::make('plate')->label('Targa')->searchable(), IconColumn::make('is_active')->label('Attivo')->boolean()])->recordActions([
            EditAction::make(),
            DeleteAction::make()->disabled(fn (FuneralVehicle $record) => $record->isUsed())
                ->tooltip(fn (FuneralVehicle $record) => $record->isUsed() ? 'Già associato a una pratica: disattivalo da Modifica.' : 'Elimina definitivamente questo mezzo.'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => FuneralVehicleResource\Pages\ManageVehicles::route('/')];
    }
}
