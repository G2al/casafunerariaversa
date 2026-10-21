<?php

namespace App\Filament\Support;

use App\Filament\Resources\FuneralCaseResource;
use App\Models\FuneralCase;
use App\Services\DeleteFuneralCase;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;

class FuneralCaseDeletionActions
{
    public static function single(): DeleteAction
    {
        return DeleteAction::make()->label('Elimina')->hidden(false)->authorize('forceDelete')
            ->modalHeading('Elimina definitivamente la pratica')
            ->modalDescription('La pratica, tutti i PDF, la foto e i documenti allegati verranno eliminati definitivamente. Non sarà possibile ripristinarli.')
            ->tooltip('Elimina definitivamente pratica, PDF e allegati.')
            ->successRedirectUrl(fn () => FuneralCaseResource::getUrl('index'))
            ->using(fn (FuneralCase $record): bool => app(DeleteFuneralCase::class)->delete($record));
    }

    public static function bulk(): DeleteBulkAction
    {
        return DeleteBulkAction::make()->label('Elimina selezionate')->hidden(false)->authorize('forceDeleteAny')->authorizeIndividualRecords('forceDelete')
            ->modalHeading('Elimina definitivamente le pratiche selezionate')
            ->modalDescription('Le pratiche selezionate, tutti i PDF, le foto e i documenti allegati verranno eliminati definitivamente. Non sarà possibile ripristinarli.')
            ->using(function (DeleteBulkAction $action, Collection $records) {
                foreach ($records as $record) {
                    try {
                        app(DeleteFuneralCase::class)->delete($record);
                    } catch (\Throwable $error) {
                        $action->reportBulkProcessingFailure();
                        report($error);
                    }
                }
            });
    }
}
