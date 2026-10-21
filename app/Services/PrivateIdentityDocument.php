<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PrivateIdentityDocument
{
    public function validate(string $path): void
    {
        if (! str_starts_with($path, 'identity-documents/') || str_contains($path, '..') || str_contains($path, '\\') || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['identity_document' => 'Documento mancante o percorso non valido.']);
        }
        $this->validateBytes(Storage::disk('local')->get($path), pathinfo($path, PATHINFO_EXTENSION));
    }

    public function validateBytes(string $bytes, string $extension): void
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        if (strlen($bytes) > 10 * 1024 * 1024 || ($allowed[strtolower($extension)] ?? null) !== $mime) {
            throw ValidationException::withMessages(['identity_document' => 'Usa JPEG, PNG, WebP o PDF validi, massimo 10 MB.']);
        }
        if ($mime === 'application/pdf') {
            if (! str_starts_with($bytes, '%PDF-')) {
                throw ValidationException::withMessages(['identity_document' => 'Il file PDF non è valido.']);
            }

            return;
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || $info[0] * $info[1] > 25000000 || ! ($image = @imagecreatefromstring($bytes))) {
            throw ValidationException::withMessages(['identity_document' => 'Immagine non valida: massimo 25 megapixel.']);
        }
        imagedestroy($image);
    }
}
