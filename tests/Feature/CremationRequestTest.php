<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\AgencySettings;
use App\Filament\Resources\FuneralCaseResource\Pages\EditCase;
use App\Filament\Resources\FuneralStaffMemberResource\Pages\ManageStaff;
use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\FuneralDocument;
use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;
use App\Models\User;
use App\Services\DocumentData;
use App\Services\PdfGenerator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesCremationRequest;
use Tests\TestCase;

class CremationRequestTest extends TestCase
{
    use CreatesCremationRequest;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Storage::fake('local');
    }

    public function test_request_contains_original_declarations_selected_staff_and_logo_with_immutable_snapshot(): void
    {
        $case = $this->prepareRequest(FuneralCase::create(['type' => 'cremation']));
        $case->update(['internal_notes' => 'Dato privato da non stampare', 'death_cause' => 'Diagnosi riservata']);
        $document = app(PdfGenerator::class)->generate($case, 'document');
        $snapshot = $document->snapshot;
        $html = view('pdf.cremation', ['data' => $snapshot])->render();
        foreach (['artt. 24 e 34', '10 settembre 1990', 'Autorizzazioni/SCIA', 'TEST-123', 'Ufficio SUAP', 'art. 358 C.P.', 'sigilli di chiusura', 'Allega alla presente', 'buste paga', 'regolarità contributiva', 'IL RICHIEDENTE', 'Operatore Esempio 3', 'Direttore Esempio'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['BOZZA', 'Scheda per richiesta', 'Dato privato da non stampare', 'Diagnosi riservata', 'Citarella', 'Centro Funeraria'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
        $this->assertStringStartsWith('data:image/png;base64,', $snapshot['logo']);
        $this->assertCount(4, $snapshot['request']['staff']);
        $this->assertSame('cremation_packet_4_pages_v2', $snapshot['request']['template']);
        $this->assertSame('meno di 100 Km', $snapshot['request']['distance_description']);
        $bytes = Storage::disk('local')->get($document->file_path);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame(4, preg_match_all('~/Type\s*/Page\b~', $bytes));
        $titles = ['Domanda di autorizzazione', 'Verbale di chiusura feretro', 'DICHIARAZIONE SOSTITUTIVA DI ATTO DI NOTORIETÀ', 'Delega per l’espletamento del servizio'];
        $previous = -1;
        foreach ($titles as $title) {
            $position = strpos($html, $title);
            $this->assertNotFalse($position);
            $this->assertGreaterThan($previous, $position);
            $previous = $position;
        }
        foreach (['TEST-REA-789', 'TEST-LIC-123', 'TEST-VEND-456', 'TEST-INPS-111', 'TEST-INAIL-222', 'DOC-TEST-123', 'Familiare Esempio', 'Via Familiare 18', 'family@example.test', 'anticipare a mio nome', 'Allegare fotocopia documento', 'art. 21 del DPR 285/90', 'D.Lgs. 81/08', 'sigillo dell’impresa', 'targhetta di materiale inalterabile'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $case->technicalDirector->update(['name' => 'Nome aggiornato']);
        CompanySettings::find(1)->update(['data' => ['legal_name' => 'Società aggiornata']]);
        $this->assertSame($snapshot, $document->fresh()->snapshot);
        $this->assertSame($bytes, Storage::disk('local')->get($document->file_path));
    }

    public function test_twelve_staff_fit_four_pages_and_are_all_preserved(): void
    {
        $case = $this->prepareRequest(FuneralCase::create(['type' => 'cremation']), 12);
        $rendered = app(PdfGenerator::class)->render($case, 'document');
        $this->assertCount(12, $rendered['snapshot']['request']['staff']);
        $this->assertContains('Operatore Esempio 11', array_column($rendered['snapshot']['request']['staff'], 'name'));
        if (env('PDF_PREVIEW_ARTIFACTS')) {
            $directory = storage_path('framework/testing/pdf-previews');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory.'/cremation-staff12.pdf', $rendered['bytes']);
        }
    }

    public function test_missing_roles_company_details_and_partial_prior_transfer_prevent_printing(): void
    {
        $case = FuneralCase::create(['type' => 'cremation']);
        $missing = app(DocumentData::class)->missing($case, 'document');
        foreach (['Direttore tecnico attivo e abilitato', 'Ambito del trasporto', 'Distanza dichiarata in km', 'Protocollo autorizzazione / SCIA (Impostazioni agenzia)'] as $label) {
            $this->assertContains($label, $missing);
        }
        $case = $this->prepareRequest($case);
        $case->update(['prior_transfer_destination' => 'Camera ardente']);
        $missing = app(DocumentData::class)->missing($case, 'document');
        $this->assertContains('Data della traslazione precedente', $missing);
        $this->assertContains('Medico della certificazione', $missing);
        $case->transportStaff()->detach($case->staff_member_id);
        $case->unsetRelation('transportStaff');
        $this->expectException(ValidationException::class);
        app(PdfGenerator::class)->generate($case, 'document');
    }

    public function test_distance_over_100_and_previous_transfer_are_printed_as_entered(): void
    {
        $case = $this->prepareRequest(FuneralCase::create(['type' => 'cremation']));
        $case->update(['transport_distance_km' => 125.5, 'prior_transfer_destination' => 'Camera ardente', 'prior_transfer_date' => '2026-10-01', 'certifying_doctor' => 'Medico Esempio', 'prior_transfer_vehicle_id' => $case->vehicle_id]);
        $data = app(DocumentData::class)->assemble($case->fresh(), 'document');
        $this->assertSame('125,5 Km', $data['request']['distance_description']);
        $this->assertSame('Medico Esempio', $data['request']['prior_transfer']['doctor']);
        $this->assertSame('01/10/2026', $data['request']['prior_transfer']['date']);
        $case->update(['prior_transfer_date' => '2026-09-30']);
        $this->assertContains('Data della traslazione precedente compresa tra decesso e oggi', app(DocumentData::class)->missing($case, 'document'));
    }

    public function test_form_persists_multiple_selected_staff_and_new_fields(): void
    {
        $case = $this->prepareRequest(FuneralCase::create(['type' => 'cremation']));
        $ids = $case->transportStaff->pluck('id')->take(2)->all();
        Livewire::test(EditCase::class, ['record' => $case->id])->fillForm([
            'transportStaff' => $ids, 'transport_distance_km' => 87.25, 'transport_destination_city' => 'Nuovo Comune',
            'cremation_details.identification_method' => 'witness', 'cremation_details.witness_name' => 'Testimone Selezionato',
            'cremation_details.witness_relationship' => 'Figlio', 'cremation_details.witness_document_type' => 'Carta identità',
            'cremation_details.witness_document_number' => 'WIT-001', 'cremation_details.witness_document_issuer' => 'Comune esempio',
            'cremation_details.witness_document_issued_at' => '2020-01-01', 'cremation_details.witness_document_expires_at' => '2030-01-01',
            'cremation_details.coffin_type' => 'double',
        ])->call('save')->assertHasNoFormErrors();
        $this->assertEqualsCanonicalizing($ids, $case->fresh()->transportStaff->modelKeys());
        $this->assertSame('87.25', $case->fresh()->transport_distance_km);
        $this->assertSame('Testimone Selezionato', $case->fresh()->cremation_details['witness_name']);
        $this->assertSame('double', $case->fresh()->cremation_details['coffin_type']);
        $this->actingAs(User::factory()->create());
        Livewire::test(EditCase::class, ['record' => $case->id])->assertNotFound();
    }

    public function test_admin_can_configure_company_authorization_and_director_demographics(): void
    {
        Livewire::test(AgencySettings::class)->fillForm([
            'legal_name' => 'Impresa di prova', 'company_city' => 'Comune sede',
            'company_authorization_protocol' => 'TEST-456', 'company_authorization_date' => '2025-02-03',
            'company_authorization_issuer' => 'Comune sede - SUAP',
            'chamber_city' => 'Comune camera', 'rea_number' => 'REA-UI-123', 'inps_position' => 'INPS-UI-123',
            'inail_position' => 'INAIL-UI-123', 'practice_license_protocol' => 'LIC-UI-123',
            'practice_license_date' => '2025-01-01', 'retail_authorization_date' => '2025-02-02',
        ])->call('save')->assertHasNoFormErrors();
        $this->assertSame('TEST-456', CompanySettings::current()['company_authorization_protocol']);
        $this->assertSame('REA-UI-123', CompanySettings::current()['rea_number']);
        $this->assertSame('INPS-UI-123', CompanySettings::current()['inps_position']);
        $member = FuneralStaffMember::create(['name' => 'Richiedente esempio']);
        Livewire::test(ManageStaff::class)->callAction(TestAction::make('edit')->table($member), data: [
            'is_technical_director' => true, 'birth_place' => 'Comune nascita', 'birth_date' => '1980-01-01',
            'residence_city' => 'Comune sede', 'residence_address' => 'Via Prova 1',
        ])->assertHasNoActionErrors();
        $this->assertTrue($member->fresh()->is_technical_director);
        $this->assertSame('Comune nascita', $member->fresh()->birth_place);
        $this->assertSame('1980-01-01', $member->fresh()->birth_date->format('Y-m-d'));
    }

    public function test_excessive_content_is_reported_without_archiving_a_truncated_document(): void
    {
        $case = $this->prepareRequest(FuneralCase::create(['type' => 'cremation']), 12);
        foreach ($case->transportStaff as $member) {
            $member->update(['credentials' => str_repeat('Abilitazione dimostrativa protocollo 123456. ', 5)]);
        }
        try {
            app(PdfGenerator::class)->generate($case->fresh(), 'document');
            $this->fail('Contenuto eccessivo accettato senza verifica.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Nessun testo è stato tagliato', $error->getMessage());
        }
        $this->assertSame(0, $case->documents()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('pdf'));
    }

    public function test_new_catalog_references_are_protected_even_after_archiving(): void
    {
        $case = FuneralCase::create(['type' => 'cremation']);
        $member = FuneralStaffMember::create(['name' => 'Solo in elenco']);
        $director = FuneralStaffMember::create(['name' => 'Solo direttore', 'is_technical_director' => true]);
        $vehicle = FuneralVehicle::create(['name' => 'Solo traslazione']);
        $case->transportStaff()->sync([$member->id]);
        $case->update(['technical_director_id' => $director->id, 'prior_transfer_vehicle_id' => $vehicle->id]);
        $case->delete();
        foreach ([$member, $director, $vehicle] as $record) {
            $this->assertTrue($record->isUsed());
            try {
                $record->delete();
                $this->fail('Riferimento utilizzato eliminato.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('name', $error->errors());
            }
        }
    }

    public function test_incremental_migration_copies_legacy_selection_without_losing_records(): void
    {
        $migration = require database_path('migrations/2026_10_21_000100_add_cremation_request_fields.php');
        $migration->down();
        $member = FuneralStaffMember::create(['name' => 'Operatore preesistente']);
        $case = FuneralCase::create(['type' => 'cremation', 'staff_member_id' => $member->id, 'first_name' => 'Pratica preesistente']);
        // SQLite ricostruisce la tabella per aggiungere FK; rinvia la verifica
        // durante la transazione di test, mantenendo attivi tutti i vincoli.
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $migration->up();
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame('Pratica preesistente', $case->fresh()->first_name);
        $this->assertSame([$member->id], $case->fresh()->transportStaff->modelKeys());
        $this->assertDatabaseHas('users', ['id' => auth()->id()]);
    }

    public function test_witness_and_double_coffin_generate_without_requiring_deceased_document_and_keep_other_data_private(): void
    {
        $case = $this->prepareRequest(FuneralCase::create(['type' => 'cremation']));
        $details = $case->cremation_details;
        foreach (array_keys($details) as $key) {
            if (str_starts_with($key, 'identity_document_')) {
                unset($details[$key]);
            }
        }
        $details = array_replace($details, [
            'identification_method' => 'witness', 'witness_name' => 'Testimone Esempio', 'witness_relationship' => 'Figlio',
            'witness_document_type' => 'Passaporto', 'witness_document_number' => 'WIT-TEST-888', 'witness_document_issuer' => 'Ente esempio',
            'witness_document_issued_at' => '2024-01-01', 'witness_document_expires_at' => '2034-01-01',
            'coffin_type' => 'double', 'unused_private_value' => 'Dato estraneo da escludere',
        ]);
        unset($details['wooden_lining']);
        $case->update(['cremation_details' => $details]);
        $rendered = app(PdfGenerator::class)->render($case->fresh(), 'document');
        $this->assertSame(4, preg_match_all('~/Type\s*/Page\b~', $rendered['bytes']));
        $packet = $rendered['snapshot']['request']['packet'];
        $this->assertSame('witness', $packet['identification']['method']);
        $this->assertSame('WIT-TEST-888', $packet['identification']['number']);
        $this->assertSame('double', $packet['coffin_type']);
        $this->assertStringNotContainsString('Dato estraneo da escludere', json_encode($rendered['snapshot']));
        $html = view('pdf.cremation', ['data' => $rendered['snapshot']])->render();
        $this->assertStringContainsString('☒ Il cadavere è stato racchiuso in DUPLICE', $html);
        $this->assertStringContainsString('Testimone Esempio', $html);

        $case->update(['cremation_details' => array_replace($details, ['coffin_type' => 'biodegradable', 'wooden_lining' => 'Tessuto esempio'])]);
        $data = app(DocumentData::class)->assemble($case->fresh(), 'document');
        $this->assertSame('biodegradable', $data['request']['packet']['coffin_type']);
    }

    public function test_invalid_packet_dates_missing_family_and_unknown_choices_are_reported_before_generation(): void
    {
        $case = $this->prepareRequest(FuneralCase::create(['type' => 'cremation']));
        $case->update(['contact_last_name' => null, 'cremation_details' => array_replace($case->cremation_details, [
            'identity_document_expires_at' => '2019-01-01', 'coffin_type' => 'unknown', 'report_at' => '2026-09-30 10:00:00',
            'contact_birth_date' => '2026-02-30', 'transport_authorization_date' => '2026-10-03',
        ])]);
        $missing = implode(' | ', app(DocumentData::class)->missing($case->fresh(), 'document'));
        foreach (['Scadenza documento del defunto', 'Confezionamento del feretro', 'Data e ora di redazione del verbale', 'Data di nascita del familiare', 'Data autorizzazione del singolo trasporto', 'Cognome del familiare delegante'] as $label) {
            $this->assertStringContainsString($label, $missing);
        }
    }

    public function test_new_packet_migration_preserves_legacy_case_pdf_and_snapshot(): void
    {
        $case = FuneralCase::create(['type' => 'cremation', 'first_name' => 'Preesistente']);
        $snapshot = ['request' => ['template' => 'cremation_request_page_1_v1'], 'name' => 'Preesistente'];
        Storage::disk('local')->put('pdf/legacy.pdf', '%PDF-1.4 storico dimostrativo');
        $document = FuneralDocument::create([
            'funeral_case_id' => $case->id, 'created_by' => auth()->id(), 'kind' => 'document', 'case_type' => 'cremation',
            'version' => 1, 'file_path' => 'pdf/legacy.pdf', 'snapshot' => $snapshot,
        ]);
        $migration = require database_path('migrations/2026_10_21_000200_add_cremation_packet_details.php');
        $migration->down();
        $migration->up();
        $this->assertSame('Preesistente', $case->fresh()->first_name);
        $this->assertSame($snapshot, $document->fresh()->snapshot);
        $this->assertSame('%PDF-1.4 storico dimostrativo', Storage::disk('local')->get($document->file_path));
        $this->assertNull($case->fresh()->cremation_details);
    }
}
