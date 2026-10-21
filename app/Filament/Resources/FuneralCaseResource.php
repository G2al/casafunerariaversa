<?php

namespace App\Filament\Resources;

use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Filament\Resources\FuneralCaseResource\RelationManagers\DocumentsRelationManager;
use App\Filament\Support\CaseActions;
use App\Filament\Support\CremationRequestForm;
use App\Filament\Support\PrivateUpload;
use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\ManifestTemplate;
use App\Models\User;
use App\Services\CsvExport;
use App\Services\DocumentData;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FuneralCaseResource extends Resource
{
    protected static ?string $model = FuneralCase::class;

    protected static ?string $modelLabel = 'Pratica funebre';

    protected static ?string $pluralModelLabel = 'Pratiche funebri';

    protected static ?string $recordTitleAttribute = 'code';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|\UnitEnum|null $navigationGroup = 'Operatività';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->visibleTo(auth()->user())->with('owner');

        return auth()->user()?->isAdmin() ? $query->withoutGlobalScopes([SoftDeletingScope::class]) : $query;
    }

    public static function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (config('funeral.sections') as $section => $fields) {
            $components = [];
            foreach ($fields as $field => $label) {
                $input = match (true) {
                    $field === 'sex' => Select::make($field)->options(['male' => 'Maschio', 'female' => 'Femmina']),
                    $field === 'burial_type' => Select::make($field)->options(['burial' => 'Inumazione', 'entombment' => 'Tumulazione', 'other' => 'Altro']),
                    str_ends_with($field, '_date') => DatePicker::make($field)->native(false)->displayFormat('d/m/Y')->maxDate(today()),
                    str_ends_with($field, '_at') => DateTimePicker::make($field)->native(false)->displayFormat('d/m/Y H:i')->seconds(false),
                    $field === 'death_time' => TimePicker::make($field)->seconds(false),
                    $field === 'death_cause' => Textarea::make($field)->maxLength(5000)->helperText('Informazione riservata. Esclusa da manifesti, PDF e CSV.'),
                    default => TextInput::make($field)->maxLength(255),
                };
                if (str_contains($field, 'phone')) {
                    $input->tel();
                }
                if ($field === 'contact_email') {
                    $input->email();
                }
                if ($field === 'birth_date') {
                    $input->beforeOrEqual(fn (Get $get) => $get('death_date'));
                }
                if ($field === 'viewing_ends_at') {
                    $input->afterOrEqual(fn (Get $get) => $get('viewing_starts_at'));
                }
                if ($field === 'viewing_address' || $field === 'viewing_venue') {
                    $input->default(fn () => CompanySettings::current()[$field] ?? null);
                }
                $components[] = $input->label($label);
            }
            if ($section === 'Defunto') {
                $components[] = PrivateUpload::make('photo_path', 'Fotografia del defunto (facoltativa)', 'photos');
            }
            if ($section === 'Familiare referente') {
                $components[] = Repeater::make('family_members')->label('Persone da nominare nel manifesto')->schema([
                    TextInput::make('relationship')->label('Parentela')->maxLength(100), TextInput::make('name')->label('Nome e cognome')->maxLength(255),
                ])->columns(2)->defaultItems(0)->maxItems(30)->columnSpanFull();
            }
            if ($section === 'Trasporto') {
                $components[] = Select::make('staff_member_id')->label('Operatore incaricato del trasporto')->relationship('staffMember', 'name', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload();
                $components[] = Select::make('vehicle_id')->label('Mezzo')->relationship('vehicle', 'plate', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload();
                $components[] = Select::make('transportStaff')->label('Personale da riportare nel documento')->relationship('transportStaff', 'name', fn (Builder $query) => $query->where('is_active', true))->multiple()->searchable()->preload()->maxItems(16)->columnSpanFull()->helperText('Seleziona i nominativi da stampare con qualifica ed estremi di abilitazione, includendo l’operatore incaricato del trasporto.');
            }
            $tab = Tab::make($section)->schema([Section::make($section)->schema($components)->columns(['default' => 1, 'md' => 2])]);
            if ($section === 'Cremazione') {
                $tab->visible(fn (Get $get) => $get('type') === 'cremation' || $get('type') === CaseType::Cremation);
                $tab->schema([Section::make($section)->schema($components)->columns(['default' => 1, 'md' => 2]), ...CremationRequestForm::sections()]);
            }
            $tabs[] = $tab;
        }
        $tabs[] = Tab::make('Manifesto')->schema([
            Section::make('Cornice e contenuti')->description('Salva la pratica prima di aprire l’anteprima. L’anteprima è il PDF effettivo.')->schema([
                Select::make('manifest_template_id')->label('Cornice')->options(fn () => ManifestTemplate::where('is_active', true)->orderBy('sort_order')->pluck('title', 'id'))->searchable()->live()
                    ->helperText('Se il catalogo è vuoto, l’amministratore può caricare le immagini in Cornici manifesti.'),
                View::make('filament.forms.template-gallery')->columnSpanFull(),
                TextInput::make('manifest_options.intro')->label('Introduzione personalizzata')->maxLength(250)->helperText('Vuoto: testo automatico in base al sesso.'),
                Textarea::make('manifest_options.family_text')->label('Testo dell’annuncio')->maxLength(2400)->rows(4)->helperText('Vuoto: composto dall’elenco dei familiari. La modifica vale solo per questo manifesto.'),
                TextInput::make('manifest_options.place')->label('Luogo di pubblicazione')->maxLength(100),
                Toggle::make('manifest_options.show_photo')->label('Mostra fotografia')->default(true),
                Toggle::make('manifest_options.show_viewing')->label('Mostra camera ardente')->default(true),
                Toggle::make('manifest_options.show_ceremony')->label('Mostra rito')->default(true),
                Toggle::make('manifest_options.show_transfer')->label('Mostra trasferimento')->default(true),
                Toggle::make('manifest_options.show_closing')->label('Mostra ringraziamento')->default(true),
            ])->columns(['default' => 1, 'md' => 2]),
        ]);
        $tabs[] = Tab::make('Note interne')->schema([Textarea::make('internal_notes')->label('Note e indicazioni organizzative')->maxLength(10000)->rows(8)->helperText('Mai stampate nei documenti o nei manifesti.')]);

        return $schema->components([
            Section::make('Pratica')->schema([
                Select::make('type')->label('Procedura')->options(CaseType::class)->required()->live()->helperText('Cambiando procedura i dati comuni restano salvati. I PDF precedenti restano nello storico con la vecchia tipologia.'),
                Select::make('status')->label('Stato')->options(CaseStatus::class)->default('draft')->required(),
                DateTimePicker::make('registered_at')->label('Presa in carico')->default(now())->required()->displayFormat('d/m/Y H:i')->seconds(false),
                Select::make('operator_id')->label('Operatore proprietario')->options(fn () => auth()->user()?->isAdmin() ? User::where('is_active', true)->pluck('name', 'id') : [])->searchable()->visible(fn () => auth()->user()?->isAdmin()),
                Placeholder::make('completeness')->label('Completezza dati salvati')->content(function (?FuneralCase $record) {
                    if (! $record) {
                        return 'Puoi salvare una bozza anche senza nominativo. I dati mancanti saranno indicati prima della stampa.';
                    }
                    $service = app(DocumentData::class);

                    return 'Età al decesso: '.($record->age ?? 'non calcolabile').' · Documento: '.(implode(', ', $service->missing($record, 'document')) ?: 'dati completi').' · Manifesto: '.(implode(', ', $service->missing($record, 'manifest')) ?: 'dati completi');
                })->columnSpanFull(),
            ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
            Tabs::make('Scheda')->tabs($tabs)->columnSpanFull()->persistTabInQueryString(),
        ]);
    }

    public static function dateFilters(): array
    {
        return [
            Filter::make('period')->label('Periodo')->schema([
                Select::make('date_field')->label('Data da filtrare')->options(['registered_at' => 'Presa in carico', 'created_at' => 'Creazione', 'death_date' => 'Decesso'])->default('registered_at'),
                DatePicker::make('from')->label('Dal')->displayFormat('d/m/Y'), DatePicker::make('until')->label('Al')->displayFormat('d/m/Y')->afterOrEqual('from'),
            ])->query(function (Builder $query, array $data) {
                $field = in_array($data['date_field'] ?? '', ['registered_at', 'created_at', 'death_date'], true) ? $data['date_field'] : 'registered_at';

                return $query->when($data['from'] ?? null, fn ($q, $value) => $q->whereDate($field, '>=', $value))->when($data['until'] ?? null, fn ($q, $value) => $q->whereDate($field, '<=', $value));
            }),
        ];
    }

    public static function prepareFormData(array $data): array
    {
        $data['manifest_options'] = array_replace([
            'show_photo' => true, 'show_viewing' => true, 'show_ceremony' => true,
            'show_transfer' => true, 'show_closing' => true,
        ], $data['manifest_options'] ?? []);

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Codice')->searchable()->sortable(),
            TextColumn::make('last_name')->label('Cognome')->searchable()->sortable()->placeholder('Bozza'),
            TextColumn::make('first_name')->label('Nome')->searchable()->sortable(),
            TextColumn::make('type')->label('Procedura')->badge(), TextColumn::make('status')->label('Stato')->badge(),
            TextColumn::make('registered_at')->label('Presa in carico')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('death_date')->label('Decesso')->date('d/m/Y')->sortable()->toggleable(),
            TextColumn::make('owner.name')->label('Operatore')->visible(fn () => auth()->user()?->isAdmin()),
        ])->defaultSort('created_at', 'desc')->filters([
            SelectFilter::make('type')->label('Procedura')->options(CaseType::class),
            SelectFilter::make('status')->label('Stato')->options(CaseStatus::class),
            SelectFilter::make('operator_id')->label('Operatore')->options(fn () => auth()->user()?->isAdmin() ? User::pluck('name', 'id') : [])->visible(fn () => auth()->user()?->isAdmin()),
            ...static::dateFilters(), TrashedFilter::make()->visible(fn () => auth()->user()?->isAdmin()),
        ])->recordActions([
            ViewAction::make(), EditAction::make(), ActionGroup::make(CaseActions::make())->label('PDF'),
            DeleteAction::make()->label('Elimina')->tooltip('Sposta la pratica nel cestino, conservando dati e PDF.'), RestoreAction::make(),
        ])->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make()->label('Elimina selezionate')->authorizeIndividualRecords('delete')
                    ->modalDescription('Le pratiche selezionate verranno spostate nel cestino. I dati e i PDF resteranno conservati; l’amministratore potrà ripristinarle.'),
                RestoreBulkAction::make()->label('Ripristina selezionate')->authorizeIndividualRecords('restore'),
            ])->label('Azioni sui selezionati'),
        ])->headerActions([
            Action::make('csv')->label('Esporta CSV filtrato')->action(fn ($livewire) => app(CsvExport::class)->cases($livewire->getFilteredTableQuery())),
        ])->emptyStateHeading('Nessuna pratica')->emptyStateDescription('Crea la prima pratica. Puoi completare i dati in seguito.');
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => FuneralCaseResource\Pages\ListCases::route('/'), 'create' => FuneralCaseResource\Pages\CreateCase::route('/create'), 'view' => FuneralCaseResource\Pages\ViewCase::route('/{record}'), 'edit' => FuneralCaseResource\Pages\EditCase::route('/{record}/edit')];
    }
}
