<?php

namespace App\Filament\Support;

use App\Filament\Resources\FuneralCaseResource;
use App\Models\FuneralCase;
use App\Services\DeleteFuneralCase;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;

class FuneralCaseDeletionActions
{
    public static function single(): DeleteAction
    {
        return DeleteAction::make()->label('Sposta nel cestino')
            ->modalDescription('La pratica e i suoi allegati saranno conservati nel cestino. Un amministratore potrà ripristinarli.')
            ->successRedirectUrl(fn () => FuneralCaseResource::getUrl('index'));
    }

    public static function bulk(): DeleteBulkAction
    {
        return DeleteBulkAction::make()->label('Sposta nel cestino')->authorizeIndividualRecords('delete')
            ->modalDescription('Le pratiche selezionate e i relativi allegati saranno conservati nel cestino.');
    }

    public static function forceSingle(): ForceDeleteAction
    {
        return ForceDeleteAction::make()->label('Elimina definitivamente')
            ->modalDescription('Pratica, PDF, foto e allegati saranno eliminati definitivamente. Non sarà possibile ripristinarli.')
            ->successRedirectUrl(fn () => FuneralCaseResource::getUrl('index'))
            ->using(fn (FuneralCase $record): bool => app(DeleteFuneralCase::class)->delete($record));
    }

    public static function forceBulk(): ForceDeleteBulkAction
    {
        return ForceDeleteBulkAction::make()->label('Elimina definitivamente')->authorizeIndividualRecords('forceDelete')
            ->modalDescription('Le pratiche selezionate, i PDF, le foto e gli allegati saranno eliminati definitivamente.');
    }
}
