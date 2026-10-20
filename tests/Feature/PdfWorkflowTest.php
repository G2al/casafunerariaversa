<?php

namespace Tests\Feature;

use App\Models\FuneralCase;
use App\Models\ManifestTemplate;
use App\Models\User;
use App\Services\PdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PdfWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function completeCase(string $type = 'cremation'): FuneralCase
    {
        return FuneralCase::create(['type' => $type, 'first_name' => 'Persona', 'last_name' => 'Esempio', 'sex' => 'female', 'birth_date' => '1950-01-01', 'birth_place' => 'Comune esempio', 'death_date' => '2026-10-01', 'death_place' => 'Comune esempio', 'transport_at' => '2026-10-02 10:00', 'transport_destination' => 'Destinazione esempio', 'crematorium' => 'Crematorio esempio', 'death_cause' => 'Dato sanitario riservato', 'internal_notes' => 'Nota privata', 'viewing_venue' => 'Sede dimostrativa', 'viewing_address' => 'Via di esempio 10, Comune esempio', 'viewing_starts_at' => '2026-10-01 14:30', 'viewing_ends_at' => '2026-10-02 14:00', 'church' => 'Parrocchia di esempio', 'ceremony_city' => 'Comune esempio', 'ceremony_at' => '2026-10-02 15:00', 'transfer_place' => 'Parrocchia di esempio', 'transfer_at' => '2026-10-02 14:15']);
    }

    public function test_both_documents_are_real_pdfs_and_previous_versions_are_immutable(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        foreach (['cremation', 'standard_transport'] as $type) {
            $case = $this->completeCase($type);
            $this->assertNotSame('Dato sanitario riservato', $case->getRawOriginal('death_cause'));
            $one = app(PdfGenerator::class)->generate($case, 'document');
            $bytes = Storage::disk('local')->get($one->file_path);
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertGreaterThan(10000, strlen($bytes));
            $this->artifact($type, $bytes);
            $two = app(PdfGenerator::class)->generate($case, 'document');
            $this->assertSame(2, $two->version);
            $this->assertNotSame($one->file_path, $two->file_path);
            $this->assertSame($bytes, Storage::disk('local')->get($one->file_path));
            $this->get(route('private.document', $one))->assertOk();
            $this->get(route('private.preview', [$case, 'document']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_all_sample_frames_photo_options_accents_and_privacy(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $case = $this->completeCase();
        foreach (['07_cornice_madonna_stelle.jpg', '08_cornice_madonna_coronata.jpg', '09_cornice_gesu.jpg'] as $index => $sample) {
            Storage::disk('local')->put('frames/'.$sample, file_get_contents(base_path('docs/references/'.$sample)));
            $frame = ManifestTemplate::create(['title' => 'Campione '.$index, 'image_path' => 'frames/'.$sample]);
            $case->update(['manifest_template_id' => $frame->id, 'manifest_options' => ['family_text' => 'Ne danno il triste annuncio i familiari. È un annuncio di prova.']]);
            $pdf = app(PdfGenerator::class)->generate($case->fresh(), 'manifest');
            $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($pdf->file_path));
            $this->artifact('manifest-'.$index, Storage::disk('local')->get($pdf->file_path));
            $this->assertStringContainsString('È venuta', $pdf->snapshot['intro']);
            $this->assertArrayNotHasKey('death_cause', $pdf->snapshot);
            $this->assertStringNotContainsString('Dato sanitario', json_encode($pdf->snapshot));
            $this->assertStringNotContainsString('Nota privata', json_encode($pdf->snapshot));
            $this->assertStringContainsString('/MediaBox [0.000 0.000 841.890 595.280]', Storage::disk('local')->get($pdf->file_path));
        }
        $image = imagecreatetruecolor(160, 200);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        Storage::disk('local')->put('photos/test.png', $png);
        $case->update(['photo_path' => 'photos/test.png', 'sex' => 'male']);
        $withPhoto = app(PdfGenerator::class)->generate($case->fresh(), 'manifest');
        $this->assertNotNull($withPhoto->snapshot['photo']);
        $this->assertStringContainsString('È venuto', $withPhoto->snapshot['intro']);
        $this->artifact('manifest-photo', Storage::disk('local')->get($withPhoto->file_path));
        $this->actingAs(User::factory()->create());
        $this->get(route('private.document', $withPhoto))->assertForbidden();
        $this->get(route('private.preview', [$case, 'manifest']))->assertForbidden();
        $this->get(route('private.photo', $case))->assertForbidden();
    }

    public function test_incomplete_case_cannot_generate_and_preview_lists_missing_fields(): void
    {
        $this->actingAs(User::factory()->create());
        $case = FuneralCase::create(['type' => 'cremation']);
        $this->get(route('private.preview', [$case, 'document']))->assertStatus(422)->assertSee('Dati mancanti');
        $this->expectException(ValidationException::class);
        app(PdfGenerator::class)->generate($case, 'document');
    }

    public function test_long_names_fit_and_excessive_text_is_rejected(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        Storage::disk('local')->put('frames/sample.jpg', file_get_contents(base_path('docs/references/09_cornice_gesu.jpg')));
        $frame = ManifestTemplate::create(['title' => 'Campione', 'image_path' => 'frames/sample.jpg']);
        $case = $this->completeCase();
        $case->update(['manifest_template_id' => $frame->id, 'first_name' => 'Persona con un nome composto particolarmente lungo', 'last_name' => 'Cognome dimostrativo con più elementi', 'manifest_options' => ['family_text' => str_repeat('Familiari e amici partecipano al ricordo della persona cara. ', 8)]]);
        $pdf = app(PdfGenerator::class)->generate($case->fresh(), 'manifest');
        $layout = $pdf->snapshot['layout'];
        foreach ($layout['lines'] as $line) {
            $this->assertLessThanOrEqual($layout['area']['y'] + $layout['area']['h'], $line['y'] + $line['size'] * 1.5);
        }
        $this->artifact('manifest-long', Storage::disk('local')->get($pdf->file_path));
        $case->update(['manifest_options' => ['family_text' => str_repeat('Testo eccessivo. ', 500)]]);
        $this->expectException(ValidationException::class);
        app(PdfGenerator::class)->generate($case->fresh(), 'manifest');
    }

    private function artifact(string $name, string $bytes): void
    {
        if (! env('PDF_PREVIEW_ARTIFACTS')) {
            return;
        }
        $directory = storage_path('framework/testing/pdf-previews');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($directory.'/'.$name.'.pdf', $bytes);
    }
}
