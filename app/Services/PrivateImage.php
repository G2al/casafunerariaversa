<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PrivateImage
{
    public function dataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_contains($path, '..') || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['image' => 'Immagine mancante o percorso non valido.']);
        }
        $bytes = Storage::disk('local')->get($path);
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
