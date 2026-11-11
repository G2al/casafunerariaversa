<?php

namespace App\Filament\Support;

use App\Enums\CaseType;
use App\Enums\IdentityDocumentType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;

class FuneralPacketForm
{
    public static function sections(): array
    {
        return [
            Section::make('Domanda di autorizzazione — pagina 1')->visible(fn (Get $get) => (bool) $get('transport_enabled'))->description('Completa i dati specifici della domanda. Puoi salvare la bozza anche senza averli ancora raccolti.')->schema([
                self::text('transport_purpose', 'Finalità del trasporto (per essere)')->visible(fn (Get $get) => in_array($get('type'), ['standard_transport', CaseType::StandardTransport], true))->columnSpanFull(),
                Select::make('transport_scope')->label('Ambito del trasporto')->options(['within_city' => 'Nell’ambito comunale', 'other_city' => 'In altro Comune']),
                TextInput::make('transport_destination_city')->label('Comune di destinazione')->maxLength(255)->helperText('Il Comune in cui si trova la destinazione, distinto dal nome del crematorio.'),
            ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Identificazione del defunto — pagina 2')->visible(fn (Get $get) => (bool) $get('transport_enabled'))->schema([
                Select::make('cremation_details.identification_method')->label('Modalità di identificazione')->options(['document' => 'Documento del defunto', 'witness' => 'Conoscenza diretta tramite testimone'])->live()->helperText('Compila i dati del documento oppure del testimone, secondo il caso.'),
                Placeholder::make('identity_document_hint')->label('Documento del defunto')->content('Gli estremi e i caricamenti fronte/retro si compilano nella sezione Documento del defunto della scheda Defunto.')->visible(fn (Get $get) => $get('cremation_details.identification_method') === 'document'),
                Section::make('Testimone')->visible(fn (Get $get) => $get('cremation_details.identification_method') === 'witness')->schema([
                    self::text('witness_name', 'Nome e cognome del testimone'),
                    self::text('witness_relationship', 'In qualità di / rapporto con il defunto'),
                    ...self::documentFields('witness'),
                ])->columns(2)->columnSpanFull(),
            ])->columns(2),
            Section::make('Feretro e verbale — pagina 2')->visible(fn (Get $get) => (bool) $get('transport_enabled'))->schema([
                Select::make('cremation_details.coffin_type')->label('Confezionamento del feretro')->options([
                    'wood' => 'Sola cassa di legno foderata internamente',
                    'biodegradable' => 'Cassa di legno con contenitore biodegradabile',
                    'double' => 'Duplice cassa di legno e zinco',
                ])->live(),
                self::text('wooden_lining', 'Fodera interna della cassa di legno')->visible(fn (Get $get) => in_array($get('cremation_details.coffin_type'), ['wood', 'biodegradable'], true)),
                self::text('sanitary_precautions', 'Eventuali particolari precauzioni igienico-sanitarie')->columnSpanFull(),
                self::date('transport_authorization_date', 'Data autorizzazione del singolo trasporto')->live()->afterStateUpdated(fn ($state, Set $set) => $set('cremation_details.declaration_date', $state)),
                self::text('transport_authorization_issuer', 'Comune che autorizza il singolo trasporto')->formatStateUsing(fn (Get $get) => $get('competent_city'))->readOnly()->helperText('Compilato dal Comune competente.'),
                self::text('report_place', 'Luogo di redazione del verbale')->formatStateUsing(fn (Get $get) => $get('competent_city'))->readOnly()->helperText('Compilato dal Comune competente.'),
                DateTimePicker::make('cremation_details.report_at')->label('Data e ora di redazione del verbale')->seconds(false)->displayFormat('d/m/Y H:i'),
            ])->description('Il personale e il mezzo sono quelli selezionati nella sezione Trasporto della scheda Defunto. L’operatore incaricato è anche il soggetto che prende in consegna il feretro.')->columns(['default' => 1, 'md' => 2]),
            Section::make('Dichiarazione dell’impresa — pagina 3')->visible(fn (Get $get) => (bool) $get('transport_enabled'))->schema([
                self::text('declaration_place', 'Luogo della dichiarazione')->formatStateUsing(fn (Get $get) => $get('competent_city'))->readOnly()->helperText('Compilato dal Comune competente.'),
                self::date('declaration_date', 'Data della dichiarazione')->formatStateUsing(fn (Get $get) => $get('cremation_details.transport_authorization_date'))->disabled()->dehydrated(false),
            ])->description('REA, licenze, INPS e INAIL si configurano una volta nelle Impostazioni agenzia. Nel documento viene ripreso l’elenco del personale selezionato, senza nominativi predefiniti.')->columns(2),
            Section::make('Destinazione delle ceneri')->visible(fn (Get $get) => in_array($get('type'), ['cremation', CaseType::Cremation], true))->schema([
                self::text('ashes_custodian', 'Le ceneri saranno affidate a'),
                self::text('ashes_placement', 'Le ceneri saranno deposte presso / in'),
                self::text('ashes_city', 'Comune di destinazione delle ceneri'),
                self::text('ashes_address', 'Indirizzo completo di destinazione delle ceneri'),
            ])->columns(['default' => 1, 'md' => 2]),
        ];
    }

    public static function familyDelegant(): Section
    {
        return Section::make('Familiare delegante — delega su richiesta')->schema([
            self::text('contact_birth_place', 'Luogo di nascita del familiare'),
            self::date('contact_birth_date', 'Data di nascita del familiare')->maxDate(today()),
            self::date('mandate_date', 'Data della delega'),
        ])->description('Nome, cognome, parentela, indirizzo completo di civico, Comune, telefono ed email vengono dal tab Familiare delegante. Le firme restano da apporre sul cartaceo.')->columns(['default' => 1, 'md' => 2]);
    }

    public static function familyDocument(): Section
    {
        return Section::make('Documento del familiare delegante')->description('Questi estremi compilano il testimone a pagina 2. Fronte e retro sono facoltativi e privati: JPEG, PNG, WebP o PDF.')->schema([
            Select::make('cremation_details.contact_document_type')->label('Tipologia di documento')->options(IdentityDocumentType::class),
            self::text('contact_document_number', 'Numero di documento'),
            self::text('contact_document_issuer', 'Ente di rilascio'),
            self::date('contact_document_expires_at', 'Scadenza')->afterOrEqual('cremation_details.contact_document_issued_at'),
            self::date('contact_document_issued_at', 'Emissione')->maxDate(today()),
            PrivateDocumentUpload::make('delegate_identity_front_path', 'Fronte documento del delegato', 'delegate-documents', 'private.delegate-identity', 'front'),
            PrivateDocumentUpload::make('delegate_identity_back_path', 'Retro documento del delegato', 'delegate-documents', 'private.delegate-identity', 'back'),
        ])->columns(2)->columnSpanFull();
    }

    public static function priorTransfer(): Section
    {
        return Section::make('Traslazione precedente già effettuata')->description('Se applicabile: dati del trasferimento già avvenuto, distinti dalla data e dal luogo del trasferimento previsto per il rito. Altrimenti lascia vuoto.')->collapsible()->schema([
            Toggle::make('prior_transfer_enabled')->label('Traslazione precedente effettuata')->default(false)->live(),
            Section::make('Dati della traslazione')->visible(fn (Get $get) => (bool) $get('prior_transfer_enabled'))->schema([
                TextInput::make('prior_transfer_destination')->label('La salma è stata traslata presso')->maxLength(255),
                DatePicker::make('prior_transfer_date')->label('Data della traslazione effettuata')->displayFormat('d/m/Y')->maxDate(today())->afterOrEqual(fn (Get $get) => $get('death_date')),
                TextInput::make('certifying_doctor')->label('Medico della certificazione')->maxLength(255),
                Select::make('prior_transfer_vehicle_id')->label('Mezzo utilizzato per la traslazione precedente')->relationship('priorTransferVehicle', 'plate', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload(),
            ])->columns(['default' => 1, 'md' => 2]),
        ])->columnSpanFull();
    }

    private static function text(string $field, string $label): TextInput
    {
        return TextInput::make('cremation_details.'.$field)->label($label)->maxLength(255);
    }

    private static function date(string $field, string $label): DatePicker
    {
        return DatePicker::make('cremation_details.'.$field)->label($label)->displayFormat('d/m/Y');
    }

    private static function documentFields(string $prefix): array
    {
        return [
            self::text($prefix.'_document_type', 'Tipo di documento'),
            self::text($prefix.'_document_number', 'Numero documento'),
            self::text($prefix.'_document_issuer', 'Comune / ente di rilascio'),
            self::date($prefix.'_document_issued_at', 'Data di rilascio')->maxDate(today()),
            self::date($prefix.'_document_expires_at', 'Scadenza documento')->afterOrEqual('cremation_details.'.$prefix.'_document_issued_at'),
        ];
    }
}
