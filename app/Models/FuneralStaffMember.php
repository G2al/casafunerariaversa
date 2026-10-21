<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class FuneralStaffMember extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_technical_director' => 'boolean', 'birth_date' => 'date'];
    }

    public function funeralCases(): HasMany
    {
        return $this->hasMany(FuneralCase::class, 'staff_member_id')->withTrashed();
    }

    public function isUsed(): bool
    {
        if (array_key_exists('funeral_cases_exists', $this->getAttributes()) && array_key_exists('directed_cases_exists', $this->getAttributes()) && array_key_exists('selected_cases_exists', $this->getAttributes())) {
            return $this->funeral_cases_exists || $this->directed_cases_exists || $this->selected_cases_exists;
        }

        return $this->funeralCases()->exists() || $this->directedCases()->exists() || $this->selectedCases()->exists();
    }

    public function directedCases(): HasMany
    {
        return $this->hasMany(FuneralCase::class, 'technical_director_id')->withTrashed();
    }

    public function selectedCases(): BelongsToMany
    {
        return $this->belongsToMany(FuneralCase::class, 'funeral_case_staff')->withTrashed();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $member) {
            if ($member->funeralCases()->exists() || $member->directedCases()->exists() || $member->selectedCases()->exists()) {
                throw ValidationException::withMessages(['name' => 'Il membro del personale è associato a una pratica. Disattivalo per conservarne lo storico.']);
            }
        });
    }
}
