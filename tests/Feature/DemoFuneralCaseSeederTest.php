<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;
use App\Models\ManifestTemplate;
use App\Models\User;
use App\Services\DocumentData;
use App\Services\PdfGenerator;
use Database\Seeders\DemoFuneralCaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoFuneralCaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_complete_demo_requires_only_personnel_and_keeps_photo_empty(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->artisan('db:seed', ['--class' => DemoFuneralCaseSeeder::class, '--force' => true])->assertSuccessful();
        $case = FuneralCase::sole();
        $this->assertNull(auth()->user());
        $this->assertSame($admin->id, $case->operator_id);
        $this->assertSame($admin->id, $case->created_by);
        $this->assertNull($case->photo_path);
        $this->assertNull($case->technical_director_id);
        $this->assertNull($case->staff_member_id);
        $this->assertSame(0, FuneralStaffMember::count());
        foreach (config('funeral.sections') as $fields) {
            foreach (array_keys($fields) as $field) {
                $this->assertNotEmpty($case->$field, 'Campo dimostrativo non compilato: '.$field);
            }
        }
        $this->assertSame([
            'Direttore tecnico attivo e abilitato', 'Operatore incaricato del trasporto',
            'Personale da riportare nel documento (selezione multipla)',
        ], app(DocumentData::class)->missing($case, 'document'));
        $this->assertSame([], app(DocumentData::class)->missing($case, 'manifest'));
        $this->actingAs($admin);
        $director = FuneralStaffMember::create([
            'name' => 'Direttore Test', 'qualification' => 'Direttore tecnico', 'credentials' => 'TEST-DT-001',
            'is_technical_director' => true, 'birth_date' => '1980-01-01', 'birth_place' => 'Aversa',
            'residence_city' => 'Aversa', 'residence_address' => 'Via Test 1',
        ]);
        $staff = collect([$director]);
        for ($i = 1; $i <= 3; $i++) {
            $staff->push(FuneralStaffMember::create(['name' => 'Operatore Test '.$i, 'qualification' => 'Operatore funebre', 'credentials' => 'TEST-OP-'.$i]));
        }
        $case->update(['technical_director_id' => $director->id, 'staff_member_id' => $staff->last()->id]);
        $case->transportStaff()->sync($staff->pluck('id')->all());
        $this->assertSame([], app(DocumentData::class)->missing($case->fresh(), 'document'));
        $rendered = app(PdfGenerator::class)->render($case->fresh(), 'document');
        $this->assertSame(4, preg_match_all('~/Type\s*/Page\b~', $rendered['bytes']));
    }

    public function test_rerun_keeps_existing_case_choices_and_company_values(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $operator = User::factory()->create();
        $this->actingAs($operator);
        CompanySettings::updateOrCreate(['id' => 1], ['data' => ['legal_name' => 'Società già configurata', 'company_authorization_protocol' => 'PROTOCOLLO-ESISTENTE']]);
        $this->seed(DemoFuneralCaseSeeder::class);
        $this->assertSame($operator->id, auth()->id());
        $case = FuneralCase::sole();
        $this->actingAs($admin);
        $member = FuneralStaffMember::create(['name' => 'Scelta manuale']);
        $case->update(['first_name' => 'Modifica manuale', 'staff_member_id' => $member->id]);
        $case->transportStaff()->sync([$member->id]);
        $this->seed(DemoFuneralCaseSeeder::class);
        $this->assertSame(1, FuneralCase::count());
        $this->assertSame(1, FuneralVehicle::count());
        $this->assertSame(1, ManifestTemplate::count());
        $this->assertSame('Modifica manuale', $case->fresh()->first_name);
        $this->assertSame([$member->id], $case->fresh()->transportStaff->modelKeys());
        $this->assertSame('Società già configurata', CompanySettings::current()['legal_name']);
        $this->assertSame('PROTOCOLLO-ESISTENTE', CompanySettings::current()['company_authorization_protocol']);
        $case->delete();
        $this->seed(DemoFuneralCaseSeeder::class);
        $this->assertSame(1, FuneralCase::withTrashed()->count());
        $this->assertTrue($case->fresh()->trashed());
    }

    public function test_no_admin_causes_no_partial_demo_data(): void
    {
        try {
            $this->seed(DemoFuneralCaseSeeder::class);
            $this->fail('Seeder avviato senza amministratore.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('amministratore attivo', $error->getMessage());
        }
        $this->assertSame(0, FuneralCase::count());
        $this->assertSame(0, CompanySettings::count());
        $this->assertSame(0, FuneralVehicle::count());
    }
}
