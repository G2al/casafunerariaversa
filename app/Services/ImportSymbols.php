<?php

namespace App\Services;

use App\Models\Symbol;
use App\Models\SymbolFolder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ImportSymbols
{
    public function import(SymbolFolder $folder, array $paths, array $names = []): void
    {
        Gate::authorize('create', Symbol::class);
        if (count($paths) < 1 || count($paths) > 100) {
            throw ValidationException::withMessages(['images' => 'Seleziona da 1 a 100 immagini.']);
        }
        DB::transaction(function () use ($folder, $paths, $names) {
            SymbolFolder::whereKey($folder->id)->lockForUpdate()->firstOrFail();
            $order = Symbol::where('symbol_folder_id', $folder->id)->max('sort_order') ?? 0;
            foreach ($paths as $path) {
                if (! is_string($path) || ! str_starts_with($path, 'symbols/') || str_contains($path, '..') || str_contains($path, '\\')) {
                    throw ValidationException::withMessages(['images' => 'Percorso immagine non valido.']);
                }
                app(PrivateImage::class)->dataUri($path);
                Symbol::create(['symbol_folder_id' => $folder->id, 'title' => mb_substr(pathinfo($names[$path] ?? basename($path), PATHINFO_FILENAME), 0, 255), 'image_path' => $path, 'sort_order' => ++$order, 'is_active' => true]);
            }
        });
    }
}
