<?php

namespace App\Filament\Resources\ManifestTemplateResource\Pages;

use App\Filament\Resources\ManifestTemplateResource;
use App\Models\ManifestTemplate;
use App\Services\PrivateImage;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ManageTemplates extends ManageRecords
{
    protected static string $resource = ManifestTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make(), Action::make('bulk_upload')->label('Carica più cornici')->schema([
            FileUpload::make('images')->label('Immagini')->disk('local')->directory('frames')->visibility('private')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(10240)->multiple()->maxFiles(100)->required()->preventFilePathTampering()->storeFileNamesIn('names'),
        ])->action(function (array $data) {
            Gate::authorize('create', ManifestTemplate::class);
            DB::transaction(function () use ($data) {
                $order = ManifestTemplate::max('sort_order') ?? 0;
                foreach ($data['images'] as $path) {
                    app(PrivateImage::class)->dataUri($path);
                    ManifestTemplate::create(['title' => mb_substr(pathinfo($data['names'][$path] ?? basename($path), PATHINFO_FILENAME), 0, 255), 'image_path' => $path, 'sort_order' => ++$order, 'is_active' => true]);
                }
            });
            Notification::make()->success()->title('Cornici caricate')->send();
        })];
    }
}
