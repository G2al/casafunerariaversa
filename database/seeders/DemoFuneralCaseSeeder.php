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
            $this->command?->line('Due pratiche con dati inventati ma plausibili. Completa documenti di identità, foto, direttore tecnico, operatore incaricato e personale.');
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
        $year = $today->year;
        $contact = $female ? 'Elisa Romano' : 'Lorenzo Rinaldi';
        $church = $female ? 'Parrocchia di Santa Lucia' : 'Parrocchia di San Michele';
        $crematorium = $female ? 'Centro crematorio Santa Lucia' : 'Centro crematorio San Lorenzo';
        $destinationCity = $female ? 'Caserta' : 'Castel Volturno';
        $vehicle = $this->vehicle();
        $venue = 'Casa funeraria Aversa';
        $venueAddress = 'Via dei Platani 31, Aversa';

        return [
            'operator_id' => $ownerId, 'type' => $type, 'status' => 'draft',
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
            'cemetery' => 'Cimitero comunale di Aversa', 'burial_type' => 'entombment',
            'competent_city' => 'Aversa', 'transport_origin' => $venue,
            'transport_destination' => $crematorium, 'transport_destination_city' => $destinationCity,
            'transport_route' => 'Aversa → '.$church.' → '.$crematorium.', '.$destinationCity,
            'transport_at' => $at(11), 'transport_scope' => 'other_city', 'transport_distance_km' => $female ? 28 : 34,
            'authorization_number' => 'Prot. '.($female ? '4382' : '4381').'/'.$year.' del '.$prior->format('d/m/Y'),
            'crematorium' => $crematorium,
            'ashes_destination' => 'Urna nel loculo di famiglia presso il Cimitero comunale di Aversa',
            'vehicle_id' => $vehicle->id, 'prior_transfer_vehicle_id' => $vehicle->id,
            'prior_transfer_destination' => $venue, 'prior_transfer_date' => $prior,
            'certifying_doctor' => $female ? 'Dott.ssa Serena Vitale' : 'Dott. Paolo Gentile',
            'transport_request_note' => 'Rientro delle ceneri ad Aversa e consegna al familiare incaricato.',
            'transport_attachment_extra_1' => 'Autorizzazione alla cremazione',
            'transport_attachment_extra_2' => 'Dichiarazione del familiare per la destinazione delle ceneri',
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
                'transport_purpose' => ($female ? 'cremata' : 'cremato').' presso il '.$crematorium,
                'ashes_custodian' => $contact, 'ashes_placement' => 'Cimitero comunale di Aversa, loculo di famiglia',
                'ashes_city' => 'Aversa', 'ashes_address' => 'Via del Riposo 12',
            ],
            'internal_notes' => $female
                ? 'La figlia Elisa ritirerà le ceneri. Concordare telefonicamente il rientro e la consegna.'
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
