<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\AgencySettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\OperatorReport;
use App\Filament\Resources\FuneralCaseResource\Pages\CreateCase;
use App\Filament\Resources\FuneralCaseResource\Pages\EditCase;
use App\Filament\Resources\FuneralCaseResource\Pages\ListCases;
use App\Filament\Resources\FuneralCaseResource\Pages\ViewCase;
use App\Filament\Resources\FuneralVehicleResource\Pages\ManageVehicles;
use App\Filament\Resources\ManifestTemplateResource\Pages\ManageTemplates;
use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Models\FuneralCase;
use App\Models\ManifestTemplate;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_screens_render(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        foreach ([ManageUsers::class, ManageTemplates::class, ManageVehicles::class, AgencySettings::class, OperatorReport::class, Dashboard::class, ListCases::class, CreateCase::class] as $page) {
            Livewire::test($page)->assertSuccessful();
        }
    }

    public function test_operator_can_create_incomplete_case_and_reopen_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(CreateCase::class)->fillForm(['type' => 'cremation', 'status' => 'draft'])->call('create')->assertHasNoFormErrors();
        $case = FuneralCase::firstOrFail();
        $this->assertSame($user->id, $case->operator_id);
        Livewire::test(EditCase::class, ['record' => $case->id])->assertSuccessful()->fillForm(['first_name' => 'Persona', 'last_name' => 'Esempio'])->call('save')->assertHasNoFormErrors();
        Livewire::test(ViewCase::class, ['record' => $case->id])->assertSuccessful();
        $this->assertSame('Persona', $case->fresh()->first_name);
    }

    public function test_partial_dates_remain_optional_and_impossible_dates_are_reported_in_the_form(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(CreateCase::class)->fillForm(['type' => 'standard_transport', 'birth_date' => '1950-01-01'])->call('create')->assertHasNoFormErrors();
        $case = FuneralCase::firstOrFail();
        Livewire::test(EditCase::class, ['record' => $case->id])->assertFormSet(['manifest_options.show_photo' => true, 'manifest_options.show_closing' => true]);
        Livewire::test(CreateCase::class)->fillForm(['type' => 'cremation', 'birth_date' => '2025-02-30'])->call('create')->assertHasFormErrors(['birth_date']);
        Livewire::test(CreateCase::class)->fillForm(['type' => 'cremation', 'birth_date' => '2000-01-01', 'death_date' => '1999-01-01'])->call('create')->assertHasFormErrors(['birth_date']);
    }

    public function test_other_operator_cannot_open_case_or_admin_pages(): void
    {
        $a = User::factory()->create();
        $this->actingAs($a);
        $case = FuneralCase::create(['type' => 'cremation']);
        $this->actingAs(User::factory()->create());
        Livewire::test(ListCases::class)->assertCanNotSeeTableRecords([$case]);
        Livewire::test(EditCase::class, ['record' => $case->id])->assertNotFound();
        Livewire::test(ManageUsers::class)->assertForbidden();
        Livewire::test(ManageTemplates::class)->assertForbidden();
        Livewire::test(AgencySettings::class)->assertForbidden();
        Livewire::test(OperatorReport::class)->assertForbidden();
    }

    public function test_gallery_with_uploaded_frame_and_pdf_actions_render(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $image = UploadedFile::fake()->image('cornice.jpg', 1600, 1131);
        Livewire::test(ManageTemplates::class)->callAction(CreateAction::class, data: ['title' => 'Cornice dimostrativa', 'image_path' => $image, 'sort_order' => 1, 'is_active' => true])->assertHasNoActionErrors();
        $frame = ManifestTemplate::firstOrFail();
        $case = FuneralCase::create(['type' => 'cremation', 'manifest_template_id' => $frame->id]);
        Livewire::test(EditCase::class, ['record' => $case->id])->assertSuccessful()->assertSee('Cornice dimostrativa');
        Livewire::test(ViewCase::class, ['record' => $case->id])->assertSuccessful();
        Livewire::test(ManageTemplates::class)->assertActionDisabled(TestAction::make('delete')->table($frame));
        $this->assertDatabaseHas('manifest_templates', ['id' => $frame->id]);
        $this->expectException(ValidationException::class);
        $frame->delete();
    }

    public function test_user_management_requires_explicit_role_and_protects_last_admin_in_the_form(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);
        Livewire::test(ManageUsers::class)->callAction(CreateAction::class, data: [
            'name' => 'Operatore dimostrativo', 'email' => 'operator@example.test', 'password' => 'Password-di-test-2026', 'role' => 'operator', 'is_active' => true,
        ])->assertHasNoActionErrors();
        $this->assertDatabaseHas('users', ['email' => 'operator@example.test', 'role' => 'operator']);
        Livewire::test(ManageUsers::class)->callAction(TestAction::make('edit')->table($admin), data: ['role' => 'operator'])->assertHasActionErrors(['role']);
        Livewire::test(ManageUsers::class)->callAction(TestAction::make('edit')->table($admin), data: ['is_active' => false])->assertHasActionErrors(['is_active']);
    }

    public function test_filtered_csv_action_downloads_the_scoped_list(): void
    {
        $this->actingAs(User::factory()->create());
        FuneralCase::create(['type' => 'cremation']);
        Livewire::test(ListCases::class)->filterTable('type', 'cremation')->callAction(TestAction::make('csv')->table())->assertFileDownloaded('pratiche-'.now()->format('Ymd-His').'.csv');
    }
}
