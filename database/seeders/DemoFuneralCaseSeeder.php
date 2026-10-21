<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\FuneralVehicle;
use App\Models\ManifestTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoFuneralCaseSeeder extends Seeder
{
    private const MARKER = '[DEMO-CREMAZIONE-SEEDER]';

    public function run(): void
    {
        $owner = User::where('role', UserRole::Admin->value)->where('is_active', true)->orderBy('id')->first();
        if (! $owner) {
            throw new \RuntimeException('Serve un amministratore attivo a cui assegnare la pratica dimostrativa.');
        }
        $previousUser = Auth::user();
        Auth::setUser($owner);
        try {
            $case = DB::transaction(function () use ($owner) {
                $existing = FuneralCase::withTrashed()->where('internal_notes', 'like', '%'.self::MARKER.'%')->first();
                if ($existing) {
                    return $existing;
                }
                $this->fillEmptyCompanyFields();
                $vehicle = FuneralVehicle::firstOrCreate(['plate' => 'DEMO-001'], [
                    'name' => 'DEMO — Autofunebre di prova', 'credentials' => 'DEMO — Idoneità sanitaria e libretto di prova', 'is_active' => true,
                ]);
                $today = today();
                $yesterday = $today->copy()->subDay();
                $death = $today->copy()->subDays(2);
                $at = fn (int $hour, int $minute = 0) => $today->copy()->setTime($hour, $minute);

                return FuneralCase::create([
                    'operator_id' => $owner->id, 'type' => 'cremation', 'status' => 'draft', 'registered_at' => now(),
                    'first_name' => 'Mario', 'last_name' => 'Esempio DEMO', 'sex' => 'male',
                    'birth_place' => 'Aversa', 'birth_date' => '1950-03-15',
                    'father_name' => 'Giuseppe Esempio', 'mother_name' => 'Anna Dimostrativa',
                    'residence_address' => 'Via Esempio 10', 'residence_city' => 'Aversa',
                    'marital_status' => 'Coniugato', 'spouse_name' => 'Maria Dimostrativa', 'occupation' => 'Pensionato',
                    'death_place' => 'Aversa', 'death_address' => 'Via Dimostrativa 20', 'death_date' => $death,
                    'death_time' => '09:00', 'death_cause' => 'Dato sanitario fittizio per prova; nessuna persona reale.',
                    'reference_phone' => '0000000000',
                    'contact_first_name' => 'Luca', 'contact_last_name' => 'Esempio', 'contact_relationship' => 'Figlio',
                    'contact_phone' => '0000000000', 'contact_email' => 'familiare@example.test',
                    'contact_address' => 'Via Prova 18', 'contact_city' => 'Aversa',
                    'family_members' => [['relationship' => 'Moglie', 'name' => 'Maria Dimostrativa'], ['relationship' => 'Figlio', 'name' => 'Luca Esempio']],
                    'viewing_venue' => 'DEMO — Camera ardente di prova', 'viewing_address' => 'Via Esempio 31, Aversa',
                    'viewing_starts_at' => $yesterday->copy()->setTime(8, 0), 'viewing_ends_at' => $at(9),
                    'church' => 'DEMO — Parrocchia di prova', 'ceremony_city' => 'Aversa', 'ceremony_at' => $at(10),
                    'transfer_place' => 'DEMO — Parrocchia di prova', 'transfer_at' => $at(9, 30),
                    'cemetery' => 'DEMO — Cimitero di prova', 'burial_type' => 'other',
                    'competent_city' => 'Aversa', 'transport_origin' => 'DEMO — Camera ardente di prova',
                    'transport_destination' => 'DEMO — Crematorio di prova', 'transport_destination_city' => 'Napoli',
                    'transport_route' => 'Aversa → Parrocchia di prova → Crematorio di prova, Napoli',
                    'transport_at' => $at(11), 'transport_scope' => 'other_city', 'transport_distance_km' => 35,
                    'authorization_number' => 'DEMO-AUT-001 — Autorizzazione fittizia di prova',
                    'crematorium' => 'DEMO — Crematorio di prova', 'ashes_destination' => 'DEMO — Urna destinata al cimitero di prova',
                    'vehicle_id' => $vehicle->id, 'prior_transfer_vehicle_id' => $vehicle->id,
                    'prior_transfer_destination' => 'DEMO — Camera ardente di prova', 'prior_transfer_date' => $yesterday,
                    'certifying_doctor' => 'Dott. Medico Dimostrativo',
                    'transport_request_note' => 'DEMO — Pratica esclusivamente dimostrativa.',
                    'transport_attachment_extra_1' => 'DEMO — Allegato aggiuntivo di prova 1',
                    'transport_attachment_extra_2' => 'DEMO — Allegato aggiuntivo di prova 2',
                    'manifest_template_id' => $this->frame()->id,
                    'manifest_options' => [
                        'intro' => 'È venuto a mancare all’affetto dei suoi cari il Signor',
                        'family_text' => 'Ne danno il triste annuncio la moglie Maria, il figlio Luca e i familiari. Annuncio dimostrativo.',
                        'place' => 'Aversa', 'show_photo' => true, 'show_viewing' => true,
                        'show_ceremony' => true, 'show_transfer' => true, 'show_closing' => true,
                    ],
                    'cremation_details' => [
                        'identification_method' => 'document', 'identity_document_type' => 'Carta di identità',
                        'identity_document_number' => 'DEMO-DOC-001', 'identity_document_issuer' => 'Comune di Aversa',
                        'identity_document_issued_at' => $today->copy()->subYears(2)->toDateString(),
                        'identity_document_expires_at' => $today->copy()->addYears(5)->toDateString(),
                        'coffin_type' => 'wood', 'wooden_lining' => 'DEMO — Tessuto di prova',
                        'sanitary_precautions' => 'DEMO — Nessuna particolare precauzione indicata nella prova',
                        'transport_authorization_date' => $yesterday->toDateString(), 'transport_authorization_issuer' => 'Aversa',
                        'report_place' => 'Aversa', 'report_at' => $at(10, 30)->format('Y-m-d H:i:s'),
                        'declaration_place' => 'Aversa', 'declaration_date' => $today->toDateString(),
                        'contact_birth_place' => 'Aversa', 'contact_birth_date' => '1980-05-20', 'mandate_date' => $yesterday->toDateString(),
                    ],
                    'internal_notes' => self::MARKER.' Pratica fittizia. Selezionare direttore tecnico, operatore incaricato e personale; caricare la foto. Conservare questo marcatore per evitare duplicati del seeder.',
                ]);
            });
            $this->command?->info('Pratica DEMO disponibile: '.$case->code.' — '.$case->full_name.' (assegnata al primo amministratore attivo).');
            $this->command?->line('Da completare: direttore tecnico, operatore incaricato, elenco personale e foto. Le impostazioni aziendali già compilate non vengono sostituite.');
        } finally {
            $previousUser ? Auth::setUser($previousUser) : Auth::guard()->forgetUser();
        }
    }

    private function frame(): ManifestTemplate
    {
        $existing = ManifestTemplate::where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
        if ($existing) {
            return $existing;
        }
        $path = 'frames/demo/cornice-prova.jpg';
        if (! Storage::disk('local')->put($path, file_get_contents(base_path('docs/references/09_cornice_gesu.jpg')))) {
            throw new \RuntimeException('Impossibile salvare la cornice dimostrativa.');
        }

        return ManifestTemplate::create(['title' => 'DEMO — Cornice di prova', 'image_path' => $path, 'is_active' => true, 'sort_order' => 999]);
    }

    private function fillEmptyCompanyFields(): void
    {
        $settings = CompanySettings::current();
        $date = today()->subYear()->toDateString();
        foreach ([
            'legal_name' => 'DEMO — Impresa funebre di prova Srl', 'trade_name' => 'DEMO — Agenzia di prova',
            'tax_number' => 'DEMO-PIVA', 'company_address' => 'Via Società Esempio 10', 'company_city' => 'Aversa',
            'phone' => '0000000000', 'email' => 'agenzia@example.test',
            'company_authorization_protocol' => 'DEMO-SCIA-001', 'company_authorization_date' => $date,
            'company_authorization_issuer' => 'DEMO — Comune di Aversa, Ufficio SUAP',
            'chamber_city' => 'Caserta', 'rea_number' => 'DEMO-REA-001',
            'practice_license_protocol' => 'DEMO-LIC-001', 'practice_license_date' => $date,
            'practice_license_issuer' => 'DEMO — Comune di Aversa, Ufficio SUAP',
            'retail_authorization_protocol' => 'DEMO-VEND-001', 'retail_authorization_date' => $date,
            'retail_authorization_issuer' => 'DEMO — Comune di Aversa',
            'inps_position' => 'DEMO-INPS-001', 'inail_position' => 'DEMO-INAIL-001',
        ] as $key => $value) {
            if (blank($settings[$key] ?? null)) {
                $settings[$key] = $value;
            }
        }
        if (filled($settings['publication_company_name'] ?? null) && blank($settings['publication_company_city'] ?? null)) {
            $settings['publication_company_city'] = 'DEMO — Comune di prova';
        }
        CompanySettings::updateOrCreate(['id' => 1], ['data' => $settings]);
    }
}
