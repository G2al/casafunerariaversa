<?php

namespace App\Filament\Support;

use App\Enums\CaseType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

class FuneralPacketForm
{
    public static function sections(): array
    {
        return [
            Section::make('Domanda di autorizzazione — pagina 1')->description('Completa i dati specifici della domanda. Puoi salvare la bozza anche senza averli ancora raccolti.')->schema([
                Select::make('technical_director_id')->label('Direttore tecnico / richiedente')->relationship('technicalDirector', 'name', fn (Builder $query) => $query->where('is_active', true)->where('is_technical_director', true))->searchable()->preload()->helperText('Anagrafica e abilitazione si gestiscono da Personale trasporto.'),
                Select::make('transport_scope')->label('Ambito del trasporto')->options(['within_city' => 'Nell’ambito comunale', 'other_city' => 'In altro Comune']),
                TextInput::make('transport_destination_city')->label('Comune di destinazione')->maxLength(255)->helperText('Il Comune in cui si trova la destinazione, distinto dal nome del crematorio.'),
                TextInput::make('transport_distance_km')->label('Distanza dichiarata (km)')->numeric()->minValue(0)->maxValue(999999.99)->helperText('Inserisci la distanza effettiva dichiarata. La dicitura «meno di 100 Km» viene usata solo per distanze inferiori a 100.'),
                TextInput::make('transport_request_note')->label('Indicazione aggiuntiva della domanda (riga 5)')->maxLength(255)->columnSpanFull(),
                TextInput::make('transport_attachment_extra_1')->label('Allegato aggiuntivo (riga 6)')->maxLength(255),
                TextInput::make('transport_attachment_extra_2')->label('Allegato aggiuntivo (riga 7)')->maxLength(255)->helperText('Descrizioni da stampare. Il gestionale non allega automaticamente questi file.'),
            ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Traslazione precedente già effettuata')->description('Se applicabile: dati del trasferimento già avvenuto, distinti dalla data e dal luogo del trasferimento previsto per il rito. Altrimenti lascia vuoto.')->collapsible()->collapsed()->schema([
                TextInput::make('prior_transfer_destination')->label('La salma è stata traslata presso')->maxLength(255),
                DatePicker::make('prior_transfer_date')->label('Data della traslazione effettuata')->displayFormat('d/m/Y')->maxDate(today())->afterOrEqual(fn (Get $get) => $get('death_date')),
                TextInput::make('certifying_doctor')->label('Medico della certificazione')->maxLength(255),
                Select::make('prior_transfer_vehicle_id')->label('Mezzo utilizzato per la traslazione precedente')->relationship('priorTransferVehicle', 'plate', fn (Builder $query) => $query->where('is_active', true))->searchable()->preload(),
            ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Identificazione del defunto — pagina 2')->schema([
                Select::make('cremation_details.identification_method')->label('Modalità di identificazione')->options(['document' => 'Documento del defunto', 'witness' => 'Conoscenza diretta tramite testimone'])->live()->helperText('Compila i dati del documento oppure del testimone, secondo il caso.'),
                Placeholder::make('identity_document_hint')->label('Documento del defunto')->content('Gli estremi e i caricamenti fronte/retro si compilano nel tab Documento del defunto.')->visible(fn (Get $get) => $get('cremation_details.identification_method') === 'document'),
                Section::make('Testimone')->visible(fn (Get $get) => $get('cremation_details.identification_method') === 'witness')->schema([
                    self::text('witness_name', 'Nome e cognome del testimone'),
                    self::text('witness_relationship', 'In qualità di / rapporto con il defunto'),
                    ...self::documentFields('witness'),
                ])->columns(2)->columnSpanFull(),
            ])->columns(2),
            Section::make('Feretro e verbale — pagina 2')->schema([
                Select::make('cremation_details.coffin_type')->label('Confezionamento del feretro')->options([
                    'wood' => 'Sola cassa di legno foderata internamente',
                    'biodegradable' => 'Cassa di legno con contenitore biodegradabile',
                    'double' => 'Duplice cassa di legno e zinco',
                ])->live(),
                self::text('wooden_lining', 'Fodera interna della cassa di legno')->visible(fn (Get $get) => in_array($get('cremation_details.coffin_type'), ['wood', 'biodegradable'], true)),
                self::text('sanitary_precautions', 'Eventuali particolari precauzioni igienico-sanitarie')->columnSpanFull(),
                self::date('transport_authorization_date', 'Data autorizzazione del singolo trasporto'),
                self::text('transport_authorization_issuer', 'Comune che autorizza il singolo trasporto')->helperText('Numero ed estremi si compilano nel campo «Estremi autorizzazione» del tab Trasporto.'),
                self::text('report_place', 'Luogo di redazione del verbale'),
                DateTimePicker::make('cremation_details.report_at')->label('Data e ora di redazione del verbale')->seconds(false)->displayFormat('d/m/Y H:i'),
            ])->description('Il personale e il mezzo sono quelli selezionati nel tab Trasporto. L’operatore incaricato è anche il soggetto che prende in consegna il feretro.')->columns(['default' => 1, 'md' => 2]),
            Section::make('Dichiarazione dell’impresa — pagina 3')->schema([
                self::text('declaration_place', 'Luogo della dichiarazione'),
                self::date('declaration_date', 'Data della dichiarazione'),
            ])->description('REA, licenze, INPS e INAIL si configurano una volta nelle Impostazioni agenzia. Nel documento viene ripreso l’elenco del personale selezionato, senza nominativi predefiniti.')->columns(2),
            Section::make('Familiare delegante — pagina 4')->schema([
                self::text('contact_birth_place', 'Luogo di nascita del familiare'),
                self::date('contact_birth_date', 'Data di nascita del familiare')->maxDate(today()),
                self::date('mandate_date', 'Data della delega'),
            ])->description('Nome, cognome, parentela, indirizzo completo di civico, Comune, telefono ed email vengono dal tab Familiare referente. Le firme restano da apporre sul cartaceo.')->columns(['default' => 1, 'md' => 2]),
            Section::make('Finalità del trasporto e destinazione delle ceneri')->description('Campi presenti nelle pagine originali del trasporto normale, mantenuti come richiesto.')->visible(fn (Get $get) => in_array($get('type'), ['standard_transport', CaseType::StandardTransport], true))->schema([
                self::text('transport_purpose', 'Finalità del trasporto (per essere)')->columnSpanFull(),
                self::text('ashes_custodian', 'Le ceneri saranno affidate a'),
                self::text('ashes_placement', 'Le ceneri saranno deposte presso / in'),
                self::text('ashes_city', 'Comune di destinazione delle ceneri'),
                self::text('ashes_address', 'Indirizzo completo di destinazione delle ceneri'),
            ])->columns(['default' => 1, 'md' => 2]),
        ];
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
