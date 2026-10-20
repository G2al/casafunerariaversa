<?php

namespace App\Filament\Support;

use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\ManifestTemplate;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PrivateUpload
{
    public static function make(string $name, string $label, string $directory): FileUpload
    {
        return FileUpload::make($name)->label($label)->disk('local')->directory($directory)->visibility('private')
            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(10240)->preventFilePathTampering()
            ->rules([fn () => function (string $attribute, mixed $value, \Closure $fail) {
                if (! $value instanceof UploadedFile) {
                    return;
                }
                $info = @getimagesize($value->getRealPath());
                if (! $info || $info[0] * $info[1] > 25000000 || ! in_array(strtolower($value->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $fail('Immagine non valida: JPEG, PNG o WebP, massimo 25 megapixel.');
                }
            }])->getUploadedFileUsing(function (string $file, ?Model $record) {
                if (! $record || ! Storage::disk('local')->exists($file)) {
                    return null;
                }
                $url = match (true) {
                    $record instanceof FuneralCase => route('private.photo', $record),
                    $record instanceof ManifestTemplate => route('private.template', $record),
                    $record instanceof CompanySettings => route('private.logo'),
                    default => null,
                };

                return ['name' => basename($file), 'size' => Storage::disk('local')->size($file), 'type' => Storage::disk('local')->mimeType($file), 'url' => $url];
            });
    }
}
