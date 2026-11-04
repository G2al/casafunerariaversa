<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FuneralCaseResource;
use App\Models\AuditEvent;
use App\Models\FuneralCase;
use App\Models\ManifestTemplate;
use App\Models\Symbol;
use App\Models\User;
use App\Services\PdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManifestEditorTest extends TestCase
{
    use RefreshDatabase;

    private FuneralCase $case;

    private static ?string $image = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        Storage::disk('local')->put('frames/sample.jpg', file_get_contents(base_path('docs/references/09_cornice_gesu.jpg')));
        $frame = ManifestTemplate::create(['title' => 'Cornice', 'image_path' => 'frames/sample.jpg']);
        $this->case = FuneralCase::create(['type' => 'cremation', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'sex' => 'male', 'birth_date' => '1950-01-01', 'death_date' => '2026-10-01', 'manifest_template_id' => $frame->id, 'internal_notes' => 'Segreto interno', 'death_cause' => 'Dato sanitario']);
    }

    private function payload(): array
    {
        $data = $this->getJson(route('private.manifest-editor.show', $this->case))->assertOk()->json();
        if (! self::$image) {
            $image = imagecreatetruecolor(3508, 2480);
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
            ob_start();
            imagejpeg($image, null, 97);
            $bytes = ob_get_clean();
            imagedestroy($image);
            self::$image = 'data:image/jpeg;base64,'.base64_encode($bytes);
        }

        return ['revision' => $data['revision'], 'source' => $data['source'], 'scene' => $data['scene'], 'image' => self::$image];
    }

    public function test_bootstrap_page_and_fonts_are_private(): void
    {
        $this->get(FuneralCaseResource::getUrl('manifest-editor', ['record' => $this->case]))->assertOk()->assertSee('Editor manifesto')->assertSee('data-manifest-editor', false);
        $data = $this->getJson(route('private.manifest-editor.show', $this->case))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $this->assertSame(1, $data['scene']['version']);
        $this->assertContains('name', array_column($data['scene']['elements'], 'id'));
        $this->assertStringNotContainsString('Segreto interno', json_encode($data));
        $this->assertStringNotContainsString('Dato sanitario', json_encode($data));
        $this->get(route('private.manifest-editor.font', 'serif'))->assertOk();
        $this->get(route('private.manifest-editor.font', 'unknown'))->assertNotFound();
        $payload = $this->payload();
        $this->actingAs(User::factory()->create());
        $this->getJson(route('private.manifest-editor.show', $this->case))->assertForbidden();
        $this->postJson(route('private.manifest-editor.save', $this->case), $payload)->assertForbidden();
        $this->deleteJson(route('private.manifest-editor.reset', $this->case), ['revision' => null])->assertForbidden();
        $this->get(FuneralCaseResource::getUrl('manifest-editor', ['record' => $this->case]))->assertNotFound();
    }

    public function test_save_reload_pdf_and_reset_preserve_archived_versions(): void
    {
        $payload = $this->payload();
        $payload['scene']['elements'][0]['text'] = 'Testo personalizzato';
        $response = $this->postJson(route('private.manifest-editor.save', $this->case), $payload + ['generate' => true])->assertOk();
        $saved = $this->case->fresh()->manifest_layout;
        $this->assertSame($response['revision'], $saved['revision']);
        Storage::disk('local')->assertExists($saved['image_path']);
        $this->getJson(route('private.manifest-editor.show', $this->case))->assertJsonPath('scene.elements.0.text', 'Testo personalizzato');
        $document = $this->case->documents()->sole();
        $pdf = Storage::disk('local')->get($document->file_path);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, preg_match_all('~/Type\s*/Page\b~', $pdf));
        $this->assertSame($saved['revision'], $document->snapshot['editor_revision']);
        $this->get($response['documentUrl'])->assertOk();
        $this->get(route('private.preview', [$this->case, 'manifest']))->assertOk();
        $this->deleteJson(route('private.manifest-editor.reset', $this->case), ['revision' => $saved['revision']])->assertOk();
        $this->assertNull($this->case->fresh()->manifest_layout);
        $this->assertSame($pdf, Storage::disk('local')->get($document->file_path));
        $this->assertArrayHasKey('layout', app(PdfGenerator::class)->render($this->case->fresh(), 'manifest')['snapshot']);
    }

    public function test_stale_revision_and_case_changes_cannot_silently_overwrite(): void
    {
        $payload = $this->payload();
        $this->postJson(route('private.manifest-editor.save', $this->case), $payload)->assertOk();
        $this->postJson(route('private.manifest-editor.save', $this->case), $payload)->assertConflict();
        $current = $this->payload();
        $this->case->refresh()->update(['first_name' => 'Nome cambiato']);
        $this->postJson(route('private.manifest-editor.save', $this->case), $current)->assertConflict();
        $this->getJson(route('private.manifest-editor.show', $this->case))->assertJsonPath('stale', true);
        $this->deleteJson(route('private.manifest-editor.reset', $this->case), ['revision' => null])->assertConflict();
        $this->assertCount(1, Storage::disk('local')->files('manifest-editor'));
    }

    public function test_invalid_assets_geometry_and_images_are_rejected(): void
    {
        $payload = $this->payload();
        $bad = $payload;
        $bad['scene']['elements'][0]['src'] = 'https://example.com/arbitrary.svg';
        $this->postJson(route('private.manifest-editor.save', $this->case), $bad)->assertUnprocessable();
        $bad = $payload;
        $bad['scene']['elements'][0]['x'] = 840;
        $this->postJson(route('private.manifest-editor.save', $this->case), $bad)->assertUnprocessable();
        $bad = $payload;
        $bad['image'] = 'data:image/jpeg;base64,'.base64_encode('not an image');
        $this->postJson(route('private.manifest-editor.save', $this->case), $bad)->assertUnprocessable();
        $this->assertNull($this->case->fresh()->manifest_layout);
        $this->assertSame([], Storage::disk('local')->files('manifest-editor'));
    }

    public function test_assets_are_frozen_until_refreshed_and_trashed_case_cannot_be_edited(): void
    {
        $photo = UploadedFile::fake()->image('photo.png');
        Storage::disk('local')->put('photos/one.png', file_get_contents($photo->getRealPath()));
        $this->case->update(['photo_path' => 'photos/one.png']);
        $this->postJson(route('private.manifest-editor.save', $this->case), $this->payload())->assertOk();
        $savedPhoto = $this->case->fresh()->manifest_layout['assets']['photo'];
        $this->case->update(['photo_path' => null]);
        $data = $this->getJson(route('private.manifest-editor.show', $this->case))->assertOk()->json();
        $this->assertTrue($data['stale']);
        $this->assertSame($savedPhoto, $data['assets']['photo']);
        $this->assertNull($data['currentAssets']['photo']);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->case->delete();
        $this->get(FuneralCaseResource::getUrl('manifest-editor', ['record' => $this->case]))->assertForbidden();
    }

    public function test_trash_preserves_assets_and_force_delete_removes_all_revisions(): void
    {
        $this->postJson(route('private.manifest-editor.save', $this->case), $this->payload())->assertOk();
        $first = $this->case->fresh()->manifest_layout['image_path'];
        $this->postJson(route('private.manifest-editor.save', $this->case), $this->payload())->assertOk();
        $second = $this->case->fresh()->manifest_layout['image_path'];
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->case->delete();
        Storage::disk('local')->assertExists([$first, $second]);
        $this->case->forceDelete();
        Storage::disk('local')->assertMissing([$first, $second]);
        $this->assertSame(0, AuditEvent::where('event', 'manifest_layout_saved')->count());
    }

    public function test_shapes_symbols_frames_and_private_uploads_round_trip(): void
    {
        $symbol = Symbol::create(['title' => 'Simbolo', 'image_path' => 'frames/sample.jpg']);
        $this->actingAs($this->case->owner);
        $this->get(route('private.symbol', $symbol))->assertOk();
        $upload = $this->post(route('private.manifest-editor.upload', $this->case), ['image' => UploadedFile::fake()->image('extra.png', 200, 100)])->assertOk()->json();
        $this->get($upload['url'])->assertOk();
        $payload = $this->payload();
        $base = $payload['scene']['elements'][0];
        $payload['scene']['backgroundAsset'] = 'frame_'.$this->case->manifest_template_id;
        foreach (['rect', 'ellipse', 'triangle'] as $shape) {
            $payload['scene']['elements'][] = array_replace($base, ['id' => $shape, 'type' => $shape, 'x' => 0, 'y' => 0, 'width' => 800, 'height' => 550, 'fill' => 'transparent', 'stroke' => '#174f37', 'strokeWidth' => 3]);
        }
        foreach (['symbol_'.$symbol->id, $upload['key']] as $key) {
            $payload['scene']['elements'][] = array_replace($base, ['id' => $key, 'type' => 'image', 'asset' => $key, 'x' => 40, 'y' => 200, 'width' => 100, 'height' => 100]);
        }
        $this->postJson(route('private.manifest-editor.save', $this->case), $payload)->assertOk();
        $saved = $this->case->fresh()->manifest_layout;
        $this->assertStringStartsWith('data:image/', $saved['assets'][$upload['key']]);
        $this->assertStringStartsWith('data:image/', $saved['assets']['symbol_'.$symbol->id]);
        $this->assertSame($payload['scene'], $saved['scene']);
        $symbol->delete();
        $new = $this->payload();
        $this->postJson(route('private.manifest-editor.save', $this->case), $new)->assertOk();
        $this->actingAs(User::factory()->create());
        $this->get($upload['url'])->assertForbidden();
        $this->post(route('private.manifest-editor.upload', $this->case), ['image' => UploadedFile::fake()->image('other.png')])->assertForbidden();
    }

    public function test_prelapide_is_editable_and_independent_from_manifest(): void
    {
        $symbol = Symbol::create(['title' => 'Primo', 'image_path' => 'frames/sample.jpg', 'sort_order' => 0]);
        $this->get(FuneralCaseResource::getUrl('prelapide-editor', ['record' => $this->case]))->assertOk()->assertSee('Editor prelapide');
        $manifest = $this->payload();
        $this->postJson(route('private.manifest-editor.save', $this->case), $manifest)->assertOk();
        $manifestRevision = $this->case->fresh()->manifest_layout['revision'];
        $url = route('private.manifest-editor.show', [$this->case, 'kind' => 'prelapide']);
        $data = $this->getJson($url)->assertOk()->json();
        $this->assertContains('symbol', array_column($data['scene']['elements'], 'id'));
        $this->assertContains('border_0', array_column($data['scene']['elements'], 'id'));
        $input = ['scene' => $data['scene'], 'source' => $data['source'], 'revision' => null, 'image' => $manifest['image'], 'generate' => true];
        $this->postJson($data['saveUrl'], $input)->assertOk();
        $saved = $this->case->fresh();
        $this->assertNotNull($saved->prelapide_layout);
        $this->assertSame($manifestRevision, $saved->manifest_layout['revision']);
        $doc = $saved->documents()->where('kind', 'prelapide')->sole();
        $this->assertSame($saved->prelapide_layout['revision'], $doc->snapshot['editor_revision']);
        $this->deleteJson($data['resetUrl'], ['revision' => $saved->prelapide_layout['revision']])->assertOk();
        $this->assertNull($saved->fresh()->prelapide_layout);
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_foreign_assets_and_malformed_shape_styles_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['scene']['elements'][0] = array_replace($payload['scene']['elements'][0], ['type' => 'image', 'asset' => 'upload_999999']);
        $this->postJson(route('private.manifest-editor.save', $this->case), $payload)->assertUnprocessable();
        $payload = $this->payload();
        $payload['scene']['elements'][0] = array_replace($payload['scene']['elements'][0], ['type' => 'rect', 'fill' => 'url(https://example.com/image)', 'strokeWidth' => -1]);
        $this->postJson(route('private.manifest-editor.save', $this->case), $payload)->assertUnprocessable();
        $payload = $this->payload();
        $payload['scene']['backgroundAsset'] = 'frame_999999';
        $this->postJson(route('private.manifest-editor.save', $this->case), $payload)->assertUnprocessable();
        $this->assertSame([], Storage::disk('local')->files('manifest-editor'));
    }

    public function test_selected_frame_remains_frozen_until_explicit_refresh(): void
    {
        $frame = ManifestTemplate::create(['title' => 'Alternativa', 'image_path' => 'frames/sample.jpg']);
        $key = 'frame_'.$frame->id;
        $payload = $this->payload();
        $payload['scene']['backgroundAsset'] = $key;
        $this->postJson(route('private.manifest-editor.save', $this->case), $payload)->assertOk();
        $frozen = $this->case->fresh()->manifest_layout['assets'][$key];
        $file = UploadedFile::fake()->image('new.png', 100, 70);
        Storage::disk('local')->put('frames/changed.png', file_get_contents($file->getRealPath()));
        $frame->update(['image_path' => 'frames/changed.png']);
        $this->postJson(route('private.manifest-editor.save', $this->case), $this->payload())->assertOk();
        $this->assertSame($frozen, $this->case->fresh()->manifest_layout['assets'][$key]);
        $payload = $this->payload();
        $payload['freshAssets'] = [$key];
        $this->postJson(route('private.manifest-editor.save', $this->case), $payload)->assertOk();
        $this->assertNotSame($frozen, $this->case->fresh()->manifest_layout['assets'][$key]);
    }

    public function test_vertical_text_stretch_is_preserved_and_out_of_range_values_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['scene']['elements'][0]['stretchY'] = 2.25;
        $payload['scene']['elements'][0]['height'] *= 2.25;
        $this->postJson(route('private.manifest-editor.save', $this->case), $payload)->assertOk();
        $saved = $this->case->fresh()->manifest_layout;
        $this->assertSame(2.25, $saved['scene']['elements'][0]['stretchY']);
        $this->getJson(route('private.manifest-editor.show', $this->case))->assertOk()->assertJsonPath('scene.elements.0.stretchY', 2.25);
        foreach ([0, 11] as $invalid) {
            $input = $this->payload();
            $input['scene']['elements'][0]['stretchY'] = $invalid;
            $this->postJson(route('private.manifest-editor.save', $this->case), $input)->assertUnprocessable();
        }
        $this->assertSame($saved['revision'], $this->case->fresh()->manifest_layout['revision']);
        $this->assertCount(1, Storage::disk('local')->files('manifest-editor'));
    }
}
