<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AuditEvent;
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
    public function run(): void
    {
        $owner = User::where('role', UserRole::Admin->value)->where('is_active', true)->orderBy('id')->first();
        if (! $owner) {
            throw new \RuntimeException('Serve un amministratore attivo a cui assegnare le pratiche.');
        }

        $previousUser = Auth::user();
        Auth::setUser($owner);
        try {
            $cases = DB::transaction(function () use ($owner) {
                $this->fillCompanyFields();
                $cases = [];
                foreach (['cremation', 'standard_transport'] as $type) {
                    $reference = 'realistic_'.$type.'_v2';
                    $seededId = AuditEvent::where('event', 'seeded_sample_case')->where('changed_fields->seed_reference', $reference)->value('funeral_case_id');
                    $existing = $seededId ? FuneralCase::withTrashed()->find($seededId) : null;
                    if ($existing) {
                        $cases[] = $existing;

                        continue;
                    }
                    $case = FuneralCase::create($this->caseData($type, $owner->id));
                    AuditEvent::create(['user_id' => $owner->id, 'funeral_case_id' => $case->id, 'event' => 'seeded_sample_case', 'changed_fields' => ['seed_reference' => $reference]]);
                    $cases[] = $case;
                }

                return $cases;
            });
            foreach ($cases as $case) {
                $this->command?->info($case->code.' — '.$case->full_name.' — '.$case->type->getLabel());
            }
            $this->command?->line('Due pratiche con dati inventati e flussi aggiornati. Trasporto attivo, distanza iniziale 100 km; traslazione precedente attiva solo nella cremazione.');
            $this->command?->line('Da inserire manualmente: documento di identità, foto, direttore tecnico e personale, certificato di morte e modello ISTAT.');
            $this->command?->line('Puoi provare Documento, Manifesto e Prelapide. Impostando Trasporto = No, Documento genera la sola delega. Le pratiche già create sono conservate senza sovrascriverle.');
        } finally {
            $previousUser ? Auth::setUser($previousUser) : Auth::guard()->forgetUser();
        }
    }

    private function caseData(string $type, int $ownerId): array
    {
        $female = $type === 'standard_transport';
        $today = today();
        $prior = $today->copy()->subDay();
        $death = $today->copy()->subDays($female ? 2 : 3);
        $at = fn (int $hour, int $minute = 0) => $today->copy()->setTime($hour + ($female ? 2 : 0), $minute);
        $contact = $female ? 'Elisa Romano' : 'Lorenzo Rinaldi';
        $church = $female ? 'Parrocchia di Santa Lucia' : 'Parrocchia di San Michele';
        $crematorium = $female ? 'Centro crematorio Santa Lucia' : 'Centro crematorio San Lorenzo';
        $destinationCity = $female ? 'Carinaro' : 'Castel Volturno';
        $cemetery = $female ? 'Cimitero comunale di Carinaro' : 'Cimitero comunale di Aversa';
        $vehicle = $this->vehicle();
        $venue = 'Casa funeraria Aversa';
        $venueAddress = 'Via dei Platani 31, Aversa';

        return [
            'operator_id' => $ownerId, 'type' => $type, 'status' => 'draft', 'transport_enabled' => true, 'prior_transfer_enabled' => ! $female,
            'photo_path' => null, 'identity_document_type' => null, 'identity_front_path' => null, 'identity_back_path' => null,
            'death_certificate_path' => null, 'istat_path' => null,
            'technical_director_id' => null, 'staff_member_id' => null,
            'registered_at' => $death->copy()->setTime(15, 30),
            'first_name' => $female ? 'Teresa' : 'Antonio', 'last_name' => $female ? 'Moretti' : 'Rinaldi',
            'sex' => $female ? 'female' : 'male', 'birth_place' => $female ? 'Caserta' : 'Aversa',
            'birth_date' => $female ? '1952-09-23' : '1947-04-18',
            'father_name' => $female ? 'Pietro Moretti' : 'Carlo Rinaldi',
            'mother_name' => $female ? 'Giulia Esposito' : 'Elena Gallo',
            'residence_address' => $female ? 'Via delle Magnolie 16' : 'Via dei Tigli 24', 'residence_city' => 'Aversa',
            'marital_status' => $female ? 'Vedova' : 'Coniugato',
            'spouse_name' => $female ? 'Giovanni Romano' : 'Anna De Luca',
            'occupation' => $female ? 'Insegnante in pensione' : 'Impiegato in pensione',
            'death_place' => 'Aversa', 'death_address' => $female ? 'Via delle Magnolie 16' : 'Via dei Tigli 24',
            'death_date' => $death, 'death_time' => $female ? '07:45' : '09:20',
            'death_cause' => 'Arresto cardiocircolatorio', 'reference_phone' => $female ? '333 614 7820' : '335 728 4160',
            'contact_first_name' => $female ? 'Elisa' : 'Lorenzo', 'contact_last_name' => $female ? 'Romano' : 'Rinaldi',
            'contact_relationship' => $female ? 'Figlia' : 'Figlio',
            'contact_phone' => $female ? '333 614 7820' : '335 728 4160',
            'contact_email' => $female ? 'elisa.romano@example.com' : 'lorenzo.rinaldi@example.com',
            'contact_address' => $female ? 'Via delle Rose 8' : 'Via del Parco 12', 'contact_city' => 'Aversa',
            'family_members' => $female
                ? [['relationship' => 'Figlia', 'name' => $contact], ['relationship' => 'Nipote', 'name' => 'Giulia Romano']]
                : [['relationship' => 'Moglie', 'name' => 'Anna De Luca'], ['relationship' => 'Figlio', 'name' => $contact]],
            'viewing_venue' => $venue, 'viewing_address' => $venueAddress,
            'viewing_starts_at' => $prior->copy()->setTime(8, 0), 'viewing_ends_at' => $at(9),
            'church' => $church, 'ceremony_city' => 'Aversa', 'ceremony_at' => $at(10),
            'transfer_place' => $church, 'transfer_at' => $at(9, 30),
            'cemetery' => $cemetery, 'burial_type' => 'entombment',
            'competent_city' => 'Aversa', 'transport_origin' => $venue,
            'transport_destination' => $female ? $cemetery : $crematorium, 'transport_destination_city' => $destinationCity,
            'transport_route' => 'Aversa → '.$church.' → '.($female ? $cemetery : $crematorium.', '.$destinationCity),
            'transport_at' => $at(11), 'transport_scope' => 'other_city', 'transport_distance_km' => 100,
            'crematorium' => $female ? null : $crematorium,
            'ashes_destination' => $female ? null : 'Urna nel loculo di famiglia presso il Cimitero comunale di Aversa',
            'vehicle_id' => $vehicle->id, 'prior_transfer_vehicle_id' => $female ? null : $vehicle->id,
            'prior_transfer_destination' => $female ? null : $venue, 'prior_transfer_date' => $female ? null : $prior,
            'certifying_doctor' => $female ? null : 'Dott. Paolo Gentile',
            'manifest_template_id' => $this->frame()->id,
            'manifest_options' => [
                'intro' => $female ? 'È venuta a mancare all’affetto dei suoi cari la Signora' : 'È venuto a mancare all’affetto dei suoi cari il Signor',
                'family_text' => $female ? 'Ne danno il triste annuncio la figlia Elisa, la nipote Giulia e i parenti tutti.' : 'Ne danno il triste annuncio la moglie Anna, il figlio Lorenzo e i parenti tutti.',
                'place' => 'Aversa', 'show_photo' => true, 'show_viewing' => true,
                'show_ceremony' => true, 'show_transfer' => true, 'show_closing' => true,
            ],
            'cremation_details' => [
                'identification_method' => 'document',
                'coffin_type' => 'wood', 'wooden_lining' => 'Raso chiaro con imbottitura assorbente',
                'sanitary_precautions' => 'Nessuna precauzione aggiuntiva',
                'transport_authorization_date' => $prior->toDateString(), 'transport_authorization_issuer' => 'Aversa',
                'report_place' => 'Aversa', 'report_at' => $at(9, 15)->format('Y-m-d H:i:s'),
                'declaration_place' => 'Aversa', 'declaration_date' => $today->toDateString(),
                'contact_birth_place' => $female ? 'Caserta' : 'Aversa',
                'contact_birth_date' => $female ? '1982-11-14' : '1981-06-11',
                'mandate_date' => $prior->toDateString(),
                'transport_purpose' => $female ? 'tumulata' : 'cremato presso il '.$crematorium,
                'ashes_custodian' => $female ? null : $contact, 'ashes_placement' => $female ? null : 'Cimitero comunale di Aversa, loculo di famiglia',
                'ashes_city' => $female ? null : 'Aversa', 'ashes_address' => $female ? null : 'Via del Riposo 12',
            ],
            'internal_notes' => $female
                ? 'Concordare con la figlia Elisa l’orario di arrivo al cimitero.'
                : 'Avvisare il figlio Lorenzo prima della partenza. La famiglia richiede un rito raccolto.',
        ];
    }

    private function vehicle(): FuneralVehicle
    {
        $existing = FuneralVehicle::where('is_active', true)->where('plate', 'not like', 'DEMO%')->where('name', 'not like', 'DEMO%')->whereNotNull('plate')->where('plate', '<>', '')->orderBy('id')->first();
        if ($existing) {
            return $existing;
        }

        return FuneralVehicle::firstOrCreate(['plate' => 'GX482LT'], [
            'name' => 'Mercedes-Benz Classe E autofunebre',
            'credentials' => 'Idoneità sanitaria ASL Caserta prot. 824/'.today()->subYear()->year,
            'is_active' => true,
        ]);
    }

    private function frame(): ManifestTemplate
    {
        $existing = ManifestTemplate::where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
        if ($existing) {
            if ($existing->title === 'DEMO — Cornice di prova' && $existing->image_path === 'frames/demo/cornice-prova.jpg') {
                $existing->update(['title' => 'Cornice con immagine di Gesù']);
            }

            return $existing;
        }
        $path = 'frames/seeder/cornice-gesu.jpg';
        if (! Storage::disk('local')->put($path, file_get_contents(base_path('docs/references/09_cornice_gesu.jpg')))) {
            throw new \RuntimeException('Impossibile salvare la cornice.');
        }

        return ManifestTemplate::create(['title' => 'Cornice con immagine di Gesù', 'image_path' => $path, 'is_active' => true, 'sort_order' => 999]);
    }

    private function fillCompanyFields(): void
    {
        $settings = CompanySettings::current();
        $date = today()->subYear();
        $legacy = [
            'company_address' => 'Via Società Esempio 10',
            'phone' => '0000000000', 'email' => 'agenzia@example.test',
        ];
        foreach ([
            'legal_name' => 'Aurora Servizi Funebri S.r.l.', 'trade_name' => 'Onoranze Funebri Aurora',
            'tax_number' => '12978450638', 'company_address' => 'Via delle Acacie 18', 'company_city' => 'Aversa',
            'phone' => '081 976 4520', 'email' => 'segreteria.aurora@example.com',
            'company_authorization_protocol' => 'SUAP/2147/'.$date->year, 'company_authorization_date' => $date->toDateString(),
            'company_authorization_issuer' => 'Comune di Aversa — Ufficio SUAP',
            'chamber_city' => 'Caserta', 'rea_number' => 'CE-317842',
            'practice_license_protocol' => 'SUAP/2153/'.$date->year, 'practice_license_date' => $date->toDateString(),
            'practice_license_issuer' => 'Comune di Aversa — Ufficio SUAP',
            'retail_authorization_protocol' => 'SCIA/2160/'.$date->year, 'retail_authorization_date' => $date->toDateString(),
            'retail_authorization_issuer' => 'Comune di Aversa',
            'inps_position' => '2018746395', 'inail_position' => '21358746/72',
        ] as $key => $value) {
            $current = $settings[$key] ?? null;
            $generated = is_string($current) && (str_starts_with($current, 'DEMO-') || str_starts_with($current, 'DEMO — '));
            if (blank($current) || $generated || (isset($legacy[$key]) && $current === $legacy[$key])) {
                $settings[$key] = $value;
            }
        }
        if (filled($settings['publication_company_name'] ?? null) && (blank($settings['publication_company_city'] ?? null) || $settings['publication_company_city'] === 'DEMO — Comune di prova')) {
            $settings['publication_company_city'] = 'Aversa';
        }
        CompanySettings::updateOrCreate(['id' => 1], ['data' => $settings]);
    }
}
