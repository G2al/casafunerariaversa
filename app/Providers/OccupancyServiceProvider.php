<?php

namespace App\Providers;

use App\Models\FuneralCase;
use App\Models\FuneralVehicle;
use App\Models\Room;
use App\Models\User;
use App\Models\VehicleCommitment;
use App\Services\OccupancyUpdates;
use Illuminate\Support\ServiceProvider;

class OccupancyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach ([Room::class, FuneralVehicle::class, VehicleCommitment::class] as $model) {
            $model::saved(static fn () => OccupancyUpdates::changed());
            $model::deleted(static fn () => OccupancyUpdates::changed());
        }
        FuneralCase::saved(static function (FuneralCase $case) {
            if ($case->wasRecentlyCreated || $case->wasChanged(['room_id', 'viewing_starts_at', 'viewing_ends_at', 'operator_id', 'first_name', 'last_name'])) {
                OccupancyUpdates::changed();
            }
        });
        FuneralCase::deleted(static fn () => OccupancyUpdates::changed());
        FuneralCase::restored(static fn () => OccupancyUpdates::changed());
        User::updated(static function (User $user) {
            if ($user->wasChanged(['is_active', 'role'])) {
                OccupancyUpdates::changed();
            }
        });
    }
}
