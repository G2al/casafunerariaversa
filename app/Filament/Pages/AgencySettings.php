<?php

namespace App\Filament\Pages;

use App\Filament\Support\PrivateUpload;
use App\Models\CompanySettings;
use App\Services\PrivateImage;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AgencySettings extends Page
{
    protected static ?string $title = 'Impostazioni agenzia';

    protected static string|\UnitEnum|null $navigationGroup = 'Amministrazione';

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.agency-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', CompanySettings::class);
        $this->form->fill(CompanySettings::current());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->model(CompanySettings::find(1) ?? new CompanySettings)->components([
            Section::make('Dati aziendali verificati')->description('Inserisci i dati ufficiali. La sede della camera ardente è distinta dalla sede della società.')->schema([
                TextInput::make('legal_name')->label('Ragione sociale')->maxLength(255), TextInput::make('trade_name')->label('Nome commerciale')->maxLength(255),
                TextInput::make('tax_number')->label('Partita IVA / codice fiscale')->maxLength(30), TextInput::make('company_address')->label('Indirizzo società')->maxLength(255),
                TextInput::make('phone')->label('Telefono')->tel()->maxLength(50), TextInput::make('email')->label('Email')->email()->maxLength(255),
                PrivateUpload::make('logo_path', 'Logo', 'company')->preventFilePathTampering(allowFilePathUsing: fn (string $file) => $file === (CompanySettings::current()['logo_path'] ?? null)),
                Textarea::make('legal_details')->label('Dati legali / autorizzazioni da verificare')->maxLength(4000),
            ])->columns(2),
            Section::make('Camera ardente e manifesto')->schema([
                TextInput::make('viewing_venue')->label('Nome sede casa funeraria')->maxLength(255)->helperText('Precompilato nelle nuove pratiche; può essere modificato per il singolo servizio.'), TextInput::make('viewing_address')->label('Indirizzo casa funeraria')->maxLength(255)->helperText('Indirizzo della camera ardente, precompilato nelle nuove pratiche. Verifica il riferimento iniziale Via Savoia 31.'),
                Textarea::make('closing_text')->label('Messaggio finale del manifesto')->maxLength(500),
                TextInput::make('intro_male')->label('Introduzione maschile')->maxLength(250)->placeholder('È venuto a mancare all’affetto dei suoi cari il Signor'),
                TextInput::make('intro_female')->label('Introduzione femminile')->maxLength(250)->placeholder('È venuta a mancare all’affetto dei suoi cari la Signora'),
                TextInput::make('safe_left')->label('Margine sinistro (%)')->numeric()->minValue(23)->maxValue(60)->required()->helperText('Inizio del testo da sinistra: lascia libera la figura religiosa della cornice.'),
                TextInput::make('safe_top')->label('Margine superiore (%)')->numeric()->minValue(2)->maxValue(30)->required()->helperText('Inizio del testo dall’alto. Le percentuali si riferiscono all’area stampabile, già interna ai margini di stampa.'),
                TextInput::make('safe_width')->label('Larghezza zona testo (%)')->numeric()->minValue(30)->maxValue(75)->required()->helperText('Spazio orizzontale disponibile per i testi del manifesto.'),
                TextInput::make('safe_height')->label('Altezza zona testo (%)')->numeric()->minValue(30)->maxValue(94)->required()->helperText('Spazio verticale disponibile. Mantieni i valori iniziali se le cornici hanno la geometria dei campioni.'),
            ])->columns(2),
        ]);
    }

    public function save(): void
    {
        Gate::authorize('create', CompanySettings::class);
        $data = $this->form->getState();
        if ($data['safe_left'] + $data['safe_width'] > 98 || $data['safe_top'] + $data['safe_height'] > 98) {
            throw ValidationException::withMessages(['data.safe_width' => 'La zona testo deve rimanere entro il 98% della pagina.']);
        }
        if ($data['logo_path'] ?? null) {
            app(PrivateImage::class)->dataUri($data['logo_path']);
        }
        CompanySettings::updateOrCreate(['id' => 1], ['data' => $data]);
        Notification::make()->success()->title('Impostazioni salvate')->send();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('save')->label('Salva impostazioni')->action('save')];
    }
}
