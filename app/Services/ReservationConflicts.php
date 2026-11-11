<?php

namespace App\Services;

use App\Models\FuneralCase;
use App\Models\Room;
use App\Models\VehicleCommitment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReservationConflicts
{
    public function interval(mixed $start, mixed $end): ?array
    {
        try {
            if (blank($start) || blank($end)) {
                return null;
            }
            $start = Carbon::parse($start);
            $end = Carbon::parse($end);

            return $end->gt($start) ? [$start, $end] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function rooms(mixed $room, mixed $start, mixed $end, ?int $caseId = null, bool $locking = false): Collection
    {
        $i = $this->interval($start, $end);
        if (! $room || ! $i) {
            return collect();
        }

        return FuneralCase::where('room_id', $room)->when($caseId, fn ($q) => $q->where('id', '!=', $caseId))
            ->where('viewing_starts_at', '<', $i[1])->where('viewing_ends_at', '>', $i[0])->when($locking, fn ($q) => $q->lockForUpdate())->get();
    }

    public function vehicles(mixed $vehicle, mixed $start, mixed $end, ?int $caseId = null, bool $locking = false): Collection
    {
        $i = $this->interval($start, $end);
        if (! $vehicle || ! $i) {
            return collect();
        }

        return VehicleCommitment::where('vehicle_id', $vehicle)->where('status', 'committed')
            ->when($caseId, fn ($q) => $q->where('funeral_case_id', '!=', $caseId))->whereHas('funeralCase')
            ->where('starts_at', '<', $i[1])->where('ends_at', '>', $i[0])->when($locking, fn ($q) => $q->lockForUpdate())->get();
    }

    public function guardRoom(FuneralCase $case): void
    {
        if (! $case->room_id) {
            return;
        }
        if (! $this->interval($case->viewing_starts_at, $case->viewing_ends_at)) {
            throw ValidationException::withMessages(['viewing_ends_at' => 'Per occupare la stanza servono inizio e fine esposizione, con fine successiva all’inizio.']);
        }
        Room::whereKey($case->room_id)->lockForUpdate()->firstOrFail();
        if ($this->rooms($case->room_id, $case->viewing_starts_at, $case->viewing_ends_at, $case->id, true)->isNotEmpty() && ! $case->allowRoomOverlap) {
            throw ValidationException::withMessages(['room_id' => 'Stanza già impegnata. Cambia stanza/orari oppure conferma la forzatura a tua responsabilità.']);
        }
    }
}
