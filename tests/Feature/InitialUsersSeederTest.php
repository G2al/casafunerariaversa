<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InitialUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['initial_users' => ['admin' => [], 'operator' => []]]);
    }

    public function test_creates_admin_and_optional_operator_with_hashed_passwords(): void
    {
        config(['initial_users' => [
            'admin' => ['name' => 'Admin fittizio', 'email' => 'admin@example.test', 'password' => 'Admin-test-password-2026'],
            'operator' => ['name' => 'Operatore fittizio', 'email' => 'operator@example.test', 'password' => 'Operator-test-password-2026'],
        ]]);
        $this->artisan('db:seed', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $operator = User::where('email', 'operator@example.test')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame(UserRole::Operator, $operator->role);
        $this->assertTrue($operator->is_active);
        $this->assertTrue(Hash::check('Admin-test-password-2026', $admin->password));
        $this->assertTrue(Hash::check('Operator-test-password-2026', $operator->password));
        $original = $admin->refresh()->getRawOriginal();
        config(['initial_users.admin.name' => 'Nome diverso', 'initial_users.admin.password' => 'Password-diversa-2026']);
        $this->artisan('db:seed', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertSame(2, User::count());
        $this->assertSame($original, $admin->fresh()->getRawOriginal());
    }

    public function test_existing_admin_is_preserved_without_configuration_or_demo_users(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $original = $admin->refresh()->getRawOriginal();
        $this->artisan('db:seed', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertSame(1, User::count());
        $this->assertSame($original, $admin->fresh()->getRawOriginal());
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_empty_database_requires_credentials_in_non_interactive_mode(): void
    {
        try {
            $this->artisan('db:seed', ['--force' => true, '--no-interaction' => true])->run();
            $this->fail('Il seeder deve richiedere credenziali esplicite.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('admin', $error->errors());
        }
        $this->assertSame(0, User::count());
    }

    public function test_interactive_bootstrap_asks_for_email_and_hidden_password(): void
    {
        $this->artisan('db:seed', ['--force' => true])
            ->expectsQuestion('Email del primo amministratore', 'admin@example.test')
            ->expectsQuestion('Password Amministratore (almeno 12 caratteri)', 'Password-test-interattiva-2026')
            ->assertSuccessful();
        $this->assertTrue(User::firstOrFail()->isAdmin());
    }

    public function test_rejects_role_conflicts_without_promoting_or_creating_partial_accounts(): void
    {
        $operator = User::factory()->create(['email' => 'existing@example.test']);
        config(['initial_users.admin' => ['email' => $operator->email, 'password' => 'Password-test-2026']]);
        try {
            $this->artisan('db:seed', ['--force' => true, '--no-interaction' => true])->run();
            $this->fail('Il seeder non deve promuovere account preesistenti.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('admin', $error->errors());
        }
        $this->assertSame(UserRole::Operator, $operator->fresh()->role);
        $this->assertSame(1, User::count());
    }

    public function test_invalid_operator_password_prevents_partial_bootstrap(): void
    {
        config(['initial_users' => [
            'admin' => ['email' => 'admin@example.test', 'password' => 'Password-admin-test-2026'],
            'operator' => ['email' => 'operator@example.test', 'password' => 'corta'],
        ]]);
        try {
            $this->artisan('db:seed', ['--force' => true, '--no-interaction' => true])->run();
            $this->fail('La password corta deve essere rifiutata.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('password', $error->errors());
        }
        $this->assertSame(0, User::count());
    }
}
