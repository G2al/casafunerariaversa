<?php

namespace App\Services;

use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\FuneralStaffMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class CremationRequestData
{
    public function missing(FuneralCase $case): array
    {
        $case->loadMissing(['technicalDirector', 'transportStaff', 'staffMember', 'vehicle', 'priorTransferVehicle']);
        $missing = [];
        foreach ([
            'residence_city' => 'Comune di residenza del defunto', 'residence_address' => 'Indirizzo di residenza del defunto',
            'competent_city' => 'Comune competente', 'transport_origin' => 'Luogo di partenza',
            'transport_destination_city' => 'Comune di destinazione',
        ] as $field => $label) {
            if (blank($case->$field)) {
                $missing[] = $label;
            }
        }
        if (! in_array($case->sex, ['male', 'female'], true)) {
            $missing[] = 'Sesso del defunto';
        }
        if (! in_array($case->transport_scope, ['within_city', 'other_city'], true)) {
            $missing[] = 'Ambito del trasporto';
        }
        if (Validator::make(['distance' => $case->transport_distance_km], ['distance' => 'required|numeric|min:0|max:999999.99'])->fails()) {
            $missing[] = 'Distanza dichiarata in km';
        }

        $director = $case->technicalDirector;
        if (! $director || ! $director->is_active || ! $director->is_technical_director) {
            $missing[] = 'Direttore tecnico attivo e abilitato';
        }
        if (! $case->vehicle?->is_active || blank($case->vehicle?->plate)) {
            $missing[] = 'Mezzo attivo con targa';
        }
        if (! $case->staffMember?->is_active) {
            $missing[] = 'Operatore incaricato del trasporto';
        }
        if ($case->transportStaff->isEmpty()) {
            $missing[] = 'Personale da riportare nel documento (selezione multipla)';
        }
        foreach ($case->transportStaff as $member) {
            if (! $member->is_active || blank($member->qualification) || blank($member->credentials)) {
                $missing[] = 'Personale: '.$member->name.' — verifica stato attivo, qualifica ed estremi di abilitazione';
            }
        }
        if ($case->staffMember && ! $case->transportStaff->contains('id', $case->staff_member_id)) {
            $missing[] = 'Includere l’operatore incaricato nell’elenco del personale';
        }

        $company = CompanySettings::current();
        foreach ([
            'legal_name' => 'Ragione sociale', 'company_address' => 'Indirizzo società', 'company_city' => 'Comune della sede',
            'company_authorization_protocol' => 'Protocollo autorizzazione / SCIA', 'company_authorization_issuer' => 'Comune e ufficio di rilascio dell’autorizzazione',
        ] as $field => $label) {
            if (blank($company[$field] ?? null)) {
                $missing[] = $label.' (Impostazioni agenzia)';
            }
        }
        if (Validator::make(['date' => $company['company_authorization_date'] ?? null], ['date' => 'required|date|before_or_equal:today'])->fails()) {
            $missing[] = 'Data autorizzazione / SCIA valida (Impostazioni agenzia)';
        }

        $priorFields = ['prior_transfer_destination', 'prior_transfer_date', 'certifying_doctor', 'prior_transfer_vehicle_id'];
        if (collect($priorFields)->contains(fn ($field) => filled($case->$field))) {
            foreach (['prior_transfer_destination' => 'Destinazione della traslazione precedente', 'prior_transfer_date' => 'Data della traslazione precedente', 'certifying_doctor' => 'Medico della certificazione'] as $field => $label) {
                if (blank($case->$field)) {
                    $missing[] = $label;
                }
            }
            if (! $case->priorTransferVehicle?->is_active || blank($case->priorTransferVehicle?->plate)) {
                $missing[] = 'Mezzo della traslazione precedente con targa';
            }
            if ($case->prior_transfer_date && ($case->prior_transfer_date->isFuture() || ($case->death_date && $case->prior_transfer_date->lt($case->death_date)))) {
                $missing[] = 'Data della traslazione precedente compresa tra decesso e oggi';
            }
        }

        return array_merge($missing, $this->packetMissing($case, $company));
    }

    public function assemble(FuneralCase $case, array $company): array
    {
        return [
            'template' => 'cremation_packet_4_pages_v2',
            'packet' => $this->packet($case, $company),
            'deceased' => [
                'name' => $case->full_name, 'female' => $case->sex === 'female',
                'birth_date' => $case->birth_date?->format('d/m/Y'), 'birth_place' => $case->birth_place,
                'marital_status' => $case->marital_status, 'spouse_name' => $case->spouse_name,
                'residence_city' => $case->residence_city, 'residence_address' => $case->residence_address,
                'death_date' => $case->death_date?->format('d/m/Y'), 'death_time' => $case->death_time,
                'death_place' => $case->death_place, 'death_address' => $case->death_address,
            ],
            'competent_city' => $case->competent_city,
            'director' => $this->person($case->technicalDirector) + [
                'birth_place' => $case->technicalDirector->birth_place, 'birth_date' => $case->technicalDirector->birth_date?->format('d/m/Y'),
                'residence_city' => $case->technicalDirector->residence_city, 'residence_address' => $case->technicalDirector->residence_address,
            ],
            'staff' => $case->transportStaff->map(fn ($member) => $this->person($member))->values()->all(),
            'assigned_operator' => $this->person($case->staffMember),
            'vehicle' => $case->vehicle->only(['name', 'plate', 'credentials']),
            'scope' => $case->transport_scope, 'origin' => $case->transport_origin,
            'destination' => $case->transport_destination, 'destination_city' => $case->transport_destination_city,
            'crematorium' => $case->crematorium,
            'distance_km' => $case->transport_distance_km,
            'distance_description' => (float) $case->transport_distance_km < 100 ? 'meno di 100 Km' : rtrim(rtrim(number_format((float) $case->transport_distance_km, 2, ',', '.'), '0'), ',').' Km',
            'transport_date' => $case->transport_at->format('d/m/Y'), 'transport_time' => $case->transport_at->format('H:i'),
            'company_authorization_date' => Carbon::parse($company['company_authorization_date'])->format('d/m/Y'),
            'prior_transfer' => [
                'destination' => $case->prior_transfer_destination, 'date' => $case->prior_transfer_date?->format('d/m/Y'),
                'doctor' => $case->certifying_doctor, 'vehicle_plate' => $case->priorTransferVehicle?->plate,
            ],
            'additional_note' => $case->transport_request_note,
            'additional_attachments' => [$case->transport_attachment_extra_1, $case->transport_attachment_extra_2],
            'font_size' => 9.2,
        ];
    }

    private function person(FuneralStaffMember $member): array
    {
        return $member->only(['name', 'qualification', 'credentials']);
    }

    private function packetMissing(FuneralCase $case, array $company): array
    {
        $details = $case->cremation_details ?? [];
        $labels = [
            'identification_method' => 'Modalità di identificazione', 'coffin_type' => 'Confezionamento del feretro',
            'transport_authorization_date' => 'Data autorizzazione del singolo trasporto',
            'transport_authorization_issuer' => 'Comune che autorizza il singolo trasporto',
            'report_place' => 'Luogo di redazione del verbale', 'report_at' => 'Data e ora di redazione del verbale',
            'declaration_place' => 'Luogo della dichiarazione', 'declaration_date' => 'Data della dichiarazione',
            'contact_birth_place' => 'Luogo di nascita del familiare', 'contact_birth_date' => 'Data di nascita del familiare',
            'mandate_date' => 'Data della delega',
        ];
        $rules = array_fill_keys(array_keys($labels), ['required', 'string', 'max:255']);
        $rules['identification_method'] = 'required|in:document,witness';
        $rules['coffin_type'] = 'required|in:wood,biodegradable,double';
        foreach (['contact_birth_date', 'transport_authorization_date', 'declaration_date', 'mandate_date'] as $field) {
            $rules[$field] = 'required|date_format:Y-m-d';
        }
        $rules['contact_birth_date'] .= '|before_or_equal:today';
        $rules['report_at'] = 'required|date';
        $rules['sanitary_precautions'] = 'nullable|string|max:255';
        if (in_array($details['coffin_type'] ?? '', ['wood', 'biodegradable'], true)) {
            $labels['wooden_lining'] = 'Fodera interna della cassa di legno';
            $rules['wooden_lining'] = 'required|string|max:255';
        }
        if (in_array($details['identification_method'] ?? '', ['document', 'witness'], true)) {
            $prefix = $details['identification_method'] === 'witness' ? 'witness' : 'identity';
            $who = $prefix === 'witness' ? 'testimone' : 'defunto';
            foreach (['type' => 'Tipo documento', 'number' => 'Numero documento', 'issuer' => 'Ente di rilascio', 'issued_at' => 'Data rilascio documento', 'expires_at' => 'Scadenza documento'] as $field => $label) {
                $key = $prefix.'_document_'.$field;
                $labels[$key] = $label.' del '.$who;
                $rules[$key] = in_array($field, ['issued_at', 'expires_at'], true) ? 'required|date_format:Y-m-d' : 'required|string|max:255';
            }
            $rules[$prefix.'_document_expires_at'] .= '|after_or_equal:'.$prefix.'_document_issued_at';
            $rules[$prefix.'_document_issued_at'] .= '|before_or_equal:report_at';
            if ($prefix === 'witness') {
                $labels += ['witness_name' => 'Nome del testimone', 'witness_relationship' => 'Rapporto del testimone con il defunto'];
                $rules += ['witness_name' => 'required|string|max:255', 'witness_relationship' => 'required|string|max:255'];
            }
        }
        if ($case->death_date) {
            $rules['report_at'] .= '|after_or_equal:'.$case->death_date->format('Y-m-d');
        }
        if ($case->transport_at) {
            $rules['transport_authorization_date'] .= '|before_or_equal:'.$case->transport_at->format('Y-m-d');
        }
        $invalid = Validator::make($details, $rules)->errors()->keys();
        $missing = array_map(fn ($key) => ($labels[$key] ?? $key).' (pagine 2–4: dato mancante o non valido)', $invalid);
        foreach (['authorization_number' => 'Estremi autorizzazione del singolo trasporto', 'contact_first_name' => 'Nome del familiare delegante', 'contact_last_name' => 'Cognome del familiare delegante', 'contact_relationship' => 'Parentela del familiare delegante', 'contact_city' => 'Comune del familiare delegante', 'contact_address' => 'Indirizzo completo del familiare delegante'] as $field => $label) {
            if (blank($case->$field)) {
                $missing[] = $label;
            }
        }
        foreach ([
            'tax_number' => 'Partita IVA / codice fiscale', 'chamber_city' => 'Camera di commercio', 'rea_number' => 'Numero REA',
            'practice_license_protocol' => 'Protocollo licenza art. 115 TULPS', 'practice_license_issuer' => 'Ente di rilascio licenza art. 115',
            'retail_authorization_issuer' => 'Ente comunicazione vendita casse', 'inps_position' => 'Posizione INPS', 'inail_position' => 'Posizione INAIL',
        ] as $field => $label) {
            if (blank($company[$field] ?? null)) {
                $missing[] = $label.' (Impostazioni agenzia)';
            }
        }
        foreach (['practice_license_date' => 'Data licenza art. 115 TULPS', 'retail_authorization_date' => 'Data comunicazione vendita casse'] as $field => $label) {
            if (Validator::make($company, [$field => 'required|date_format:Y-m-d|before_or_equal:today'])->fails()) {
                $missing[] = $label.' valida (Impostazioni agenzia)';
            }
        }
        if (filled($company['publication_company_name'] ?? null) && blank($company['publication_company_city'] ?? null)) {
            $missing[] = 'Comune della società che pubblica l’annuncio (Impostazioni agenzia)';
        }

        return $missing;
    }

    private function packet(FuneralCase $case, array $company): array
    {
        $details = $case->cremation_details;
        $date = fn (string $key) => Carbon::parse($details[$key])->format('d/m/Y');
        $prefix = $details['identification_method'] === 'witness' ? 'witness' : 'identity';

        return [
            'identification' => [
                'method' => $details['identification_method'],
                'witness_name' => $prefix === 'witness' ? $details['witness_name'] : null,
                'witness_relationship' => $prefix === 'witness' ? $details['witness_relationship'] : null,
                'type' => $details[$prefix.'_document_type'], 'number' => $details[$prefix.'_document_number'],
                'issuer' => $details[$prefix.'_document_issuer'], 'issued_at' => $date($prefix.'_document_issued_at'),
                'expires_at' => $date($prefix.'_document_expires_at'),
            ],
            'coffin_type' => $details['coffin_type'],
            'wooden_lining' => in_array($details['coffin_type'], ['wood', 'biodegradable'], true) ? $details['wooden_lining'] : null,
            'sanitary_precautions' => $details['sanitary_precautions'] ?? null,
            'transport_authorization' => ['number' => $case->authorization_number, 'date' => $date('transport_authorization_date'), 'issuer' => $details['transport_authorization_issuer']],
            'report_place' => $details['report_place'], 'report_date' => Carbon::parse($details['report_at'])->format('d/m/Y'), 'report_time' => Carbon::parse($details['report_at'])->format('H:i'),
            'declaration_place' => $details['declaration_place'], 'declaration_date' => $date('declaration_date'),
            'church' => $case->church, 'ceremony_city' => $case->ceremony_city,
            'company_license_date' => Carbon::parse($company['practice_license_date'])->format('d/m/Y'),
            'retail_authorization_date' => Carbon::parse($company['retail_authorization_date'])->format('d/m/Y'),
            'family' => [
                'name' => trim($case->contact_first_name.' '.$case->contact_last_name), 'relationship' => $case->contact_relationship,
                'birth_place' => $details['contact_birth_place'], 'birth_date' => $date('contact_birth_date'),
                'city' => $case->contact_city, 'address' => $case->contact_address, 'phone' => $case->contact_phone, 'email' => $case->contact_email,
            ],
            'mandate_date' => $date('mandate_date'),
            'publication_company_name' => filled($company['publication_company_name'] ?? null) ? $company['publication_company_name'] : $company['legal_name'],
            'publication_company_city' => filled($company['publication_company_name'] ?? null) ? $company['publication_company_city'] : $company['company_city'],
        ];
    }
}
