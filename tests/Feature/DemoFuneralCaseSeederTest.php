<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FuneralCaseResource\Pages\EditCase;
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
use Livewire\Livewire;
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

    public function test_two_realistic_cases_require_only_identity_personnel_and_photo(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->artisan('db:seed', ['--class' => DemoFuneralCaseSeeder::class, '--force' => true])->assertSuccessful();
        $cases = FuneralCase::orderBy('id')->get();
        $this->assertCount(2, $cases);
        $this->assertNull(auth()->user());
        $this->assertEqualsCanonicalizing(['cremation', 'standard_transport'], $cases->map(fn ($case) => $case->type->value)->all());
        $this->assertEqualsCanonicalizing(['Antonio Rinaldi', 'Teresa Moretti'], $cases->pluck('full_name')->all());
        $this->assertSame(0, FuneralStaffMember::count());
        $this->actingAs($admin);
        $director = FuneralStaffMember::create([
            'name' => 'Direttore Test', 'qualification' => 'Direttore tecnico', 'credentials' => 'TEST-DT-001',
            'is_technical_director' => true,
        ]);
        $staff = collect([$director]);
        for ($i = 1; $i <= 3; $i++) {
            $staff->push(FuneralStaffMember::create(['name' => 'Operatore Test '.$i, 'qualification' => 'Operatore funebre', 'credentials' => 'TEST-OP-'.$i]));
        }

        foreach ($cases as $case) {
            $this->assertSame($admin->id, $case->operator_id);
            $this->assertSame($admin->id, $case->created_by);
            foreach (['photo_path', 'identity_document_type', 'identity_front_path', 'identity_back_path', 'technical_director_id', 'staff_member_id'] as $field) {
                $this->assertNull($case->$field);
            }
            $this->assertCount(0, $case->transportStaff);
            foreach (config('funeral.sections') as $fields) {
                foreach (array_keys($fields) as $field) {
                    $this->assertNotEmpty($case->$field, 'Campo non compilato: '.$field);
                }
            }
            $printable = json_encode($case->toArray(), JSON_UNESCAPED_UNICODE);
            foreach (['DEMO', 'Dimostrativ', 'Esempio', 'Prova', '0000000000'] as $placeholder) {
                $this->assertStringNotContainsString($placeholder, $printable);
            }
            foreach (['identity_document_type', 'identity_document_number', 'identity_document_issuer', 'identity_document_issued_at', 'identity_document_expires_at'] as $field) {
                $this->assertArrayNotHasKey($field, $case->cremation_details);
            }
            $missing = app(DocumentData::class)->missing($case, 'document');
            $this->assertCount(8, $missing);
            foreach ($missing as $label) {
                $this->assertTrue(str_contains($label, 'documento') || str_contains($label, 'rilascio') || str_contains($label, 'Direttore tecnico') || str_contains($label, 'Operatore incaricato') || str_contains($label, 'Personale da riportare'), $label);
            }
            $this->assertSame([], app(DocumentData::class)->missing($case, 'manifest'));
            $this->assertTrue($case->death_date->lte($case->prior_transfer_date));
            $this->assertTrue($case->viewing_starts_at->lt($case->viewing_ends_at));
            $this->assertTrue($case->viewing_ends_at->lt($case->transfer_at));
            $this->assertTrue($case->transfer_at->lt($case->ceremony_at));
            $this->assertTrue($case->ceremony_at->lt($case->transport_at));
            $case->update([
                'technical_director_id' => $director->id, 'staff_member_id' => $staff->last()->id,
                'identity_document_type' => 'electronic_id',
                'cremation_details' => array_replace($case->cremation_details, [
                    'identity_document_number' => 'ID-TEST-001', 'identity_document_issuer' => 'Comune di Aversa',
                    'identity_document_issued_at' => '2020-01-01', 'identity_document_expires_at' => '2030-01-01',
                ]),
            ]);
            $case->transportStaff()->sync($staff->pluck('id')->all());
            $this->assertSame([], app(DocumentData::class)->missing($case->fresh(), 'document'));
            $rendered = app(PdfGenerator::class)->render($case->fresh(), 'document');
            $this->assertSame(4, preg_match_all('~/Type\s*/Page\b~', $rendered['bytes']));
            $this->assertStringNotContainsString('DEMO', json_encode($rendered['snapshot']));
        }
    }

    public function test_rerun_preserves_manual_edits_archived_cases_and_configured_company(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $operator = User::factory()->create();
        $this->actingAs($operator);
        CompanySettings::updateOrCreate(['id' => 1], ['data' => ['legal_name' => 'Società già configurata', 'company_authorization_protocol' => 'PROTOCOLLO-ESISTENTE']]);
        $this->seed(DemoFuneralCaseSeeder::class);
        $this->assertSame($operator->id, auth()->id());
        $case = FuneralCase::where('type', 'cremation')->sole();
        $this->actingAs($admin);
        $member = FuneralStaffMember::create(['name' => 'Scelta manuale']);
        Livewire::test(EditCase::class, ['record' => $case->id])->fillForm(['first_name' => 'Modifica manuale'])->call('save')->assertHasNoFormErrors();
        $case = $case->fresh();
        // Il collegamento del seeder è nell'audit: resta stabile anche se si sostituisce il JSON.
        $details = $case->cremation_details;
        unset($details['seed_reference']);
        $case->update(['staff_member_id' => $member->id, 'cremation_details' => $details]);
        $case->transportStaff()->sync([$member->id]);
        $this->seed(DemoFuneralCaseSeeder::class);
        $this->assertSame(2, FuneralCase::count());
        $this->assertSame(1, FuneralVehicle::count());
        $this->assertSame(1, ManifestTemplate::count());
        $this->assertSame('Modifica manuale', $case->fresh()->first_name);
        $this->assertSame([$member->id], $case->fresh()->transportStaff->modelKeys());
        $this->assertSame('Società già configurata', CompanySettings::current()['legal_name']);
        $this->assertSame('PROTOCOLLO-ESISTENTE', CompanySettings::current()['company_authorization_protocol']);
        $case->delete();
        $this->seed(DemoFuneralCaseSeeder::class);
        $this->assertSame(2, FuneralCase::withTrashed()->count());
        $this->assertTrue($case->fresh()->trashed());
    }

    public function test_legacy_generated_settings_are_upgraded_without_touching_old_cases(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        CompanySettings::updateOrCreate(['id' => 1], ['data' => [
            'legal_name' => 'DEMO — Impresa funebre di prova Srl', 'tax_number' => 'DEMO-PIVA',
            'company_address' => 'Via Società Esempio 10', 'email' => 'agenzia@example.test', 'phone' => '0000000000',
            'company_authorization_protocol' => 'DEMO-SCIA-001',
        ]]);
        $old = FuneralCase::create(['type' => 'cremation', 'last_name' => 'Esempio DEMO', 'internal_notes' => '[DEMO-CREMAZIONE-SEEDER]']);
        $this->seed(DemoFuneralCaseSeeder::class);
        $this->assertSame(3, FuneralCase::count());
        $this->assertSame('Esempio DEMO', $old->fresh()->last_name);
        $this->assertStringNotContainsString('DEMO', json_encode(CompanySettings::current()));
        $this->assertSame('Aurora Servizi Funebri S.r.l.', CompanySettings::current()['legal_name']);
        $this->seed(DemoFuneralCaseSeeder::class);
        $this->assertSame(3, FuneralCase::count());
    }

    public function test_no_admin_causes_no_partial_data(): void
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
