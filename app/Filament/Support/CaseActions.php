<?php

namespace App\Filament\Support;

use App\Models\FuneralCase;
use App\Services\PdfGenerator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CaseActions
{
    public static function make(): array
    {
        $actions = [];
        foreach (['document' => 'Documento', 'manifest' => 'Manifesto'] as $kind => $label) {
            $actions[] = Action::make('preview_'.$kind)->label('Anteprima '.$label)->icon('heroicon-o-eye')
                ->url(fn (FuneralCase $record) => route('private.preview', [$record, $kind]))->openUrlInNewTab();
            $actions[] = Action::make('generate_'.$kind)->label('Genera '.$label)->icon('heroicon-o-document-arrow-down')
                ->visible(fn (FuneralCase $record) => Gate::allows('update', $record) && ! $record->trashed())
                ->requiresConfirmation()->modalDescription('Vengono utilizzati i dati salvati. La nuova versione PDF resta nello storico e non sovrascrive le precedenti.')
                ->action(function (FuneralCase $record) use ($kind) {
                    try {
                        $document = app(PdfGenerator::class)->generate($record, $kind);
                        Notification::make()->success()->title('PDF archiviato: versione '.$document->version)->send();

                        return redirect()->to(route('private.document', $document));
                    } catch (ValidationException $error) {
                        Notification::make()->danger()->title('Impossibile generare il PDF')->body(implode(' ', Arr::flatten($error->errors())))->persistent()->send();
                    }
                });
        }

        return $actions;
    }
}
