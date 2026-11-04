<?php

namespace Tests\Feature;

use App\Models\FuneralCase;
use App\Models\User;
use App\Services\DocumentData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyDocumentUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_document_populates_witness_and_ashes_are_repeated_only_for_cremation(): void
    {
        $this->actingAs(User::factory()->create());
        $case = FuneralCase::create(['type' => 'cremation', 'contact_first_name' => 'Anna', 'contact_last_name' => 'Rossi', 'contact_relationship' => 'Sorella', 'cremation_details' => ['identification_method' => 'document', 'contact_document_type' => 'driving_license', 'contact_document_number' => 'AB12345', 'contact_document_issuer' => 'Motorizzazione', 'contact_document_issued_at' => '2020-01-02', 'contact_document_expires_at' => '2030-01-02', 'ashes_custodian' => 'Famiglia Rossi', 'ashes_city' => 'Aversa', 'ashes_placement' => 'Cimitero', 'ashes_address' => 'Via Roma']]);
        $data = app(DocumentData::class)->assemble($case, 'document');
        $witness = $data['request']['packet']['identification']['witness'];
        $this->assertSame(['name' => 'Anna Rossi', 'relationship' => 'Sorella', 'type' => 'Patente', 'number' => 'AB12345', 'issuer' => 'Motorizzazione', 'issued_at' => '02/01/2020', 'expires_at' => '02/01/2030'], $witness);
        $html = view('pdf.funeral-packet', ['data' => $data])->render();
        $this->assertSame(2, substr_count($html, 'Dopo l’avvenuta cremazione'));
        $this->assertStringContainsString('AB12345', $html);
        $this->assertLessThan(strpos($html, 'Allega alla presente'), strpos($html, 'Dopo l’avvenuta cremazione'));
        $case->update(['type' => 'standard_transport']);
        $html = view('pdf.funeral-packet', ['data' => app(DocumentData::class)->assemble($case, 'document')])->render();
        $this->assertStringNotContainsString('Dopo l’avvenuta cremazione', $html);
        $case->update(['cremation_details' => ['contact_document_number' => 'SOLO123']]);
        $witness = app(DocumentData::class)->assemble($case, 'document')['request']['packet']['identification']['witness'];
        $this->assertSame('SOLO123', $witness['number']);
        $this->assertNull($witness['type']);
        $case->update(['cremation_details' => ['contact_document_issued_at' => '2020-01-02']]);
        $witness = app(DocumentData::class)->assemble($case, 'document')['request']['packet']['identification']['witness'];
        $this->assertSame('02/01/2020', $witness['issued_at']);
        $this->assertSame('Anna Rossi', $witness['name']);
    }
}
