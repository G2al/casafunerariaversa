<?php

namespace App\Filament\Support;

use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\ManifestTemplate;
use App\Models\Symbol;
use App\Services\OptimizedUpload;
use App\Services\SafeSvg;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PrivateUpload
{
    public static function make(string $name, string $label, string $directory): FileUpload
    {
        return FileUpload::make($name)->label($label)->disk('local')->directory($directory)->visibility('private')
            ->saveUploadedFileUsing(fn (UploadedFile $file) => app(OptimizedUpload::class)->store($file, $directory))
            ->image()->acceptedFileTypes($directory === 'symbols' ? ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'] : ['image/jpeg', 'image/png', 'image/webp'])->maxSize(10240)->preventFilePathTampering()
            ->rules([fn () => function (string $attribute, mixed $value, \Closure $fail) use ($directory) {
                if (! $value instanceof UploadedFile) {
                    return;
                }
                if ($directory === 'symbols' && strtolower($value->getClientOriginalExtension()) === 'svg') {
                    try {
                        app(SafeSvg::class)->sanitize(file_get_contents($value->getRealPath()));
                    } catch (ValidationException $error) {
                        $fail($error->getMessage());
                    }

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
                    $record instanceof Symbol => route('private.symbol', $record),
                    $record instanceof ManifestTemplate => route('private.template', $record),
                    $record instanceof CompanySettings => route('private.logo'),
                    default => null,
                };

                return ['name' => basename($file), 'size' => Storage::disk('local')->size($file), 'type' => Storage::disk('local')->mimeType($file), 'url' => $url];
            });
    }
}
