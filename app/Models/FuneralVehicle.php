<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class FuneralVehicle extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function funeralCases(): HasMany
    {
        return $this->hasMany(FuneralCase::class, 'vehicle_id')->withTrashed();
    }

    public function isUsed(): bool
    {
        return array_key_exists('funeral_cases_exists', $this->getAttributes())
            ? (bool) $this->funeral_cases_exists : $this->funeralCases()->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $vehicle) {
            if ($vehicle->funeralCases()->exists()) {
                throw ValidationException::withMessages(['name' => 'Il mezzo è associato a una pratica. Disattivalo per conservarne lo storico.']);
            }
        });
    }
}
