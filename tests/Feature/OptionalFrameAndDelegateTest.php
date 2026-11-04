<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FuneralCaseResource\Pages\EditCase;
use App\Models\AuditEvent;
use App\Models\FuneralCase;
use App\Models\User;
use App\Services\DeleteFuneralCase;
use App\Services\PdfGenerator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OptionalFrameAndDelegateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_manifest_preview_generation_and_editor_work_without_a_frame(): void
    {
        $case = FuneralCase::create(['type' => 'cremation', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'sex' => 'male', 'death_date' => '2026-10-01']);
        $this->get(route('private.preview', [$case, 'manifest']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $document = app(PdfGenerator::class)->generate($case, 'manifest');
        $this->assertNull($document->manifest_template_id);
        $this->assertNull($document->snapshot['background']);
        $this->assertSame('Senza cornice', $document->snapshot['template_title']);
        $this->getJson(route('private.manifest-editor.show', $case))->assertOk()->assertJsonPath('assets.background', null);
        $this->assertSame(1, preg_match_all('~/Type\s*/Page\b~', Storage::disk('local')->get($document->file_path)));
    }

    public function test_delegate_front_and_back_uploads_are_optional_private_and_preserved_until_purge(): void
    {
        $owner = auth()->user();
        $case = FuneralCase::create(['type' => 'standard_transport']);
        $this->get(route('private.delegate-identity', [$case, 'front']))->assertNotFound();
        Livewire::test(EditCase::class, ['record' => $case->id])->fillForm(['delegate_identity_front_path' => UploadedFile::fake()->image('fronte.jpg', 300, 200), 'delegate_identity_back_path' => UploadedFile::fake()->createWithContent('retro.pdf', "%PDF-1.4\nDocumento fittizio")])->call('save')->assertHasNoFormErrors();
        $case->refresh();
        $paths = [$case->delegate_identity_front_path, $case->delegate_identity_back_path];
        $this->assertStringEndsWith('.webp', $paths[0]);
        $this->assertStringEndsWith('.pdf', $paths[1]);
        foreach (['front', 'back'] as $side) {
            $this->get(route('private.delegate-identity', [$case, $side]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->actingAs(User::factory()->create());
        $this->get(route('private.delegate-identity', [$case, 'front']))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('private.delegate-identity', [$case, 'back']))->assertOk();
        $case->delete();
        Storage::disk('local')->assertExists($paths);
        $case->forceDelete();
        $audit = AuditEvent::where('event', 'permanently_deleted')->latest('id')->firstOrFail();
        app(DeleteFuneralCase::class)->cleanup($audit->id);
        Storage::disk('local')->assertMissing($paths);
    }
}
