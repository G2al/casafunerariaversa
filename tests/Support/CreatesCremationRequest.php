<?php

namespace Tests\Support;

use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;

trait CreatesCremationRequest
{
    private function prepareRequest(FuneralCase $case, int $staffCount = 4): FuneralCase
    {
        CompanySettings::updateOrCreate(['id' => 1], ['data' => [
            'legal_name' => 'Impresa dimostrativa Srl', 'company_address' => 'Via Esempio 10',
            'company_city' => 'Comune sede', 'company_authorization_protocol' => 'TEST-123',
            'company_authorization_date' => '2025-01-10', 'company_authorization_issuer' => 'Comune sede - Ufficio SUAP',
            'tax_number' => 'TEST-PIVA', 'chamber_city' => 'Comune camera', 'rea_number' => 'TEST-REA-789',
            'practice_license_protocol' => 'TEST-LIC-123', 'practice_license_date' => '2025-02-03', 'practice_license_issuer' => 'Comune sede - SUAP',
            'retail_authorization_protocol' => 'TEST-VEND-456', 'retail_authorization_date' => '2025-02-04', 'retail_authorization_issuer' => 'Comune sede',
            'inps_position' => 'TEST-INPS-111', 'inail_position' => 'TEST-INAIL-222',
        ]]);
        $director = FuneralStaffMember::create([
            'name' => 'Direttore Esempio', 'qualification' => 'Direttore tecnico', 'credentials' => 'Attestato dimostrativo DT-1',
            'is_technical_director' => true, 'birth_date' => '1980-01-01', 'birth_place' => 'Comune nascita',
            'residence_city' => 'Comune residenza', 'residence_address' => 'Via Residenza 20',
        ]);
        $staff = collect([$director]);
        for ($i = 1; $i < $staffCount; $i++) {
            $staff->push(FuneralStaffMember::create([
                'name' => 'Operatore Esempio '.$i, 'qualification' => 'Operatore funebre',
                'credentials' => 'Prot. TEST-000'.$i.' del 01/01/2025 rilasciato da Regione esempio',
            ]));
        }
        $vehicle = FuneralVehicle::create(['name' => 'Autofunebre dimostrativa', 'plate' => 'TEST-001']);
        $case->update([
            'first_name' => 'Persona', 'last_name' => 'Esempio', 'sex' => 'female',
            'birth_date' => '1950-01-01', 'birth_place' => 'Comune esempio',
            'death_date' => '2026-10-01', 'death_place' => 'Comune esempio',
            'residence_city' => 'Comune esempio', 'residence_address' => 'Via Esempio 5',
            'competent_city' => 'Comune esempio', 'transport_origin' => 'Casa funeraria',
            'transport_destination' => 'Crematorio esempio', 'transport_destination_city' => 'Comune destinazione',
            'transport_at' => '2026-10-02 10:00', 'crematorium' => 'Crematorio esempio',
            'transport_distance_km' => 65, 'transport_scope' => 'other_city',
            'technical_director_id' => $director->id, 'staff_member_id' => $staff->last()->id, 'vehicle_id' => $vehicle->id,
            'authorization_number' => 'AUT-TEST-001',
            'contact_first_name' => 'Familiare', 'contact_last_name' => 'Esempio', 'contact_relationship' => 'Figlia',
            'contact_city' => 'Comune residenza', 'contact_address' => 'Via Familiare 18', 'contact_phone' => '0000000000', 'contact_email' => 'family@example.test',
            'cremation_details' => [
                'transport_purpose' => 'cremato presso il crematorio di prova',
                'ashes_custodian' => 'Familiare Esempio', 'ashes_placement' => 'Cimitero di prova',
                'ashes_city' => 'Comune destinazione ceneri', 'ashes_address' => 'Via Ceneri 15',
                'identification_method' => 'document', 'identity_document_type' => 'Carta identità', 'identity_document_number' => 'DOC-TEST-123',
                'identity_document_issuer' => 'Comune esempio', 'identity_document_issued_at' => '2020-01-01', 'identity_document_expires_at' => '2030-01-01',
                'coffin_type' => 'wood', 'wooden_lining' => 'Tessuto dimostrativo', 'sanitary_precautions' => 'Nessuna particolare',
                'transport_authorization_date' => '2026-10-01', 'transport_authorization_issuer' => 'Comune esempio',
                'report_place' => 'Comune esempio', 'report_at' => '2026-10-02 09:30:00',
                'declaration_place' => 'Comune esempio', 'declaration_date' => '2026-10-02',
                'contact_birth_place' => 'Comune nascita familiare', 'contact_birth_date' => '1980-01-01', 'mandate_date' => '2026-10-01',
            ],
        ]);
        $case->transportStaff()->sync($staff->pluck('id')->all());

        return $case->fresh();
    }
}
