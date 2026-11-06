<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\FuneralCase;
use App\Models\FuneralVehicle;
use App\Models\VehicleCommitment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommitVehicle
{
    public function snapshot(FuneralCase $case): array
    {
        Gate::authorize('update', $case);
        abort_if($case->trashed(), 403);
        $vehicle = FuneralVehicle::find($case->vehicle_id);
        if (! $case->transport_enabled || blank($case->first_name) || blank($case->last_name) || ! $case->transport_at || ! $vehicle?->is_active || blank($vehicle->plate)) {
            throw ValidationException::withMessages(['vehicle' => 'Per impegnare il mezzo servono nome e cognome del defunto, un mezzo attivo con targa e la data e ora del trasporto.']);
        }

        return [
            'case_code' => $case->code, 'deceased' => trim($case->first_name.' '.$case->last_name),
            'vehicle_id' => $vehicle->id, 'vehicle_name' => $vehicle->name, 'plate' => $vehicle->plate,
            'transport_at' => $case->transport_at->format('d/m/Y H:i'),
            'departure' => $case->transport_origin, 'destination' => $case->transport_destination,
        ];
    }

    public function execute(FuneralCase $case): VehicleCommitment
    {
        app(WahaClient::class)->ensureConfigured();
        if (DB::transactionLevel() !== 0) {
            throw ValidationException::withMessages(['vehicle' => 'Prima dell’invio il salvataggio deve essere completato. Nessun messaggio inviato.']);
        }
        $expected = hash('sha256', json_encode($this->snapshot($case), JSON_THROW_ON_ERROR));
        // Lock della pratica: anche il primo impegno è serializzato tra richieste concorrenti.
        $commitment = DB::transaction(function () use ($case, $expected) {
            $locked = FuneralCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->snapshot($locked);
            $fingerprint = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            if (! hash_equals($expected, $fingerprint)) {
                throw ValidationException::withMessages(['vehicle' => 'I dati del trasporto sono stati modificati nel frattempo. Ricarica la pratica e conferma nuovamente.']);
            }
            $entry = VehicleCommitment::where('funeral_case_id', $locked->id)->lockForUpdate()->first();
            if ($entry?->status === 'sending') {
                if ($entry->attempted_at->gt(now()->subMinutes(2))) {
                    throw ValidationException::withMessages(['vehicle' => 'Invio già in corso. Attendi prima di riprovare.']);
                }
                $entry->update(['status' => 'uncertain', 'error' => 'Il precedente invio non ha un esito confermato. Verifica WhatsApp prima di riprovare.']);

                return $entry;
            }
            if ($entry?->status === 'sent' && $entry->fingerprint === $fingerprint) {
                return $entry;
            }
            $entry ??= new VehicleCommitment(['funeral_case_id' => $locked->id]);
            $entry->fill([
                'user_id' => auth()->id(), 'snapshot' => $snapshot, 'fingerprint' => $fingerprint,
                'attempt_id' => (string) Str::uuid(), 'status' => 'sending', 'attempted_at' => now(),
                'error' => null, 'message_id' => null,
            ])->save();
            AuditEvent::create(['user_id' => auth()->id(), 'funeral_case_id' => $locked->id, 'event' => 'vehicle_committed',
                'changed_fields' => ['attempt_id' => $entry->attempt_id, 'vehicle_id' => $snapshot['vehicle_id'], 'fingerprint' => $fingerprint]]);

            return $entry;
        });
        if ($commitment->status !== 'sending') {
            return $commitment;
        }

        $s = $commitment->snapshot;
        $text = ($commitment->confirmed_snapshot ? 'AGGIORNAMENTO IMPEGNO MEZZO' : 'CONFERMA IMPEGNO MEZZO')
            ."\nPratica: ".$s['case_code']."\nLa pratica del defunto ".$s['deceased']." è stata confermata per il trasporto."
            ."\nMezzo scelto: ".$s['vehicle_name']."\nTarga: ".$s['plate']."\nData e ora trasporto: ".$s['transport_at']
            .(filled($s['departure']) ? "\nPartenza: ".$s['departure'] : '')
            .(filled($s['destination']) ? "\nDestinazione: ".$s['destination'] : '')
            ."\nRiferimento impegno: ".$commitment->attempt_id;
        try {
            $result = app(WahaClient::class)->send($text);
        } catch (\Throwable) {
            // Non registrare request, chiavi o dati personali nei log delle eccezioni HTTP.
            $result = ['status' => 'uncertain', 'message_id' => null, 'error' => 'Nessuna conferma da WAHA. Il messaggio potrebbe essere partito: verifica WhatsApp prima di riprovare.'];
        }

        return DB::transaction(function () use ($commitment, $result) {
            $entry = VehicleCommitment::whereKey($commitment->id)->lockForUpdate()->firstOrFail();
            if ($entry->attempt_id !== $commitment->attempt_id) {
                return $entry;
            }
            $entry->fill($result);
            if ($result['status'] === 'sent') {
                $entry->confirmed_snapshot = $entry->snapshot;
                $entry->sent_at = now();
            }
            $entry->save();
            AuditEvent::create(['user_id' => auth()->id(), 'funeral_case_id' => $entry->funeral_case_id,
                'event' => 'vehicle_whatsapp_'.$entry->status, 'changed_fields' => ['attempt_id' => $entry->attempt_id, 'message_id' => $entry->message_id]]);

            return $entry;
        });
    }
}
