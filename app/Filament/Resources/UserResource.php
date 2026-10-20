<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Utente';

    protected static ?string $pluralModelLabel = 'Utenti';

    protected static string|\UnitEnum|null $navigationGroup = 'Amministrazione';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(255),
            TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
            Select::make('role')->label('Ruolo')->options(UserRole::class)->required()->rules([
                fn (?User $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                    if ($record?->isLastActiveAdmin() && $value !== 'admin') {
                        $fail('Non puoi declassare l’ultimo amministratore.');
                    }
                },
            ]),
            Toggle::make('is_active')->label('Account attivo')->default(true)->rules([
                fn (?User $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                    if ($record?->isLastActiveAdmin() && ! $value) {
                        $fail('Non puoi disabilitare l’ultimo amministratore.');
                    }
                },
            ]),
            TextInput::make('password')->label('Password')->password()->revealable()->minLength(12)->maxLength(128)->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))->afterStateHydrated(fn (TextInput $component) => $component->state(null))->helperText('Almeno 12 caratteri. In modifica lascia vuoto per mantenere la password attuale.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label('Nome')->searchable(), TextColumn::make('email')->label('Email')->searchable(), TextColumn::make('role')->label('Ruolo')->badge(), IconColumn::make('is_active')->label('Attivo')->boolean()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => UserResource\Pages\ManageUsers::route('/')];
    }
}
