<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FuneralCaseResource\Pages\ListCases;
use App\Filament\Resources\FuneralStaffMemberResource\Pages\ManageStaff;
use App\Filament\Resources\FuneralVehicleResource\Pages\ManageVehicles;
use App\Filament\Resources\ManifestTemplateResource\Pages\ManageTemplates;
use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Models\AuditEvent;
use App\Models\FuneralCase;
use App\Models\FuneralDocument;
use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;
use App\Models\ManifestTemplate;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_operator_bulk_delete_is_scoped_and_permanent(): void
    {
        $operator = User::factory()->create();
        $this->actingAs($operator);
        $own = FuneralCase::create(['type' => 'cremation']);
        $second = FuneralCase::create(['type' => 'standard_transport']);
        $this->actingAs(User::factory()->create());
        $foreign = FuneralCase::create(['type' => 'cremation']);
        $this->actingAs($operator);
        Livewire::test(ListCases::class)->selectTableRecords([$own, $second, $foreign])
            ->callAction(TestAction::make('delete')->table()->bulk());
        $this->assertModelMissing($own);
        $this->assertModelMissing($second);
        $this->assertNotSoftDeleted($foreign);
        $this->assertSame(2, AuditEvent::where('event', 'permanently_deleted')->where('user_id', $operator->id)->count());
        $this->assertTrue(Gate::allows('forceDeleteAny', FuneralCase::class));
        Livewire::test(ListCases::class)->assertActionHidden(TestAction::make('restore')->table()->bulk());

        $this->assertSame(0, FuneralCase::withTrashed()->whereIn('id', [$own->id, $second->id])->count());
    }

    public function test_transport_bulk_delete_skips_used_records_including_archived_cases(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        foreach ([
            [FuneralVehicle::class, ManageVehicles::class, 'vehicle_id'],
            [FuneralStaffMember::class, ManageStaff::class, 'staff_member_id'],
        ] as [$model, $page, $foreignKey]) {
            $used = $model::create(['name' => 'Record utilizzato']);
            $free = $model::create(['name' => 'Record libero']);
            $second = $model::create(['name' => 'Secondo record libero']);
            $case = FuneralCase::create(['type' => 'standard_transport', $foreignKey => $used->id]);
            $case->delete();
            Livewire::test($page)->selectTableRecords([$used, $free, $second])
                ->callAction(TestAction::make('delete')->table()->bulk());
            $this->assertModelExists($used);
            $this->assertModelMissing($free);
            $this->assertModelMissing($second);
            $this->assertSame($used->id, $case->fresh()->$foreignKey);
        }
    }

    public function test_frames_bulk_delete_preserves_both_case_and_pdf_references(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);
        Storage::disk('local')->put('frames/sample.jpg', file_get_contents(base_path('docs/references/09_cornice_gesu.jpg')));
        $frames = collect(range(1, 4))->map(fn ($index) => ManifestTemplate::create(['title' => 'Cornice '.$index, 'image_path' => 'frames/sample.jpg']));
        $case = FuneralCase::create(['type' => 'cremation', 'manifest_template_id' => $frames[0]->id]);
        $document = FuneralDocument::create([
            'funeral_case_id' => $case->id, 'created_by' => $admin->id, 'kind' => 'manifest', 'case_type' => 'cremation',
            'version' => 1, 'manifest_template_id' => $frames[1]->id, 'file_path' => 'pdf/sample.pdf', 'snapshot' => [],
        ]);
        Livewire::test(ManageTemplates::class)->selectTableRecords($frames)
            ->callAction(TestAction::make('delete')->table()->bulk());
        $this->assertModelExists($frames[0]);
        $this->assertModelExists($frames[1]);
        $this->assertModelMissing($frames[2]);
        $this->assertModelMissing($frames[3]);
        $this->assertModelExists($document);
    }

    public function test_user_bulk_delete_keeps_the_last_admin_and_users_with_history(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);
        $used = User::factory()->create();
        $free = User::factory()->create();
        $second = User::factory()->create();
        $case = FuneralCase::create(['type' => 'standard_transport', 'operator_id' => $used->id]);
        Livewire::test(ManageUsers::class)->selectTableRecords([$admin, $used, $free, $second])
            ->callAction(TestAction::make('delete')->table()->bulk());
        $this->assertModelExists($admin);
        $this->assertModelExists($used);
        $this->assertModelMissing($free);
        $this->assertModelMissing($second);
        $this->assertSame($used->id, $case->fresh()->operator_id);
    }

    public function test_selecting_all_admins_cannot_remove_the_last_active_admin(): void
    {
        $first = User::factory()->create(['role' => UserRole::Admin]);
        $second = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($first);
        Livewire::test(ManageUsers::class)->selectTableRecords([$first, $second])
            ->callAction(TestAction::make('delete')->table()->bulk());
        $this->assertSame(1, User::where('role', UserRole::Admin)->where('is_active', true)->count());
    }

    public function test_operator_cannot_access_bulk_deletion_of_global_catalogs(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([ManageUsers::class, ManageTemplates::class, ManageVehicles::class, ManageStaff::class] as $page) {
            Livewire::test($page)->assertForbidden();
        }
        foreach ([User::class, ManifestTemplate::class, FuneralVehicle::class, FuneralStaffMember::class] as $model) {
            $this->assertFalse(Gate::allows('deleteAny', $model));
        }
    }
}
