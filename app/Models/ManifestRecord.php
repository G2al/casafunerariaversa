<?php

namespace App\Models;

use App\Enums\CaseType;
use App\Services\PrivateImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManifestRecord extends Model
{
    protected $guarded = ['id', 'created_by'];

    protected $hidden = ['manifest_layout'];

    protected function casts(): array
    {
        return ['data' => 'array', 'manifest_layout' => 'array'];
    }

    public function scopeVisibleTo($query, $user)
    {
        return ! $user?->is_active || ! $user?->role ? $query->whereRaw('1=0') : ($user->isAdmin() ? $query : $query->where('operator_id', $user->id));
    }

    public function sourceCase()
    {
        return $this->belongsTo(FuneralCase::class, 'source_case_id')->withTrashed();
    }

    public function exports()
    {
        return $this->hasMany(ManifestExport::class);
    }

    public function getCodeAttribute(): string
    {
        return 'MAN-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->data['first_name'] ?? '').' '.($this->data['last_name'] ?? ''));
    }

    public function getPhotoPathAttribute(): ?string
    {
        return $this->data['photo_path'] ?? null;
    }

    public function trashed(): bool
    {
        return false;
    }

    public function asCase(): FuneralCase
    {
        $case = new FuneralCase(array_merge(['type' => CaseType::StandardTransport, 'status' => 'draft'], $this->data ?? []));
        $case->code = $this->source_case_id ? ($this->sourceCase?->code ?? $this->code) : $this->code;

        return $case;
    }

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            $record->created_by = auth()->id();
            if (! auth()->user()?->isAdmin() || ! $record->operator_id) {
                $record->operator_id = auth()->id();
            }
        });
        static::saving(function (self $record) {
            $dataCase = $record->asCase();
            if ($dataCase->birth_date && $dataCase->death_date && $dataCase->birth_date->gt($dataCase->death_date)) {
                throw ValidationException::withMessages(['data.birth_date' => 'La nascita non può essere successiva al decesso.']);
            }
            if ($record->exists && auth()->check()) {
                Gate::authorize('update', $record);
            }
            if ($record->exists && auth()->check() && ! auth()->user()->isAdmin()) {
                $record->operator_id = $record->getRawOriginal('operator_id');
            }
            if ($record->isDirty('data') && filled($record->photo_path)) {
                app(PrivateImage::class)->dataUri($record->photo_path);
            }
        });
    }
}
