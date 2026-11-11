<?php

namespace App\Filament\Resources;

use App\Filament\Support\PrivateUpload;
use App\Models\ManifestRecord;
use App\Models\ManifestTemplate;
use App\Services\ManifestRecords;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManifestRecordResource extends Resource
{
    protected static ?string $model = ManifestRecord::class;

    protected static ?string $modelLabel = 'Manifesto';

    protected static ?string $pluralModelLabel = 'Manifesti';

    protected static string|\UnitEnum|null $navigationGroup = 'Operatività';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with('sourceCase:id,code');
    }

    public static function form(Schema $schema): Schema
    {
        $fields = [TextInput::make('data.first_name')->label('Nome')->required()->maxLength(255), TextInput::make('data.last_name')->label('Cognome')->required()->maxLength(255),
            Select::make('data.sex')->label('Sesso')->options(['male' => 'Maschio', 'female' => 'Femmina'])->required(),
            DatePicker::make('data.birth_date')->label('Data di nascita')->maxDate(today()), DatePicker::make('data.death_date')->label('Data del decesso')->required()->maxDate(today()),
            TextInput::make('data.spouse_name')->label('Coniuge')->maxLength(255), TextInput::make('data.marital_status')->label('Stato civile')->maxLength(255),
            PrivateUpload::make('data.photo_path', 'Fotografia (facoltativa)', 'photos'),
            Select::make('data.manifest_template_id')->label('Cornice (facoltativa)')->options(fn () => ManifestTemplate::where('is_active', true)->orderBy('sort_order')->pluck('title', 'id'))->searchable(),
            TextInput::make('data.manifest_options.intro')->label('Introduzione personalizzata')->maxLength(250),
            Textarea::make('data.manifest_options.family_text')->label('Testo dell’annuncio')->maxLength(2400)->rows(4)->columnSpanFull(),
            TextInput::make('data.manifest_options.place')->label('Luogo di pubblicazione')->maxLength(100),
        ];
        foreach (['viewing_venue' => 'Sede camera ardente', 'viewing_address' => 'Indirizzo camera ardente', 'church' => 'Chiesa', 'ceremony_city' => 'Località del rito', 'transfer_place' => 'Luogo del trasferimento'] as $f => $label) {
            $fields[] = TextInput::make('data.'.$f)->label($label)->maxLength(255);
        }
        foreach (['viewing_starts_at' => 'Inizio esposizione', 'viewing_ends_at' => 'Fine esposizione', 'ceremony_at' => 'Data e ora esequie', 'transfer_at' => 'Data e ora trasferimento'] as $f => $label) {
            $fields[] = DateTimePicker::make('data.'.$f)->label($label)->seconds(false);
        }
        foreach (['photo' => 'foto', 'viewing' => 'camera ardente', 'ceremony' => 'rito', 'transfer' => 'trasferimento', 'closing' => 'ringraziamento'] as $f => $label) {
            $fields[] = Toggle::make('data.manifest_options.show_'.$f)->label('Mostra '.$label)->default(true);
        }

        return $schema->components([Section::make('Dati del manifesto')->description('Puoi creare il manifesto senza pratica. Dopo aver salvato, apri l’editor per modificarne la grafica.')->schema($fields)->columns(2), View::make('filament.forms.editor-file-actions')->columnSpanFull()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id')->label('N.')->sortable(), TextColumn::make('data.last_name')->label('Cognome')->searchable(), TextColumn::make('data.first_name')->label('Nome')->searchable(),
            TextColumn::make('data.death_date')->label('Decesso')->date('d/m/Y'), TextColumn::make('sourceCase.code')->label('Pratica collegata')->placeholder('Manifesto autonomo'), TextColumn::make('updated_at')->label('Modificato')->dateTime('d/m/Y H:i')->sortable()])
            ->filters([TernaryFilter::make('source_case_id')->label('Collegamento alla pratica')->nullable()])->defaultSort('updated_at', 'desc')
            ->recordActions([EditAction::make()->label('Dati'), Action::make('editor')->label('Editor')->url(fn (ManifestRecord $r) => self::getUrl('editor', ['record' => $r])),
                Action::make('preview')->label('Anteprima / stampa')->url(fn (ManifestRecord $r) => route('private.manifests.preview', $r))->openUrlInNewTab(),
                Action::make('duplicate')->label('Duplica manifesto')->unsavedChangesAlert(false)->requiresConfirmation()->modalDescription('Viene creato un manifesto autonomo con gli stessi dati e grafica. Nessuna pratica viene duplicata.')
                    ->action(fn (ManifestRecord $record) => redirect(self::getUrl('edit', ['record' => app(ManifestRecords::class)->duplicate($record)]))),
            ]);
    }

    public static function getRelations(): array
    {
        return [ManifestRecordResource\RelationManagers\ExportsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ManifestRecordResource\Pages\ListManifests::route('/'), 'create' => ManifestRecordResource\Pages\CreateManifest::route('/create'), 'edit' => ManifestRecordResource\Pages\EditManifestData::route('/{record}/edit'), 'editor' => ManifestRecordResource\Pages\EditGraphicManifest::route('/{record}/editor')];
    }
}
