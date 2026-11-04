<?php

namespace App\Models;

use App\Services\PrivateImage;
use Illuminate\Database\Eloquent\Model;

class Symbol extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $symbol) {
            if ($symbol->isDirty('image_path')) {
                app(PrivateImage::class)->dataUri($symbol->image_path);
            }
        });
    }
}
