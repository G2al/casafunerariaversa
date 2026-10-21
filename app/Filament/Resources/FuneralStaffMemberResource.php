<?php

namespace App\Filament\Resources;

use App\Filament\Support\DeletionActions;
use App\Models\FuneralStaffMember;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FuneralStaffMemberResource extends Resource
{
    protected static ?string $model = FuneralStaffMember::class;

    protected static ?string $modelLabel = 'Membro del personale';

    protected static ?string $pluralModelLabel = 'Personale trasporto';

    protected static string|\UnitEnum|null $navigationGroup = 'Risorse del servizio';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome e cognome')->required()->maxLength(255),
            TextInput::make('qualification')->label('Qualifica')->maxLength(255),
            TextInput::make('credentials')->label('Estremi abilitazione')->maxLength(255),
            Toggle::make('is_active')->label('Attivo')->default(true)->helperText('Disattiva il personale già associato a pratiche per conservarne lo storico.'),
            Toggle::make('is_technical_director')->label('Abilitato come direttore tecnico')->default(false)->live(),
            Section::make('Anagrafica del direttore tecnico')->description('Dati facoltativi: se mancanti, nei documenti restano gli spazi da compilare a mano.')->visible(fn (Get $get) => $get('is_technical_director'))->schema([
                TextInput::make('birth_place')->label('Luogo di nascita')->maxLength(255),
                DatePicker::make('birth_date')->label('Data di nascita')->displayFormat('d/m/Y')->maxDate(today()),
                TextInput::make('residence_city')->label('Comune di residenza')->maxLength(255),
                TextInput::make('residence_address')->label('Indirizzo di residenza')->maxLength(255),
            ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withExists(['funeralCases', 'directedCases', 'selectedCases']))->columns([TextColumn::make('name')->label('Nome')->searchable(), TextColumn::make('qualification')->label('Qualifica'), IconColumn::make('is_technical_director')->label('Direttore tecnico')->boolean(), IconColumn::make('is_active')->label('Attivo')->boolean()])->recordActions([
            EditAction::make(),
            DeleteAction::make()->disabled(fn (FuneralStaffMember $record) => $record->isUsed())
                ->tooltip(fn (FuneralStaffMember $record) => $record->isUsed() ? 'Già associato a una pratica: disattivalo da Modifica.' : 'Elimina definitivamente questo membro del personale.'),
        ])->toolbarActions([BulkActionGroup::make([DeletionActions::bulk()])->label('Azioni sui selezionati')]);
    }

    public static function getPages(): array
    {
        return ['index' => FuneralStaffMemberResource\Pages\ManageStaff::route('/')];
    }
}
