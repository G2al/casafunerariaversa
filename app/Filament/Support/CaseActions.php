<?php

namespace App\Filament\Support;

use App\Filament\Resources\FuneralCaseResource;
use App\Filament\Resources\ManifestRecordResource;
use App\Http\Controllers\ManifestRecordController;
use App\Models\FuneralCase;
use App\Services\ManifestRecords;
use App\Services\PdfGenerator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CaseActions
{
    public static function ensureSaved($livewire): void
    {
        if ($livewire instanceof EditRecord && isset($livewire->savedDataHash)) {
            $hash = md5(str_replace(chr(92), '', (string) json_encode($livewire->data, JSON_UNESCAPED_UNICODE)));
            if ($hash !== $livewire->savedDataHash) {
                Notification::make()->warning()->title('Ci sono modifiche non salvate')->body('Salva la scheda prima di aprire questa funzione.')->persistent()->send();
                throw new Halt;
            }
        }
    }

    public static function make(): array
    {
        $actions = [Action::make('edit_manifest')->label('Modifica manifesto live')->icon('heroicon-o-paint-brush')
            ->visible(fn (FuneralCase $record) => Gate::allows('update', $record) && ! $record->trashed())
            ->action(function (FuneralCase $record, $livewire) {
                self::ensureSaved($livewire);
                $manifest = app(ManifestRecords::class)->forCase($record);

                return redirect(ManifestRecordResource::getUrl('editor', ['record' => $manifest]));
            })];
        $actions[] = Action::make('edit_prelapide')->label('Modifica prelapide live')->icon('heroicon-o-paint-brush')->visible(fn (FuneralCase $record) => Gate::allows('update', $record) && ! $record->trashed())->url(fn (FuneralCase $record) => FuneralCaseResource::getUrl('prelapide-editor', ['record' => $record]));
        foreach (['mandate' => 'Delega (su richiesta)', 'document' => 'Documento', 'manifest' => 'Manifesto', 'prelapide' => 'Prelapide'] as $kind => $label) {
            $actions[] = Action::make('preview_'.$kind)->label('Anteprima '.$label)->icon('heroicon-o-eye')
                ->url(fn (FuneralCase $record) => $kind === 'manifest' && $record->manifest_record_id ? route('private.manifests.preview', $record->manifest_record_id) : route('private.preview', [$record, $kind]))->openUrlInNewTab();
            $actions[] = Action::make('generate_'.$kind)->label('Genera '.$label)->icon('heroicon-o-document-arrow-down')
                ->visible(fn (FuneralCase $record) => Gate::allows('update', $record) && ! $record->trashed())
                ->unsavedChangesAlert(false)->requiresConfirmation()->modalDescription('Vengono utilizzati i dati salvati. La nuova versione PDF resta nello storico e non sovrascrive le precedenti.')
                ->action(function (FuneralCase $record, $livewire) use ($kind) {
                    self::ensureSaved($livewire);
                    try {
                        if ($kind === 'manifest') {
                            $manifest = app(ManifestRecords::class)->forCase($record);
                            $export = app(ManifestRecordController::class)->generate($manifest);

                            return redirect(route('private.manifests.export', $export));
                        }
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
