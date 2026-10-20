<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InitialUsersSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [];
        foreach (UserRole::cases() as $role) {
            $account = $this->prepare($role);
            if ($account !== null) {
                $accounts[] = $account;
            }
        }

        DB::transaction(function () use ($accounts) {
            foreach ($accounts as $account) {
                $existing = User::where('email', $account['email'])->first();
                if ($existing) {
                    $this->ensureMatchingAccount($existing, $account['role']);

                    continue;
                }
                User::create($account + ['is_active' => true]);
            }
        });

        $this->command?->info('Utenti iniziali verificati. Gli account già presenti non sono stati modificati.');
    }

    private function prepare(UserRole $role): ?array
    {
        $data = config('initial_users.'.$role->value, []);
        $interactive = $this->command && ! $this->command->option('no-interaction');
        $email = trim((string) ($data['email'] ?? ''));

        if ($email === '') {
            if ($role === UserRole::Operator && blank($data['password'] ?? null)) {
                return null;
            }
            if ($role === UserRole::Admin && blank($data['password'] ?? null) && User::where('role', $role->value)->where('is_active', true)->exists()) {
                return null;
            }
            if (! $interactive || $role === UserRole::Operator) {
                throw ValidationException::withMessages([$role->value => 'Configura INITIAL_'.strtoupper($role->value).'_EMAIL e la relativa password per creare questo account.']);
            }
            $email = (string) $this->command->ask('Email del primo amministratore');
        }

        $email = mb_strtolower(trim($email));
        Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->validate();
        $existing = User::where('email', $email)->first();
        if ($existing) {
            $this->ensureMatchingAccount($existing, $role);

            return null;
        }

        $name = trim((string) ($data['name'] ?? $role->getLabel()));
        $password = $data['password'] ?? null;
        if (blank($password) && $interactive) {
            $password = $this->command->secret('Password '.$role->getLabel().' (almeno 12 caratteri)');
        }
        Validator::make(['name' => $name, 'password' => $password], [
            'name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:12', 'max:128'],
        ], [
            'password.required' => 'Imposta una password per il nuovo account.',
            'password.min' => 'La password deve contenere almeno 12 caratteri.',
        ])->validate();

        return ['name' => $name, 'email' => $email, 'password' => $password, 'role' => $role];
    }

    private function ensureMatchingAccount(User $user, UserRole $role): void
    {
        if ($user->role !== $role || ! $user->is_active) {
            throw ValidationException::withMessages([$role->value => 'L’email configurata appartiene a un account inattivo o con ruolo diverso. Il seeder non cambia ruoli, password o stato degli utenti esistenti.']);
        }
    }
}
