<?php

namespace App\Filament\Support;

use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;
use App\Models\ManifestTemplate;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class DeletionActions
{
    public static function bulk(): DeleteBulkAction
    {
        return DeleteBulkAction::make()->label('Elimina selezionati')
            ->modalDescription('Confermi l’eliminazione dei record selezionati? I record già collegati a pratiche o PDF saranno conservati; puoi disattivarli da Modifica.')
            ->authorizeIndividualRecords(function (Model $record): Response {
                $authorization = Gate::inspect('delete', $record);
                if ($authorization->denied()) {
                    return $authorization;
                }
                if (($record instanceof FuneralStaffMember || $record instanceof FuneralVehicle || $record instanceof ManifestTemplate) && $record->isUsed()) {
                    return Response::deny('I record già utilizzati si possono disattivare da Modifica, conservando lo storico.');
                }

                return $authorization;
            });
    }
}
