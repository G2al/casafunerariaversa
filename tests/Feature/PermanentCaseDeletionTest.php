<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FuneralCaseResource\Pages\EditCase;
use App\Filament\Resources\FuneralCaseResource\Pages\ListCases;
use App\Models\AuditEvent;
use App\Models\FuneralCase;
use App\Models\FuneralDocument;
use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;
use App\Models\ManifestTemplate;
use App\Models\User;
use App\Services\DeleteFuneralCase;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class PermanentCaseDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function image(string $path): string
    {
        $upload = UploadedFile::fake()->image('immagine.png', 30, 40);
        Storage::disk('local')->put($path, file_get_contents($upload->getRealPath()));

        return $path;
    }

    private function document(FuneralCase $case): FuneralDocument
    {
        Storage::disk('local')->put('pdf/case-'.$case->id.'.pdf', '%PDF-1.4 documento di prova');

        return FuneralDocument::create([
            'funeral_case_id' => $case->id, 'created_by' => auth()->id(), 'kind' => 'document', 'case_type' => 'cremation',
            'version' => 1, 'file_path' => 'pdf/case-'.$case->id.'.pdf', 'snapshot' => ['name' => $case->full_name],
        ]);
    }

    public function test_single_delete_removes_case_pdf_uploads_and_links_but_keeps_catalogs(): void
    {
        $frame = ManifestTemplate::create(['title' => 'Cornice condivisa', 'image_path' => $this->image('frames/shared.png')]);
        $member = FuneralStaffMember::create(['name' => 'Operatore condiviso']);
        $vehicle = FuneralVehicle::create(['name' => 'Mezzo condiviso']);
        $case = FuneralCase::create([
            'type' => 'cremation', 'first_name' => 'Persona riservata', 'manifest_template_id' => $frame->id,
            'staff_member_id' => $member->id, 'technical_director_id' => $member->id, 'vehicle_id' => $vehicle->id,
            'photo_path' => $this->image('photos/private.png'),
            'identity_front_path' => $this->image('identity-documents/front.png'),
            'identity_back_path' => $this->image('identity-documents/back.png'),
        ]);
        $case->transportStaff()->sync([$member->id]);
        $document = $this->document($case);
        Livewire::test(ListCases::class)->callAction(TestAction::make('delete')->table($case))->assertHasNoActionErrors();
        $this->assertModelMissing($case);
        $this->assertModelMissing($document);
        $this->assertSame(0, DB::table('funeral_case_staff')->where('funeral_case_id', $case->id)->count());
        $this->assertSame(0, AuditEvent::where('funeral_case_id', $case->id)->count());
        Storage::disk('local')->assertMissing(['photos/private.png', 'identity-documents/front.png', 'identity-documents/back.png', $document->file_path]);
        Storage::disk('local')->assertExists('frames/shared.png');
        foreach ([$frame, $member, $vehicle] as $catalog) {
            $this->assertModelExists($catalog);
        }
        $audit = AuditEvent::where('event', 'permanently_deleted')->sole();
        $this->assertSame([], $audit->changed_fields['pending_files']);
        $this->assertStringNotContainsString('Persona riservata', json_encode($audit->toArray()));
        $this->get(route('private.document', $document))->assertNotFound();
    }

    public function test_admin_can_purge_previously_soft_deleted_cases_using_bulk_action(): void
    {
        $one = FuneralCase::create(['type' => 'cremation']);
        $two = FuneralCase::create(['type' => 'standard_transport']);
        $document = $this->document($one);
        $one->delete();
        $two->delete();
        Livewire::test(ListCases::class)->filterTable('trashed', false)->selectTableRecords([$one, $two])
            ->callAction(TestAction::make('delete')->table()->bulk())->assertHasNoActionErrors();
        $this->assertSame(0, FuneralCase::withTrashed()->count());
        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_delete_from_edit_page_is_permanent_and_redirects_to_list(): void
    {
        $case = FuneralCase::create(['type' => 'standard_transport']);
        Livewire::test(EditCase::class, ['record' => $case->id])->callAction('delete')->assertHasNoActionErrors()->assertRedirect();
        $this->assertModelMissing($case);
    }

    public function test_direct_force_delete_enforces_owner_policy(): void
    {
        $case = FuneralCase::create(['type' => 'cremation']);
        $document = $this->document($case);
        $this->actingAs(User::factory()->create());
        try {
            $case->forceDelete();
            $this->fail('Eliminata pratica altrui.');
        } catch (AuthorizationException) {
            $this->assertModelExists($case);
            $this->assertModelExists($document);
            Storage::disk('local')->assertExists($document->file_path);
        }
    }

    public function test_shared_upload_is_removed_only_after_last_case_is_deleted(): void
    {
        $path = $this->image('photos/shared.png');
        $one = FuneralCase::create(['type' => 'cremation', 'photo_path' => $path]);
        $two = FuneralCase::create(['type' => 'cremation', 'photo_path' => $path]);
        $one->forceDelete();
        Storage::disk('local')->assertExists($path);
        $two->forceDelete();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_database_failure_rolls_back_and_leaves_original_files_intact(): void
    {
        $case = FuneralCase::create(['type' => 'cremation']);
        $document = $this->document($case);
        Schema::create('deletion_blockers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funeral_case_id')->constrained()->restrictOnDelete();
        });
        DB::table('deletion_blockers')->insert(['funeral_case_id' => $case->id]);
        try {
            app(DeleteFuneralCase::class)->delete($case);
            $this->fail('Atteso vincolo di integrità.');
        } catch (QueryException) {
            $this->assertModelExists($case);
            $this->assertModelExists($document);
            $this->assertTrue(AuditEvent::where('funeral_case_id', $case->id)->exists());
            $this->assertSame(0, AuditEvent::where('event', 'permanently_deleted')->count());
            Storage::disk('local')->assertExists($document->file_path);
        } finally {
            Schema::dropIfExists('deletion_blockers');
        }
    }

    public function test_storage_failure_is_persisted_and_cleanup_can_retry(): void
    {
        $path = $this->image('photos/retry.png');
        $case = FuneralCase::create(['type' => 'cremation', 'photo_path' => $path]);
        $disk = Storage::disk('local');
        $blocked = true;
        $mock = Mockery::mock(FilesystemAdapter::class);
        $mock->shouldReceive('exists')->andReturnUsing(fn ($file) => $disk->exists($file));
        $mock->shouldReceive('delete')->andReturnUsing(function ($file) use ($disk, &$blocked) {
            return $blocked ? false : $disk->delete($file);
        });
        Storage::shouldReceive('disk')->with('local')->andReturn($mock);
        app(DeleteFuneralCase::class)->delete($case);
        $this->assertModelMissing($case);
        $this->assertTrue($disk->exists($path));
        $this->assertSame([$path], AuditEvent::where('event', 'permanently_deleted')->sole()->changed_fields['pending_files']);
        $blocked = false;
        $this->artisan('funeral:cleanup-deleted-files')->assertSuccessful();
        $this->assertFalse($disk->exists($path));
        $this->assertSame([], AuditEvent::where('event', 'permanently_deleted')->sole()->changed_fields['pending_files']);
    }
}
