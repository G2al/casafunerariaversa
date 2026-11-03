<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OptimizedUpload
{
    public function store(UploadedFile $file, string $directory): string
    {
        $bytes = file_get_contents($file->getRealPath());
        app(PrivateIdentityDocument::class)->validateBytes($bytes, $file->getClientOriginalExtension());
        if ($file->getMimeType() === 'application/pdf') {
            $extension = 'pdf';
        } else {
            $image = imagecreatefromstring($bytes);
            if (function_exists('exif_read_data') && $file->getMimeType() === 'image/jpeg') {
                $orientation = (@exif_read_data($file->getRealPath()) ?: [])['Orientation'] ?? 1;
                if (in_array($orientation, [2, 4, 5, 7], true)) {
                    imageflip($image, in_array($orientation, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
                }
                $angle = match ($orientation) {
                    3 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
                };
                if ($angle) {
                    $rotated = imagerotate($image, $angle, 0);
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
            $limit = match ($directory) {
                'photos' => 2000, 'company', 'logos' => 1600, default => 3508
            };
            $scale = min(1, $limit / max(imagesx($image), imagesy($image)));
            $target = imagecreatetruecolor(max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), imagesx($image), imagesy($image));
            ob_start();
            imagewebp($target, null, 88);
            $bytes = ob_get_clean();
            imagedestroy($target);
            imagedestroy($image);
            $extension = 'webp';
        }
        $path = $directory.'/'.Str::uuid().'.'.$extension;
        if (! $bytes || ! Storage::disk('local')->put($path, $bytes)) {
            throw ValidationException::withMessages(['upload' => 'Salvataggio del file non riuscito.']);
        }

        return $path;
    }
}
