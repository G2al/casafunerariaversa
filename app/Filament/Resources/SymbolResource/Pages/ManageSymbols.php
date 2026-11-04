<?php

namespace App\Filament\Resources\SymbolResource\Pages;

use App\Filament\Resources\SymbolFolderResource;
use App\Filament\Resources\SymbolResource;
use App\Models\SymbolFolder;
use App\Services\ImportSymbols;
use App\Services\OptimizedUpload;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Locked;

class ManageSymbols extends ManageRecords
{
    protected static string $resource = SymbolResource::class;

    #[Locked]
    public int $folderId = 0;

    public function mount(int|string|null $folder = null): void
    {
        parent::mount();
        if ($folder === null) {
            $this->redirect(SymbolFolderResource::getUrl());

            return;
        }
        $this->folderId = SymbolFolder::findOrFail($folder)->id;
    }

    public function getTitle(): string
    {
        return $this->folderId ? 'Simboli · '.SymbolFolder::findOrFail($this->folderId)->name : 'Simboli';
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('folders')->label('Tutte le cartelle')->icon('heroicon-o-folder')->url(SymbolFolderResource::getUrl()),
            CreateAction::make()->label('Nuovo simbolo')->mutateDataUsing(fn (array $data) => array_replace($data, ['symbol_folder_id' => $this->folderId])),
            Action::make('bulk_upload')->label('Carica più immagini')->icon('heroicon-o-arrow-up-tray')->schema([
                FileUpload::make('images')->label('Immagini')->disk('local')->directory('symbols')->visibility('private')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(10240)->multiple()->maxFiles(100)->required()->preventFilePathTampering()->storeFileNamesIn('names')->saveUploadedFileUsing(fn (UploadedFile $file) => app(OptimizedUpload::class)->store($file, 'symbols')),
            ])->action(function (array $data) {
                app(ImportSymbols::class)->import(SymbolFolder::findOrFail($this->folderId), $data['images'], $data['names'] ?? []);
                Notification::make()->success()->title('Immagini caricate nella cartella')->send();
            }),
        ];
    }
}
