<?php

namespace App\Filament\Resources\FuneralCaseResource\Pages;

use App\Filament\Resources\FuneralCaseResource;
use App\Filament\Resources\ManifestRecordResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;

class EditManifest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FuneralCaseResource::class;

    protected string $view = 'filament.pages.manifest-editor';

    protected static ?string $title = 'Editor manifesto';

    public string $editorKind = 'manifest';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        Gate::authorize('update', $this->record);
        abort_if($this->record->trashed(), 403);
        if ($this->editorKind === 'manifest' && $this->record->manifest_record_id) {
            $this->redirect(ManifestRecordResource::getUrl('editor', ['record' => $this->record->manifest_record_id]));
        }
    }
}
