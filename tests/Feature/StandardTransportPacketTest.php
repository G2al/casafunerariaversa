<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FuneralCaseResource\Pages\EditCase;
use App\Models\FuneralCase;
use App\Models\User;
use App\Services\DocumentData;
use App\Services\PdfGenerator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesCremationRequest;
use Tests\TestCase;

class StandardTransportPacketTest extends TestCase
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

    public function test_four_pages_show_cemetery_and_selected_staff_without_cremation(): void
    {
        $case = $this->prepareRequest(FuneralCase::create(['type' => 'standard_transport']), 12);
        $case->technicalDirector->update(['birth_date' => null, 'birth_place' => null, 'residence_city' => null, 'residence_address' => null]);
        $case->update(['identity_document_type' => 'electronic_id']);
        $document = app(PdfGenerator::class)->generate($case->fresh(), 'document');
        $data = $document->snapshot;
        $this->assertSame('standard_transport_packet_4_pages_v2', $data['request']['template']);
        $this->assertSame('standard_transport', $document->case_type);
        $bytes = Storage::disk('local')->get($document->file_path);
        $this->assertSame(4, preg_match_all('~/Type\s*/Page\b~', $bytes));
        $html = view('pdf.standard_transport', ['data' => $data])->render();
        $last = -1;
        foreach (['Domanda di autorizzazione', 'Verbale di chiusura feretro', 'DICHIARAZIONE SOSTITUTIVA', 'Delega per l’espletamento'] as $title) {
            $position = strpos($html, $title);
            $this->assertNotFalse($position);
            $this->assertGreaterThan($last, $position);
            $last = $position;
        }
        foreach (['al cimitero di', 'Cimitero di prova', 'Operatore Esempio 11', 'DOC-TEST-123', 'TEST-INPS-111', 'IN FEDE'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['impianto di cremazione', 'forno crematorio', 'ceneri', 'AUT-TEST-001'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
        $this->assertNull($data['request']['packet']['ashes']);
        $this->assertStringNotContainsString('BOZZA', $html);
        $this->assertStringNotContainsString('Corvino', $html);
        $this->assertCount(12, $data['request']['staff']);
        $this->assertStringNotContainsString('identity-documents/', json_encode($data));
        if (env('PDF_PREVIEW_ARTIFACTS')) {
            $directory = storage_path('framework/testing/pdf-previews');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory.'/standard-transport-staff12.pdf', $bytes);
        }
        $case->update(['type' => 'cremation']);
        $this->assertSame($bytes, Storage::disk('local')->get($document->file_path));
        $this->assertSame($data, $document->fresh()->snapshot);
        $this->actingAs(User::factory()->create());
        $this->get(route('private.document', $document))->assertForbidden();
    }

    public function test_normal_transport_form_saves_shared_document_fields(): void
    {
        $case = $this->prepareRequest(FuneralCase::create(['type' => 'standard_transport']));
        Livewire::test(EditCase::class, ['record' => $case->id])->fillForm([
            'identity_document_type' => 'passport', 'cremation_details.identity_document_number' => 'PASS-TEST-777',
            'cremation_details.ashes_custodian' => 'Affidatario di prova', 'cremation_details.ashes_placement' => 'Luogo di prova',
            'cremation_details.ashes_city' => 'Comune di prova', 'cremation_details.ashes_address' => 'Indirizzo di prova 1',
        ])->call('save')->assertHasNoFormErrors();
        $case = $case->fresh();
        $this->assertSame('passport', $case->identity_document_type);
        $this->assertSame('Familiare Esempio', $case->cremation_details['ashes_custodian']);
        $data = app(DocumentData::class)->assemble($case, 'document');
        $this->assertSame('Passaporto', $data['request']['packet']['identification']['type']);
        $this->assertSame('PASS-TEST-777', $data['request']['packet']['identification']['number']);
    }

    public function test_missing_ashes_do_not_apply_to_normal_transport(): void
    {
        $case = $this->prepareRequest(FuneralCase::create(['type' => 'standard_transport']));
        $case->update(['cremation_details' => array_replace($case->cremation_details, ['ashes_custodian' => null])]);
        $this->assertSame([], app(DocumentData::class)->missing($case, 'document'));
        $case->update(['type' => 'cremation']);
        $this->assertSame([], app(DocumentData::class)->missing($case, 'document'));
    }
}
