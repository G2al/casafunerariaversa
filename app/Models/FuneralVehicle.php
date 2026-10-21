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
        if (array_key_exists('funeral_cases_exists', $this->getAttributes()) && array_key_exists('prior_transfers_exists', $this->getAttributes())) {
            return $this->funeral_cases_exists || $this->prior_transfers_exists;
        }

        return $this->funeralCases()->exists() || $this->priorTransfers()->exists();
    }

    public function priorTransfers(): HasMany
    {
        return $this->hasMany(FuneralCase::class, 'prior_transfer_vehicle_id')->withTrashed();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $vehicle) {
            if ($vehicle->funeralCases()->exists() || $vehicle->priorTransfers()->exists()) {
                throw ValidationException::withMessages(['name' => 'Il mezzo è associato a una pratica. Disattivalo per conservarne lo storico.']);
            }
        });
    }
}
