<?php

namespace App\Models;

use App\Services\PrivateImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Symbol extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(SymbolFolder::class, 'symbol_folder_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $symbol) {
            $symbol->symbol_folder_id ??= SymbolFolder::firstOrCreate(['name' => 'Generali'])->id;
            if ($symbol->isDirty('image_path')) {
                app(PrivateImage::class)->dataUri($symbol->image_path);
            }
        });
    }
}
