<?php

namespace App\Filament\Resources\ManifestRecordResource\Pages;

use App\Filament\Resources\ManifestRecordResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;

class EditGraphicManifest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ManifestRecordResource::class;

    protected string $view = 'filament.pages.manifest-editor';

    protected static ?string $title = 'Editor manifesto';

    public string $editorKind = 'manifest';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        Gate::authorize('update', $this->record);
    }
}
