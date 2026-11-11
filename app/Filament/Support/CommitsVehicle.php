<?php

namespace App\Filament\Support;

use App\Services\CommitVehicle;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait CommitsVehicle
{
    public function commitVehicle()
    {
        Gate::authorize('update', $this->getRecord());
        try {
            $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
            $entry = app(CommitVehicle::class)->execute($this->getRecord(), (bool) ($this->data['override_vehicle_conflict'] ?? false));
            Notification::make()->success()->title('Mezzo impegnato')->body('Impegno registrato. Premi Invia in WhatsApp per spedire il messaggio.')->send();

            return redirect()->away(app(CommitVehicle::class)->whatsappUrl($entry));
        } catch (ValidationException $e) {
            Notification::make()->warning()->title('Impegno da verificare')->body(implode(' ', Arr::flatten($e->errors())))->persistent()->send();
        }
    }
}
