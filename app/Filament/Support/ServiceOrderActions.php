<?php

namespace App\Filament\Support;

use App\Filament\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use App\Services\ServiceOrders;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

class ServiceOrderActions
{
    public static function delete(bool $redirect = false): Action
    {
        return Action::make('deleteOrder')->label('Elimina ordine')->icon('heroicon-o-trash')->color('danger')
            ->visible(fn (ServiceOrder $record) => Gate::allows('delete', $record))
            ->requiresConfirmation()->modalHeading('Elimina e azzera ordine di servizio')
            ->modalDescription('Ordine, foto e storico dell’ordine verranno eliminati definitivamente. Se confermato, sarà escluso dai conteggi. Potrai preparare un nuovo ordine nella stessa pratica.')
            ->modalSubmitActionLabel('Elimina e azzera')
            ->action(function (ServiceOrder $record) use ($redirect) {
                app(ServiceOrders::class)->delete($record->id, $record->revision);
                Notification::make()->success()->title('Ordine eliminato')->send();

                return $redirect ? redirect(ServiceOrderResource::getUrl('index')) : null;
            });
    }
}
