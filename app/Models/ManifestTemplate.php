<?php

namespace App\Models;

use App\Services\PrivateImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ManifestTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cases()
    {
        return $this->hasMany(FuneralCase::class)->withTrashed();
    }

    public function documents()
    {
        return $this->hasMany(FuneralDocument::class);
    }

    public function isUsed(): bool
    {
        if (array_key_exists('cases_exists', $this->getAttributes())) {
            return $this->cases_exists || $this->documents_exists;
        }

        return $this->cases()->exists() || $this->documents()->exists();
    }

    protected static function booted(): void
    {
        static::saving(function (self $template) {
            if ($template->isDirty('image_path')) {
                app(PrivateImage::class)->dataUri($template->image_path);
            }
        });
        static::deleting(function (self $template) {
            if ($template->isUsed()) {
                throw ValidationException::withMessages(['title' => 'Cornice già utilizzata: disattivala invece di eliminarla.']);
            }
        });
    }
}
