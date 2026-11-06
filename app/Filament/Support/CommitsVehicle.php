<?php

namespace App\Filament\Support;

use App\Services\CommitVehicle;
use App\Services\WahaClient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait CommitsVehicle
{
    protected bool $vehicleCommitSaved = false;

    protected function afterCreate(): void
    {
        $this->vehicleCommitSaved = true;
    }

    protected function afterSave(): void
    {
        $this->vehicleCommitSaved = true;
    }

    public function commitVehicle(): void
    {
        $this->vehicleCommitSaved = false;
        try {
            app(WahaClient::class)->ensureConfigured();
            validator($this->data ?? [], [
                'first_name' => 'required|string|max:255', 'last_name' => 'required|string|max:255',
                'vehicle_id' => ['required', Rule::exists('funeral_vehicles', 'id')->where('is_active', true)],
                'transport_at' => 'required|date',
            ], [], ['first_name' => 'nome del defunto', 'last_name' => 'cognome del defunto', 'vehicle_id' => 'mezzo attivo', 'transport_at' => 'data e ora trasporto'])->validate();
            if ($this instanceof CreateRecord) {
                $this->create();
            } else {
                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
            }
            if (! $this->vehicleCommitSaved || ! $this->getRecord()?->exists) {
                return;
            }
            $entry = app(CommitVehicle::class)->execute($this->getRecord());
            if ($entry->status === 'sent') {
                Notification::make()->success()->title('Mezzo impegnato')
                    ->body('WhatsApp accettato da WAHA al +39 335 737 8138. Se questi dati erano già stati inviati, il messaggio non viene duplicato.')->send();
            } else {
                Notification::make()->warning()->title('Impegno salvato: invio da verificare')->body($entry->error)->persistent()->send();
            }
        } catch (ValidationException $error) {
            Notification::make()->danger()->title('Impossibile confermare l’impegno')->body(implode(' ', Arr::flatten($error->errors())))->persistent()->send();
        }
    }
}
