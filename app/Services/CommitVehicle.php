<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\FuneralCase;
use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;
use App\Models\VehicleCommitment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommitVehicle
{
    public function execute(FuneralCase $case, bool $force = false): VehicleCommitment
    {
        return DB::transaction(function () use ($case, $force) {
            $case = FuneralCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $case);
            abort_if($case->trashed(), 403);
            $vehicle = FuneralVehicle::whereKey($case->vehicle_id)->lockForUpdate()->first();
            $director = FuneralStaffMember::whereKey($case->technical_director_id)->where('is_active', true)->where('is_technical_director', true)->first();
            $interval = app(ReservationConflicts::class)->interval($case->transport_at, $case->transport_ends_at);
            if (! $interval || ! $vehicle?->is_active || ! $director || blank($case->first_name) || blank($case->last_name)) {
                throw ValidationException::withMessages(['vehicle_id' => 'Servono defunto, mezzo attivo, direttore tecnico e inizio/fine impegno validi.']);
            }
            $conflicts = app(ReservationConflicts::class)->vehicles($vehicle->id, $interval[0], $interval[1], $case->id, true);
            if ($conflicts->isNotEmpty() && ! $force) {
                throw ValidationException::withMessages(['vehicle_id' => 'Questo mezzo è già impegnato. Cambia gli orari o conferma la forzatura a tua responsabilità.']);
            }
            $snapshot = ['case_code' => $case->code, 'deceased' => $case->full_name, 'vehicle_id' => $vehicle->id, 'vehicle_name' => $vehicle->name, 'plate' => $vehicle->plate,
                'transport_at' => $interval[0]->format('d/m/Y H:i'), 'transport_ends_at' => $interval[1]->format('d/m/Y H:i'),
                'director' => $director->name, 'departure' => $case->transport_origin, 'destination' => $case->transport_destination];
            $entry = VehicleCommitment::updateOrCreate(['funeral_case_id' => $case->id], [
                'user_id' => auth()->id(), 'vehicle_id' => $vehicle->id, 'technical_director_id' => $director->id, 'snapshot' => $snapshot,
                'fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'attempt_id' => (string) Str::uuid(), 'status' => 'committed',
                'starts_at' => $interval[0], 'ends_at' => $interval[1], 'attempted_at' => now(), 'overlap_forced' => $conflicts->isNotEmpty(), 'error' => null,
                'message_id' => null, 'sent_at' => null, 'confirmed_snapshot' => null,
            ]);
            AuditEvent::create(['user_id' => auth()->id(), 'funeral_case_id' => $case->id, 'event' => 'vehicle_committed',
                'changed_fields' => ['vehicle_id' => $vehicle->id, 'starts_at' => $interval[0]->toIso8601String(), 'ends_at' => $interval[1]->toIso8601String(), 'forced' => $conflicts->isNotEmpty()]]);

            return $entry;
        });
    }

    public function whatsappUrl(VehicleCommitment $entry): string
    {
        $s = $entry->snapshot;
        $text = "CONFERMA IMPEGNO MEZZO\nPratica: ".$s['case_code']."\nDefunto: ".$s['deceased']."\nMezzo: ".$s['vehicle_name']."\nTarga: ".$s['plate']
            ."\nInizio impegno: ".$s['transport_at']."\nFine impegno: ".$s['transport_ends_at']."\nDirettore tecnico: ".$s['director']
            .(filled($s['departure']) ? "\nPartenza: ".$s['departure'] : '').(filled($s['destination']) ? "\nDestinazione: ".$s['destination'] : '');

        return 'https://wa.me/393296430362?text='.rawurlencode($text);
    }
}
