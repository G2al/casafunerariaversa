<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FuneralCaseResource\Pages\CreateCase;
use App\Filament\Resources\FuneralCaseResource\Pages\EditCase;
use App\Filament\Resources\FuneralCaseResource\Pages\ListCases;
use App\Models\FuneralCase;
use App\Models\User;
use App\Services\DocumentData;
use App\Services\OptimizedUpload;
use App\Services\PdfGenerator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesCremationRequest;
use Tests\TestCase;

class UpdatedWorkflowTest extends TestCase
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

    public function test_transport_no_generates_only_mandate_for_both_procedures(): void
    {
        foreach (['cremation', 'standard_transport'] as $type) {
            $case = FuneralCase::create(['type' => $type, 'transport_enabled' => false]);
            $pdf = app(PdfGenerator::class)->generate($case, 'document');
            $this->assertSame(1, preg_match_all('~/Type\s*/Page\b~', Storage::disk('local')->get($pdf->file_path)));
            $html = view('pdf.'.$type, ['data' => $pdf->snapshot])->render();
            $this->assertStringContainsString('Delega per', $html);
            foreach (['Domanda di autorizzazione', 'Verbale di chiusura', 'DICHIARAZIONE SOSTITUTIVA', 'autofunebre targato', 'Il trasporto della salma dal luogo'] as $text) {
                $this->assertStringNotContainsString($text, $html);
            }
            $this->get(route('private.preview', [$case, 'document']))->assertOk();
            $this->get(route('private.document', $pdf))->assertOk();
        }
    }

    public function test_route_cemetery_removed_fields_and_optional_prior_transfer_in_templates(): void
    {
        foreach (['cremation', 'standard_transport'] as $type) {
            $case = $this->prepareRequest(FuneralCase::create(['type' => $type]));
            $case->update([
                'transport_route' => 'Aversa - Chiesa - Carinaro', 'cemetery' => 'Cimitero comunale di Carinaro',
                'prior_transfer_destination' => 'Traslazione nascosta', 'certifying_doctor' => 'Medico nascosto',
                'transport_request_note' => 'RIGA5 DA ESCLUDERE', 'transport_attachment_extra_1' => 'RIGA6 DA ESCLUDERE',
            ]);
            $data = app(DocumentData::class)->assemble($case, 'document');
            $html = view('pdf.'.$type, ['data' => $data])->render();
            foreach (['con il seguente percorso: Aversa - Chiesa - Carinaro', 'fino al cimitero di Cimitero comunale di Carinaro'] as $text) {
                $this->assertStringContainsString($text, $html);
            }
            foreach (['Traslazione nascosta', 'Medico nascosto', 'RIGA5 DA ESCLUDERE', 'RIGA6 DA ESCLUDERE', 'AUT-TEST-001', '(5)', '-(6)', '-(7)'] as $text) {
                $this->assertStringNotContainsString($text, $html);
            }
            $case->update(['prior_transfer_enabled' => true]);
            $html = view('pdf.'.$type, ['data' => app(DocumentData::class)->assemble($case, 'document')])->render();
            $this->assertStringContainsString('Traslazione nascosta', $html);
        }
    }

    public function test_conditional_tabs_hide_cremation_transport_and_document_sections(): void
    {
        $case = FuneralCase::create(['type' => 'standard_transport']);
        Livewire::test(EditCase::class, ['record' => $case->id])
            ->assertFormFieldIsHidden('crematorium')->assertFormFieldIsVisible('transport_route')
            ->assertFormFieldIsHidden('cremation_details.ashes_custodian')
            ->assertFormFieldDoesNotExist('authorization_number')
            ->assertFormFieldDoesNotExist('transport_request_note')
            ->fillForm(['type' => 'cremation'])
            ->assertFormFieldIsVisible('crematorium')->assertFormFieldIsVisible('transport_route')
            ->assertFormFieldIsVisible('cremation_details.ashes_custodian')
            ->fillForm(['transport_enabled' => false])
            ->assertFormFieldIsHidden('transport_route')->assertFormFieldIsHidden('technical_director_id')
            ->assertFormFieldIsVisible('cremation_details.mandate_date')
            ->call('save')->assertHasNoFormErrors();
        $this->assertTrue($case->fresh()->transport_enabled); // Il trasporto non è più una scelta del form.
    }

    public function test_distance_defaults_to_100_but_custom_and_zero_are_kept(): void
    {
        Livewire::test(CreateCase::class)->assertFormSet(['transport_distance_km' => 100, 'transport_enabled' => true]);
        $case = FuneralCase::create(['type' => 'standard_transport', 'transport_distance_km' => null]);
        $this->assertSame('100.00', $case->transport_distance_km);
        $case->update(['transport_distance_km' => 0]);
        $this->assertSame('0.00', $case->transport_distance_km);
        $case->update(['transport_distance_km' => 135]);
        $this->assertSame('135.00', $case->transport_distance_km);
        $this->assertTrue(DatePicker::make('date')->isNative());
        $this->assertTrue(DateTimePicker::make('date_time')->isNative());
    }

    public function test_incremental_workflow_migration_preserves_existing_records_and_detects_prior_transfers(): void
    {
        $one = FuneralCase::create(['type' => 'standard_transport']);
        $two = FuneralCase::create(['type' => 'cremation', 'prior_transfer_destination' => 'Camera precedente']);
        $migration = require database_path('migrations/2026_11_03_000100_update_funeral_case_workflow.php');
        $migration->down();
        DB::table('funeral_cases')->where('id', $one->id)->update(['transport_distance_km' => null, 'prior_transfer_destination' => '']);
        $migration->up();
        $this->assertTrue($one->fresh()->transport_enabled);
        $this->assertFalse($one->fresh()->prior_transfer_enabled);
        $this->assertTrue($two->fresh()->prior_transfer_enabled);
        $this->assertSame('100.00', $one->fresh()->transport_distance_km);
        $this->assertSame('Camera precedente', $two->fresh()->prior_transfer_destination);
        $this->assertSame(2, FuneralCase::count());
    }

    public function test_certificates_uploaded_from_form_are_private_optimized_and_retained_in_trash(): void
    {
        $case = FuneralCase::create(['type' => 'standard_transport']);
        Livewire::test(EditCase::class, ['record' => $case->id])->fillForm([
            'death_certificate_path' => UploadedFile::fake()->image('certificato.png', 160, 220),
            'istat_path' => UploadedFile::fake()->createWithContent('istat.pdf', "%PDF-1.4\nmodello di prova"),
        ])->call('save')->assertHasNoFormErrors();
        $case = $case->fresh();
        $this->assertStringEndsWith('.webp', $case->death_certificate_path);
        $this->assertStringEndsWith('.pdf', $case->istat_path);
        $this->get(route('private.attachment', [$case, 'death-certificate']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('private.attachment', [$case, 'istat']))->assertOk();
        $admin = auth()->user();
        $this->actingAs(User::factory()->create());
        $this->get(route('private.attachment', [$case, 'istat']))->assertForbidden();
        $this->actingAs($admin);
        Livewire::test(ListCases::class)->selectTableRecords([$case])->callAction(TestAction::make('delete')->table()->bulk());
        $this->assertSoftDeleted($case);
        Storage::disk('local')->assertExists([$case->death_certificate_path, $case->istat_path]);
        Livewire::test(ListCases::class)->filterTable('trashed', false)->selectTableRecords([$case])->callAction(TestAction::make('restore')->table()->bulk());
        $this->assertNotSoftDeleted($case);
        $case->forceDelete();
        Storage::disk('local')->assertMissing([$case->death_certificate_path, $case->istat_path]);
    }

    public function test_images_are_resized_converted_and_invalid_files_rejected(): void
    {
        $upload = UploadedFile::fake()->image('foto.png', 2600, 1800);
        $path = app(OptimizedUpload::class)->store($upload, 'photos');
        $bytes = Storage::disk('local')->get($path);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertLessThan(filesize($upload->getRealPath()), strlen($bytes));
        $size = getimagesizefromstring($bytes);
        $this->assertSame(2000, $size[0]);
        $this->expectException(ValidationException::class);
        app(OptimizedUpload::class)->store(UploadedFile::fake()->createWithContent('falso.jpg', '<script>bad</script>'), 'photos');
    }

    public function test_prelapide_is_one_landscape_a4_page_with_only_requested_data(): void
    {
        $case = FuneralCase::create(['type' => 'standard_transport', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'birth_date' => '1950-02-02', 'death_date' => '2026-10-01', 'internal_notes' => 'Nota riservata']);
        $pdf = app(PdfGenerator::class)->generate($case, 'prelapide');
        $bytes = Storage::disk('local')->get($pdf->file_path);
        $this->assertSame(1, preg_match_all('~/Type\s*/Page\b~', $bytes));
        $this->assertStringContainsString('/MediaBox [0.000 0.000 841.890 595.280]', $bytes);
        $this->assertSame('02/02/1950', $pdf->snapshot['birth_date']);
        $this->assertArrayNotHasKey('internal_notes', $pdf->snapshot);
        $this->get(route('private.preview', [$case, 'prelapide']))->assertOk();
        $this->get(route('private.document', $pdf))->assertOk();
        $this->actingAs(User::factory()->create());
        $this->get(route('private.preview', [$case, 'prelapide']))->assertForbidden();
    }

    public function test_prelapide_adapts_long_names_to_a_single_page(): void
    {
        $case = FuneralCase::create(['type' => 'cremation', 'first_name' => str_repeat('Nome composto ', 16), 'last_name' => str_repeat('Cognome lungo ', 16)]);
        $pdf = app(PdfGenerator::class)->render($case, 'prelapide');
        $this->assertSame(1, preg_match_all('~/Type\s*/Page\b~', $pdf['bytes']));
        $this->assertLessThan(44, collect($pdf['snapshot']['layout']['elements'])->firstWhere('id', 'name')['fontSize']);
    }
}
