<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PrivateImage
{
    public function dimensions(string $uri): array
    {
        $bytes = base64_decode(explode(',', $uri, 2)[1] ?? '', true);
        if (str_starts_with($uri, 'data:image/svg+xml;base64,')) {
            $svg = app(SafeSvg::class)->sanitize($bytes ?: '');

            return [$svg['width'], $svg['height']];
        }
        $size = $bytes ? @getimagesizefromstring($bytes) : false;
        if (! $size) {
            throw ValidationException::withMessages(['image' => 'Immagine non valida.']);
        }

        return [$size[0], $size[1]];
    }

    public function dataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_contains($path, '..') || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['image' => 'Immagine mancante o percorso non valido.']);
        }
        $bytes = Storage::disk('local')->get($path);
        if (str_starts_with($path, 'symbols/') && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
            return 'data:image/svg+xml;base64,'.base64_encode(app(SafeSvg::class)->sanitize($bytes)['bytes']);
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || strlen($bytes) > 10 * 1024 * 1024 || $info[0] * $info[1] > 25000000) {
            throw ValidationException::withMessages(['image' => 'Usa immagini JPEG, PNG o WebP valide, massimo 10 MB e 25 megapixel.']);
        }
        // Normalizza anche WebP per garantire il supporto del motore PDF.
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw ValidationException::withMessages(['image' => 'Immagine non decodificabile.']);
        }
        ob_start();
        imagepng($image);
        $normalized = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($normalized);
    }
}
