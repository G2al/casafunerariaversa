<?php

namespace Tests\Feature;

use App\Enums\IdentityDocumentType;
use App\Enums\UserRole;
use App\Filament\Resources\FuneralCaseResource\Pages\CreateCase;
use App\Filament\Resources\FuneralCaseResource\Pages\EditCase;
use App\Models\FuneralCase;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class IdentityDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_create_with_front_image_back_pdf_and_metadata_then_change_procedure(): void
    {
        $pdf = Pdf::loadHTML('<p>Documento fittizio di prova</p>')->output();
        Livewire::test(CreateCase::class)->fillForm([
            'type' => 'standard_transport', 'status' => 'draft', 'identity_document_type' => 'electronic_id',
            'cremation_details.identity_document_number' => 'DEMO-ID-001',
            'cremation_details.identity_document_issuer' => 'Comune esempio',
            'cremation_details.identity_document_issued_at' => '2020-01-01',
            'cremation_details.identity_document_expires_at' => '2030-01-01',
            'identity_front_path' => UploadedFile::fake()->image('fronte.png', 320, 200),
            'identity_back_path' => UploadedFile::fake()->createWithContent('retro.pdf', $pdf),
        ])->call('create')->assertHasNoFormErrors();
        $case = FuneralCase::sole();
        foreach (['front', 'back'] as $side) {
            $path = $case->{'identity_'.$side.'_path'};
            $this->assertStringStartsWith('identity-documents/', $path);
            Storage::disk('local')->assertExists($path);
            $this->get(route('private.identity', [$case, $side]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->assertSame('DEMO-ID-001', $case->cremation_details['identity_document_number']);
        $front = $case->identity_front_path;
        $back = $case->identity_back_path;
        Livewire::test(EditCase::class, ['record' => $case->id])->fillForm(['type' => 'cremation'])->call('save')->assertHasNoFormErrors();
        $this->assertSame($front, $case->fresh()->identity_front_path);
        $this->assertSame($back, $case->fresh()->identity_back_path);
        $this->assertSame('electronic_id', $case->fresh()->identity_document_type);
        $this->assertCount(4, IdentityDocumentType::cases());
    }

    public function test_downloads_require_case_owner_or_admin_and_cannot_accept_an_arbitrary_path(): void
    {
        $image = UploadedFile::fake()->image('front.png');
        Storage::disk('local')->put('identity-documents/owner.png', file_get_contents($image->getRealPath()));
        $case = FuneralCase::create(['type' => 'cremation', 'identity_front_path' => 'identity-documents/owner.png']);
        $this->get(route('private.identity', [$case, 'front']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('private.identity', [$case, 'back']))->assertNotFound();
        $this->actingAs(User::factory()->create());
        $this->get(route('private.identity', [$case, 'front']))->assertForbidden();
        $own = FuneralCase::create(['type' => 'cremation']);
        Livewire::test(EditCase::class, ['record' => $own->id])->fillForm(['identity_front_path' => ['tampered' => 'identity-documents/owner.png']])->call('save')->assertHasFormErrors(['identity_front_path']);
        $this->assertNull($own->fresh()->identity_front_path);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->get(route('private.identity', [$case, 'front']))->assertOk();
        $this->get('/private/cases/'.$case->id.'/identity/../../.env')->assertNotFound();
        auth()->logout();
        $this->get(route('private.identity', [$case, 'front']))->assertRedirect('/admin/login');
    }

    public function test_invalid_type_file_contents_and_dates_are_rejected(): void
    {
        Livewire::test(CreateCase::class)->fillForm([
            'type' => 'cremation', 'identity_document_type' => 'arbitrary',
            'identity_front_path' => UploadedFile::fake()->createWithContent('fake.png', '<html>Not an image</html>'),
            'cremation_details.identity_document_issued_at' => '2020-01-01',
            'cremation_details.identity_document_expires_at' => '2019-01-01',
        ])->call('create')->assertHasFormErrors(['identity_document_type', 'identity_front_path', 'cremation_details.identity_document_expires_at']);
        $this->assertSame(0, FuneralCase::count());
        $this->expectException(ValidationException::class);
        FuneralCase::create(['type' => 'cremation', 'identity_front_path' => 'photos/../.env']);
    }

    public function test_incremental_migration_preserves_legacy_identity_data(): void
    {
        $case = FuneralCase::create(['type' => 'cremation', 'cremation_details' => ['identity_document_type' => 'Carta identità', 'identity_document_number' => 'OLD-123']]);
        $migration = require database_path('migrations/2026_10_21_000300_add_identity_document_uploads.php');
        $migration->down();
        $migration->up();
        $this->assertSame('OLD-123', $case->fresh()->cremation_details['identity_document_number']);
        $this->assertSame('Carta identità', $case->fresh()->cremation_details['identity_document_type']);
        $this->assertNull($case->fresh()->identity_front_path);
    }
}
