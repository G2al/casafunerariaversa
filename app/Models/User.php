<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->is_active && $this->role === UserRole::Admin;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->role !== null;
    }

    public function isLastActiveAdmin(): bool
    {
        return $this->isAdmin() && static::where('role', 'admin')->where('is_active', true)->count() === 1;
    }

    public function hasHistoricalRecords(): bool
    {
        return FuneralCase::withTrashed()->where(fn ($query) => $query->where('created_by', $this->id)->orWhere('operator_id', $this->id))->exists()
            || FuneralDocument::where('created_by', $this->id)->exists();
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            $admins = static::where('role', 'admin')->where('is_active', true)->lockForUpdate()->pluck('id');
            if ($this->exists && $admins->contains($this->id) && (! $this->isAdmin()) && $admins->count() === 1) {
                throw ValidationException::withMessages(['role' => 'Non puoi disabilitare o declassare l’ultimo amministratore.']);
            }
            if (! $this->exists && ! $this->role) {
                throw ValidationException::withMessages(['role' => 'Seleziona un ruolo esplicito.']);
            }

            return parent::save($options);
        });
    }

    public function delete()
    {
        return DB::transaction(function () {
            $admins = static::where('role', 'admin')->where('is_active', true)->lockForUpdate()->pluck('id');
            if ($admins->contains($this->id) && $admins->count() === 1) {
                throw ValidationException::withMessages(['role' => 'Non puoi eliminare l’ultimo amministratore.']);
            }
            if ($this->hasHistoricalRecords()) {
                throw ValidationException::withMessages(['role' => 'L’utente è associato a pratiche o PDF: disabilitalo per conservare lo storico.']);
            }

            return parent::delete();
        });
    }
}
