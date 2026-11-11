<?php

namespace App\Models;

use App\Services\PrivateIdentityDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FuneralVehicle extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'attachments' => 'array', 'attachment_names' => 'array'];
    }

    public function funeralCases(): HasMany
    {
        return $this->hasMany(FuneralCase::class, 'vehicle_id')->withTrashed();
    }

    public function isUsed(): bool
    {
        if (array_key_exists('funeral_cases_exists', $this->getAttributes()) && array_key_exists('prior_transfers_exists', $this->getAttributes())) {
            return $this->funeral_cases_exists || $this->prior_transfers_exists || VehicleCommitment::where('vehicle_id', $this->id)->exists();
        }

        return $this->funeralCases()->exists() || $this->priorTransfers()->exists() || VehicleCommitment::where('vehicle_id', $this->id)->exists();
    }

    public function priorTransfers(): HasMany
    {
        return $this->hasMany(FuneralCase::class, 'prior_transfer_vehicle_id')->withTrashed();
    }

    protected static function booted(): void
    {
        static::saving(function (self $vehicle) {
            if ($vehicle->isDirty('attachments')) {
                validator(['attachments' => $vehicle->attachments], ['attachments' => 'nullable|array|max:50', 'attachments.*' => 'required|string|distinct'])->validate();
                foreach ($vehicle->attachments ?? [] as $path) {
                    app(PrivateIdentityDocument::class)->validate($path, 'vehicle-attachments');
                }
            }
            $vehicle->attachment_names = array_intersect_key($vehicle->attachment_names ?? [], array_flip($vehicle->attachments ?? []));
        });
        static::updated(function (self $vehicle) {
            $removed = array_diff($vehicle->getOriginal('attachments') ?? [], $vehicle->attachments ?? []);
            self::cleanupAttachments($removed);
        });
        static::deleted(fn (self $vehicle) => self::cleanupAttachments($vehicle->attachments ?? []));
        static::deleting(function (self $vehicle) {
            if ($vehicle->isUsed()) {
                throw ValidationException::withMessages(['name' => 'Il mezzo è associato a una pratica. Disattivalo per conservarne lo storico.']);
            }
        });
    }

    private static function cleanupAttachments(array $paths): void
    {
        DB::afterCommit(function () use ($paths) {
            foreach ($paths as $path) {
                if (str_starts_with($path, 'vehicle-attachments/') && ! str_contains($path, '..') && ! str_contains($path, '\\')
                    && ! self::whereJsonContains('attachments', $path)->exists()) {
                    Storage::disk('local')->delete($path);
                }
            }
        });
    }
}
