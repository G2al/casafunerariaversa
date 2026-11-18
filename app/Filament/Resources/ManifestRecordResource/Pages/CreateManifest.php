<?php

namespace App\Filament\Resources\ManifestRecordResource\Pages;

use App\Filament\Resources\ManifestRecordResource;
use App\Models\ManifestRecord;
use App\Services\ManifestRecords;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Gate;

class CreateManifest extends Page
{
    protected static string $resource = ManifestRecordResource::class;

    protected string $view = 'filament.pages.create-graphic-manifest';

    public function mount(): void
    {
        Gate::authorize('create', ManifestRecord::class);
    }

    public function begin(): void
    {
        $record = app(ManifestRecords::class)->createDraft();
        $this->redirect(ManifestRecordResource::getUrl('editor', ['record' => $record]));
    }
}
