<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Services\CaseNumber;
use App\Services\PrivateImage;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FuneralCase extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'code', 'created_by'];

    protected function casts(): array
    {
        $casts = ['type' => CaseType::class, 'status' => CaseStatus::class, 'family_members' => 'array', 'manifest_options' => 'array', 'registered_at' => 'datetime', 'death_cause' => 'encrypted'];
        foreach (config('funeral.sections') as $fields) {
            foreach (array_keys($fields) as $field) {
                if (str_ends_with($field, '_date') || str_ends_with($field, '_at')) {
                    $casts[$field] = str_ends_with($field, '_date') ? 'date' : 'datetime';
                }
            }
        }

        return $casts;
    }

    protected static function booted(): void
    {
        static::creating(function (self $case) {
            $user = auth()->user();
            if ($user) {
                abort_unless($user->canAccessPanel(Filament::getCurrentOrDefaultPanel()), 403);
                $case->created_by = $user->id;
                if (! $user->isAdmin() || ! $case->operator_id) {
                    $case->operator_id = $user->id;
                }
            }
            $case->code = app(CaseNumber::class)->next();
            $case->registered_at ??= now();
            $case->status ??= CaseStatus::Draft;
        });
        static::saving(function (self $case) {
            if ($case->exists && auth()->check()) {
                if (! auth()->user()->isAdmin()) {
                    $case->operator_id = $case->getRawOriginal('operator_id');
                }
                Gate::authorize('update', $case);
                $case->created_by = $case->getRawOriginal('created_by');
                $case->code = $case->getRawOriginal('code');
            }
            if ($case->birth_date && $case->death_date && $case->birth_date->gt($case->death_date)) {
                throw ValidationException::withMessages(['birth_date' => 'La nascita non può essere successiva al decesso.']);
            }
            foreach (['birth_date', 'death_date'] as $field) {
                if ($case->$field?->isFuture()) {
                    throw ValidationException::withMessages([$field => 'La data non può essere futura.']);
                }
            }
            if ($case->viewing_starts_at && $case->viewing_ends_at && $case->viewing_ends_at->lt($case->viewing_starts_at)) {
                throw ValidationException::withMessages(['viewing_ends_at' => 'La fine esposizione precede l’inizio.']);
            }
            if ($case->isDirty('photo_path') && $case->photo_path) {
                app(PrivateImage::class)->dataUri($case->photo_path);
            }
        });
        foreach (['created', 'updated', 'deleted', 'restored'] as $event) {
            static::$event(fn (self $case) => AuditEvent::create([
                'user_id' => auth()->id(), 'funeral_case_id' => $case->id,
                'event' => $event, 'changed_fields' => array_keys($case->getChanges()),
            ]));
        }
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user || ! $user->is_active || ! $user->role) {
            return $query->whereRaw('1 = 0');
        }

        return $user->isAdmin() ? $query : $query->where('operator_id', $user->id);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name) ?: 'Pratica senza nominativo';
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date && $this->death_date && $this->birth_date->lte($this->death_date)
            ? (int) $this->birth_date->diffInYears($this->death_date) : null;
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents()
    {
        return $this->hasMany(FuneralDocument::class)->orderByDesc('version');
    }

    public function manifestTemplate()
    {
        return $this->belongsTo(ManifestTemplate::class);
    }

    public function staffMember()
    {
        return $this->belongsTo(FuneralStaffMember::class, 'staff_member_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(FuneralVehicle::class, 'vehicle_id');
    }
}
