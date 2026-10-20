<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FuneralStaffMemberResource\Pages\ManageStaff;
use App\Filament\Resources\FuneralVehicleResource\Pages\ManageVehicles;
use App\Models\FuneralCase;
use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TransportCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    public function test_admin_can_delete_unused_vehicles_and_staff_from_the_table(): void
    {
        $vehicle = FuneralVehicle::create(['name' => 'Mezzo dimostrativo', 'plate' => 'TEST-001']);
        $member = FuneralStaffMember::create(['name' => 'Persona dimostrativa']);
        foreach ([[ManageVehicles::class, $vehicle], [ManageStaff::class, $member]] as [$page, $record]) {
            Livewire::test($page)->callAction(TestAction::make('delete')->table($record));
            $this->assertModelMissing($record);
        }
    }

    public function test_references_in_archived_cases_protect_records_and_disabling_remains_possible(): void
    {
        $vehicle = FuneralVehicle::create(['name' => 'Mezzo dimostrativo', 'plate' => 'TEST-001']);
        $member = FuneralStaffMember::create(['name' => 'Persona dimostrativa']);
        $case = FuneralCase::create(['type' => 'standard_transport', 'vehicle_id' => $vehicle->id, 'staff_member_id' => $member->id]);
        $case->delete();
        foreach ([[ManageVehicles::class, $vehicle], [ManageStaff::class, $member]] as [$page, $record]) {
            Livewire::test($page)->assertActionDisabled(TestAction::make('delete')->table($record));
            try {
                $record->delete();
                $this->fail('Non si possono eliminare riferimenti utilizzati dalle pratiche.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('name', $error->errors());
            }
            Livewire::test($page)->callAction(TestAction::make('edit')->table($record), data: ['is_active' => false])->assertHasNoActionErrors();
            $this->assertFalse($record->fresh()->is_active);
        }
        $this->assertSame($vehicle->id, $case->fresh()->vehicle_id);
        $this->assertSame($member->id, $case->fresh()->staff_member_id);
    }

    public function test_operators_cannot_manage_or_delete_catalog_records(): void
    {
        $vehicle = FuneralVehicle::create(['name' => 'Mezzo dimostrativo', 'plate' => 'TEST-001']);
        $member = FuneralStaffMember::create(['name' => 'Persona dimostrativa']);
        $operator = User::factory()->create();
        $this->actingAs($operator);
        foreach ([[ManageVehicles::class, $vehicle], [ManageStaff::class, $member]] as [$page, $record]) {
            Livewire::test($page)->assertForbidden();
            $this->assertFalse(Gate::allows('delete', $record));
        }
    }
}
