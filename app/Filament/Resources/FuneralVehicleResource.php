<?php

namespace App\Filament\Resources;

use App\Filament\Support\DeletionActions;
use App\Models\FuneralVehicle;
use App\Services\OptimizedUpload;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FuneralVehicleResource extends Resource
{
    protected static ?string $model = FuneralVehicle::class;

    protected static ?string $modelLabel = 'Mezzo';

    protected static ?string $pluralModelLabel = 'Mezzi';

    protected static string|\UnitEnum|null $navigationGroup = 'Risorse del servizio';

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Descrizione')->required()->maxLength(255),
            TextInput::make('plate')->label('Targa')->required()->maxLength(30),
            TextInput::make('credentials')->label('Estremi idoneità sanitaria')->maxLength(255),
            Toggle::make('is_active')->label('Attivo')->default(true)->helperText('Disattiva i mezzi già associati a pratiche per conservarne lo storico.'),
            FileUpload::make('attachments')->label('Foto e allegati del mezzo')->columnSpanFull()
                ->disk('local')->directory('vehicle-attachments')->visibility('private')
                ->multiple()->maxFiles(50)->maxSize(10240)->reorderable()->openable()->downloadable()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->helperText('Puoi caricare più foto e PDF insieme: massimo 50 file da 10 MB ciascuno. Le foto vengono ottimizzate. Rimuovi un file e salva per eliminarlo.')
                ->preventFilePathTampering()->storeFileNamesIn('attachment_names')
                ->saveUploadedFileUsing(fn (UploadedFile $file) => app(OptimizedUpload::class)->store($file, 'vehicle-attachments'))
                ->getUploadedFileUsing(function (string $file, ?FuneralVehicle $record) {
                    if (! $record || ! in_array($file, $record->attachments ?? [], true) || ! Storage::disk('local')->exists($file)) {
                        return null;
                    }

                    return ['name' => $record->attachment_names[$file] ?? basename($file), 'size' => Storage::disk('local')->size($file), 'type' => Storage::disk('local')->mimeType($file), 'url' => route('private.vehicle-attachment', [$record, array_search($file, $record->attachments, true)])];
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withExists(['funeralCases', 'priorTransfers']))->columns([TextColumn::make('name')->label('Descrizione')->searchable(), TextColumn::make('plate')->label('Targa')->searchable(), IconColumn::make('is_active')->label('Attivo')->boolean()])->recordActions([
            EditAction::make(),
            DeleteAction::make()->disabled(fn (FuneralVehicle $record) => $record->isUsed())
                ->tooltip(fn (FuneralVehicle $record) => $record->isUsed() ? 'Già associato a una pratica: disattivalo da Modifica.' : 'Elimina definitivamente questo mezzo.'),
        ])->toolbarActions([BulkActionGroup::make([DeletionActions::bulk()])->label('Azioni sui selezionati')]);
    }

    public static function getPages(): array
    {
        return ['index' => FuneralVehicleResource\Pages\ManageVehicles::route('/')];
    }
}
