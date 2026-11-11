<?php

namespace App\Filament\Resources;

use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Filament\Resources\FuneralCaseResource\RelationManagers\DocumentsRelationManager;
use App\Filament\Support\CaseActions;
use App\Filament\Support\CaseAttachmentForm;
use App\Filament\Support\FuneralCaseDeletionActions;
use App\Filament\Support\FuneralPacketForm;
use App\Filament\Support\IdentityDocumentForm;
use App\Filament\Support\ManifestLinkForm;
use App\Filament\Support\PrivateUpload;
use App\Filament\Support\VehicleCommitmentForm;
use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\Room;
use App\Models\User;
use App\Services\CsvExport;
use App\Services\ReservationConflicts;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
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
use Filament\Schemas\Components\Utilities\Set;
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
        $groups = [];
        foreach (config('funeral.sections') as $name => $fields) {
            $groups[$name] ??= [];
            foreach ($fields as $field => $label) {
                if ($field === 'authorization_number') {
                    continue;
                }
                $input = match (true) {
                    $field === 'sex' => Select::make($field)->options(['male' => 'Maschio', 'female' => 'Femmina']),
                    $field === 'burial_type' => Select::make($field)->options(['burial' => 'Inumazione', 'entombment' => 'Tumulazione', 'other' => 'Altro']),
                    str_ends_with($field, '_date') => DatePicker::make($field)->native(true)->displayFormat('d/m/Y')->maxDate(today()),
                    str_ends_with($field, '_at') => DateTimePicker::make($field)->native(true)->seconds(false)->displayFormat('d/m/Y H:i'),
                    $field === 'death_time' => TimePicker::make($field)->seconds(false),
                    $field === 'death_cause' => Textarea::make($field)->maxLength(5000)->helperText('Riservata: mai stampata.'),
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
                if (in_array($field, ['viewing_address', 'viewing_venue'], true)) {
                    $input->default(fn () => CompanySettings::current()[$field] ?? null);
                }
                if (in_array($field, ['first_name', 'last_name', 'viewing_starts_at', 'viewing_ends_at', 'transport_at', 'competent_city', 'burial_type'], true)) {
                    $input->live(onBlur: true);
                    if (in_array($field, ['viewing_starts_at', 'viewing_ends_at', 'transport_at'], true)) {
                        $input->afterStateUpdated(fn (Set $set) => $set($field === 'transport_at' ? 'override_vehicle_conflict' : 'override_room_conflict', false));
                    }
                }
                if ($field === 'burial_type') {
                    $input->afterStateUpdated(function ($state, Set $set) {
                        $set('cremation_details.transport_purpose', ['entombment' => 'Tumulato', 'burial' => 'Inumato'][$state] ?? null);
                    });
                }
                if ($field === 'competent_city') {
                    $input->afterStateUpdated(function ($state, Set $set) {
                        foreach (['transport_authorization_issuer', 'report_place', 'declaration_place'] as $f) {
                            $set('cremation_details.'.$f, $state);
                        }
                    });
                }
                $target = $name;
                if ($name === 'Camera ardente e rito' && ! str_starts_with($field, 'viewing_')) {
                    continue;
                }
                $groups[$target][] = $input->label($label);
            }
        }
        // I campi trasferiti dal rito precedono quelli specifici del trasporto.
        $groups['Trasporto'] = array_merge(
            array_map(fn ($field, $label) => match ($field) {
                'burial_type' => Select::make($field)->label($label)->options(['burial' => 'Inumazione', 'entombment' => 'Tumulazione', 'other' => 'Altro'])->live()->afterStateUpdated(fn ($state, Set $set) => $set('cremation_details.transport_purpose', ['entombment' => 'Tumulato', 'burial' => 'Inumato'][$state] ?? null)),
                'ceremony_at','transfer_at' => DateTimePicker::make($field)->label($label)->seconds(false),
                default => TextInput::make($field)->label($label)->maxLength(255),
            }, ['church', 'ceremony_city', 'ceremony_at', 'transfer_place', 'transfer_at', 'cemetery', 'burial_type'], ['Chiesa / parrocchia', 'Località del rito', 'Data e ora esequie', 'Luogo del trasferimento', 'Data e ora trasferimento', 'Cimitero di destinazione', 'Tipo di sepoltura']),
            $groups['Trasporto']
        );
        $groups['Defunto'][] = PrivateUpload::make('photo_path', 'Fotografia del defunto (facoltativa)', 'photos');
        $groups['Camera ardente e rito'][] = Select::make('room_id')->label('Stanza')->options(fn () => Room::orderBy('name')->pluck('name', 'id'))->searchable()->live()->afterStateUpdated(fn (Set $set) => $set('override_room_conflict', false));
        $groups['Camera ardente e rito'][] = View::make('filament.forms.room-conflicts')->columnSpanFull();
        $groups['Camera ardente e rito'][] = Toggle::make('override_room_conflict')->visible(fn (Get $get, ?FuneralCase $record) => app(ReservationConflicts::class)->rooms($get('room_id'), $get('viewing_starts_at'), $get('viewing_ends_at'), $record?->id)->isNotEmpty())->label('Confermo a mia responsabilità l’occupazione della stanza anche in sovrapposizione')->default(false)->dehydrated(false);
        $groups['Camera ardente e rito'][] = FuneralPacketForm::priorTransfer();
        $groups['Trasporto'][] = TextInput::make('transport_distance_km')->label('Distanza dichiarata (km)')->numeric()->minValue(0)->maxValue(999999.99)->default(100);
        $groups['Trasporto'][] = DateTimePicker::make('transport_ends_at')->label('Fine impegno mezzo')->seconds(false)->after('transport_at')->live(onBlur: true)->afterStateUpdated(fn (Set $set) => $set('override_vehicle_conflict', false));
        $groups['Trasporto'][] = Select::make('technical_director_id')->label('Direttore tecnico')->relationship('technicalDirector', 'name', fn (Builder $q) => $q->where('is_active', true)->where('is_technical_director', true))->searchable()->preload()->live();
        $groups['Trasporto'][] = Select::make('vehicle_id')->label('Mezzo')->relationship('vehicle', 'plate', fn (Builder $q) => $q->where('is_active', true))->searchable()->preload()->live()->afterStateUpdated(fn (Set $set) => $set('override_vehicle_conflict', false));
        $groups['Trasporto'][] = Select::make('staff_member_id')->label('Operatore incaricato del trasporto')->relationship('staffMember', 'name', fn (Builder $q) => $q->where('is_active', true))->searchable()->preload();
        $groups['Trasporto'][] = Select::make('transportStaff')->label('Personale da riportare nel documento')->relationship('transportStaff', 'name', fn (Builder $q) => $q->where('is_active', true))->multiple()->searchable()->preload()->maxItems(16);
        $groups['Trasporto'] = array_merge($groups['Trasporto'], VehicleCommitmentForm::components());
        $groups['Familiare referente'][] = FuneralPacketForm::familyDocument();
        $groups['Familiare referente'][] = FuneralPacketForm::familyDelegant();
        $defunto = [Section::make('Dati del defunto')->schema($groups['Defunto'])->columns(2), IdentityDocumentForm::section(), CaseAttachmentForm::section(),
            Section::make('Camera ardente')->schema($groups['Camera ardente e rito'])->columns(2),
            Section::make('Trasporto, rito e documentazione')->schema(array_merge($groups['Trasporto'], FuneralPacketForm::sections()))->columns(2),
            Section::make('Cremazione')->schema($groups['Cremazione'])->columns(2)->visible(fn (Get $get) => in_array($get('type'), ['cremation', CaseType::Cremation], true)),
            ManifestLinkForm::section(),
            View::make('filament.forms.editor-file-actions')->columnSpanFull(),
        ];

        return $schema->components([
            Section::make('Pratica')->schema([
                Select::make('type')->label('Procedura')->options(CaseType::class)->required()->live(),
                Hidden::make('transport_enabled')->default(true)->formatStateUsing(fn () => true)->dehydrateStateUsing(fn () => true),
                Select::make('status')->label('Stato')->options(CaseStatus::class)->required()->default(CaseStatus::Draft),
                DateTimePicker::make('registered_at')->label('Presa in carico')->required()->default(now())->seconds(false),
                Select::make('operator_id')->label('Operatore proprietario')->relationship('owner', 'name', fn (Builder $q) => $q->where('is_active', true))->searchable()->preload()->visible(fn () => auth()->user()?->isAdmin()),
            ])->columns(2)->columnSpanFull(),
            Tabs::make('Scheda')->tabs([
                Tab::make('Defunto')->schema($defunto),
                Tab::make('Familiare delegante')->schema([Section::make('Familiare delegante')->schema($groups['Familiare referente'])->columns(2)]),
                Tab::make('Note interne')->schema([Textarea::make('internal_notes')->label('Note interne')->maxLength(10000)->rows(8)]),
            ])->columnSpanFull()->persistTabInQueryString(),
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
        unset($data['manifest_layout'], $data['prelapide_layout']);

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
            FuneralCaseDeletionActions::single()->unsavedChangesAlert(false), FuneralCaseDeletionActions::forceSingle()->unsavedChangesAlert(false), RestoreAction::make()->unsavedChangesAlert(false),
        ])->toolbarActions([
            BulkActionGroup::make([
                FuneralCaseDeletionActions::bulk(), FuneralCaseDeletionActions::forceBulk(),
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
        return ['index' => FuneralCaseResource\Pages\ListCases::route('/'), 'create' => FuneralCaseResource\Pages\CreateCase::route('/create'), 'prelapide-editor' => FuneralCaseResource\Pages\EditPrelapide::route('/{record}/prelapide-editor'), 'manifest-editor' => FuneralCaseResource\Pages\EditManifest::route('/{record}/manifest-editor'), 'view' => FuneralCaseResource\Pages\ViewCase::route('/{record}'), 'edit' => FuneralCaseResource\Pages\EditCase::route('/{record}/edit')];
    }
}
