<?php

namespace App\Services;

use App\Events\OccupancyChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OccupancyUpdates
{
    public static function changed(): void
    {
        if (! config('occupancy.enabled')) {
            return;
        }
        DB::afterCommit(static function () {
            try {
                OccupancyChanged::dispatch();
            } catch (\Throwable $e) {
                // Un guasto della coda non annulla una prenotazione già salvata.
                Log::error('Aggiornamento occupazioni non accodato. Verificare il servizio realtime.', ['exception' => $e::class]);
            }
        });
    }
}
