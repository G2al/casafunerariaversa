<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FuneralCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FuneralDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_ownership_numbering_age_and_private_scope(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($a);
        $case = FuneralCase::create(['type' => 'cremation', 'operator_id' => $b->id, 'birth_date' => '1950-11-20', 'death_date' => '2026-10-18']);
        $this->assertSame($a->id, $case->operator_id);
        $this->assertSame($a->id, $case->created_by);
        $this->assertSame(75, $case->age);
        $this->assertSame('FUN-'.now()->year.'-0001', $case->code);
        $this->assertTrue(Gate::forUser($a)->allows('update', $case));
        $this->assertFalse(Gate::forUser($b)->allows('view', $case));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $case));
        $this->assertSame(0, FuneralCase::visibleTo($b)->count());
        $case->update(['operator_id' => $b->id]);
        $this->assertSame($a->id, $case->fresh()->operator_id);
        $second = FuneralCase::create(['type' => 'standard_transport']);
        $this->assertStringEndsWith('0002', $second->code);
        $this->assertNull($second->age);
        $case->delete();
        $this->assertSoftDeleted($case);
        $this->assertFalse(Gate::forUser($a)->allows('restore', $case));
        $this->assertTrue(Gate::forUser($admin)->allows('restore', $case));
    }

    public function test_impossible_dates_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->expectException(ValidationException::class);
        FuneralCase::create(['type' => 'cremation', 'birth_date' => '2000-01-01', 'death_date' => '1999-01-01']);
    }

    public function test_last_admin_cannot_be_demoted_or_disabled(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $this->expectException(ValidationException::class);
        $user->update(['is_active' => false]);
    }
}
