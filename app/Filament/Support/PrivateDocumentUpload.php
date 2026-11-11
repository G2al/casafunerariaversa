<?php

namespace App\Filament\Support;

use App\Models\FuneralCase;
use App\Services\OptimizedUpload;
use App\Services\PrivateIdentityDocument;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PrivateDocumentUpload
{
    public static function make(string $field, string $label, string $directory, string $route, string $parameter): FileUpload
    {
        return FileUpload::make($field)->label($label)->disk('local')->directory($directory)->visibility('private')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->maxSize(10240)
            ->preventFilePathTampering()->openable()->downloadable()->hintActions(FilePresentationActions::make($field, match ($route) {
                'private.identity' => 'identity-'.$parameter,'private.delegate-identity' => 'delegate-'.$parameter,default => $parameter
            }))
            ->saveUploadedFileUsing(fn (UploadedFile $file) => app(OptimizedUpload::class)->store($file, $directory))
            ->rules([fn () => function (string $attribute, mixed $value, \Closure $fail) {
                if (! $value instanceof UploadedFile) {
                    return;
                }
                try {
                    app(PrivateIdentityDocument::class)->validateBytes(file_get_contents($value->getRealPath()), $value->getClientOriginalExtension());
                } catch (ValidationException $error) {
                    $fail($error->getMessage());
                }
            }])->getUploadedFileUsing(function (string $file, ?FuneralCase $record) use ($field, $label, $route, $parameter) {
                if (! $record || $file !== $record->$field || ! Storage::disk('local')->exists($file)) {
                    return null;
                }

                return ['name' => $label.'.'.pathinfo($file, PATHINFO_EXTENSION), 'size' => Storage::disk('local')->size($file), 'type' => Storage::disk('local')->mimeType($file), 'url' => route($route, [$record, $parameter])];
            });
    }
}
