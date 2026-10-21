<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FuneralCase;
use App\Models\User;
use App\Services\CaseReports;
use App\Services\CsvExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReportsAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregations_monthly_filters_and_csv_respect_owner(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a);
        $own = FuneralCase::create(['type' => 'cremation', 'first_name' => '=FORMULA()', 'last_name' => 'Esempio A', 'status' => 'in_progress', 'registered_at' => now(), 'death_date' => '2025-01-01']);
        $this->actingAs($b);
        $other = FuneralCase::create(['type' => 'standard_transport', 'last_name' => 'Riservato B', 'status' => 'completed', 'registered_at' => now()->subMonth()]);
        $service = app(CaseReports::class);
        $this->assertSame(1, $service->summary($a)['total']);
        $this->assertSame(1, $service->summary($a)['cremation']);
        $this->assertSame(0, $service->summary($a)['standard_transport']);
        $this->assertSame(2, $service->summary($admin)['total']);
        $this->assertSame(1, $service->summary($admin, ['from' => now()->startOfMonth()->format('Y-m-d')])['total']);
        $this->assertSame(1, $service->summary($a, ['date_field' => 'death_date', 'from' => '2025-01-01', 'until' => '2025-01-31'])['total']);
        $this->assertSame(0, $service->summary($a, ['date_field' => 'registered_at', 'from' => '2025-01-01', 'until' => '2025-01-31'])['total']);
        $this->assertCount(12, $service->monthly($a));
        $this->assertSame(1, array_sum($service->monthly($a)));
        $rows = collect($service->operators($admin))->keyBy('name');
        $this->assertSame(1, $rows[$a->name]['created']);
        $this->assertSame(1, $rows[$b->name]['standard_transport']);
        $this->actingAs($a);
        $response = app(CsvExport::class)->cases(FuneralCase::query());
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString("'=FORMULA()", $csv);
        $this->assertStringNotContainsString('Riservato B', $csv);
        $own->delete();
        $this->assertSame(1, $service->summary($a)['archived']);
        $this->assertSame(0, $service->summary($a)['in_progress']);
        $this->expectException(HttpException::class);
        $service->operators($a);
    }

    public function test_guests_redirect_to_login_and_disabled_users_are_blocked(): void
    {
        $this->get('/private/documents/123')->assertRedirect('/admin/login');
        $user = User::factory()->create();
        $this->actingAs($user);
        $case = FuneralCase::create(['type' => 'cremation']);
        $user->update(['is_active' => false]);
        $this->get('/admin')->assertForbidden();
        $this->get(route('private.photo', $case))->assertForbidden();
    }

    public function test_incremental_migration_preserves_existing_account_and_promotes_only_single_user(): void
    {
        // Tutto avviene nel database SQLite in memoria creato da RefreshDatabase.
        $migration = require database_path('migrations/2026_10_19_000100_create_funeral_management.php');
        $requestMigration = require database_path('migrations/2026_10_21_000100_add_cremation_request_fields.php');
        $packetMigration = require database_path('migrations/2026_10_21_000200_add_cremation_packet_details.php');
        $packetMigration->down();
        $requestMigration->down();
        $migration->down();
        $id = DB::table('users')->insertGetId(['name' => 'Amministratore fittizio', 'email' => 'admin@example.test', 'password' => 'hash-di-test', 'created_at' => now(), 'updated_at' => now()]);
        $migration->up();
        $this->assertSame('admin', DB::table('users')->where('id', $id)->value('role'));
        $this->assertSame('hash-di-test', DB::table('users')->where('id', $id)->value('password'));
        $migration->down();
        DB::table('users')->insert(['name' => 'Secondo account fittizio', 'email' => 'second@example.test', 'password' => 'hash-di-test']);
        $migration->up();
        $this->assertSame(0, DB::table('users')->whereNotNull('role')->count());
        $requestMigration->up();
        $packetMigration->up();
    }
}
