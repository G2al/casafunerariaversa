<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Enums\IdentityDocumentType;
use App\Services\CaseNumber;
use App\Services\DeleteFuneralCase;
use App\Services\PrivateIdentityDocument;
use App\Services\PrivateImage;
use App\Services\ReservationConflicts;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class FuneralCase extends Model
{
    use SoftDeletes;

    public bool $allowRoomOverlap = false;

    protected $attributes = ['transport_enabled' => true, 'prior_transfer_enabled' => false, 'transport_distance_km' => 100];

    protected $guarded = ['id', 'code', 'created_by'];

    // Le composizioni includono immagini base64: restano sul server e vengono
    // caricate soltanto dagli endpoint dedicati agli editor/PDF.
    protected $hidden = ['manifest_layout', 'prelapide_layout'];

    protected function casts(): array
    {
        $casts = ['type' => CaseType::class, 'status' => CaseStatus::class, 'family_members' => 'array', 'manifest_options' => 'array', 'manifest_layout' => 'array', 'prelapide_layout' => 'array', 'registered_at' => 'datetime', 'transport_ends_at' => 'datetime', 'death_cause' => 'encrypted'];
        $casts += ['transport_enabled' => 'boolean', 'prior_transfer_enabled' => 'boolean', 'prior_transfer_date' => 'date', 'transport_distance_km' => 'decimal:2', 'cremation_details' => 'array'];
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
            if ($case->isDirty('manifest_record_id') && $case->manifest_record_id) {
                $manifest = ManifestRecord::whereKey($case->manifest_record_id)->lockForUpdate()->firstOrFail();
                Gate::authorize('update', $manifest);
                if ($manifest->source_case_id && $manifest->source_case_id !== $case->id) {
                    throw ValidationException::withMessages(['manifest_record_id' => 'Il manifesto è già collegato a un’altra pratica. Duplicalo prima di collegarlo.']);
                }
            }
            $details = $case->cremation_details ?? [];
            $purpose = ['entombment' => 'Tumulato', 'burial' => 'Inumato'][$case->burial_type] ?? null;
            if ($purpose) {
                $details['transport_purpose'] = $purpose;
            }
            foreach (['transport_authorization_issuer', 'report_place', 'declaration_place'] as $field) {
                $details[$field] = $case->competent_city;
            }
            $details['declaration_date'] = $details['transport_authorization_date'] ?? null;
            if (! empty($details['identity_document_unlimited'])) {
                $details['identity_document_expires_at'] = null;
            }
            $case->cremation_details = $details;
            if ($case->transport_ends_at && (! $case->transport_at || $case->transport_ends_at->lte($case->transport_at))) {
                throw ValidationException::withMessages(['transport_ends_at' => 'La fine impegno deve essere successiva alla data e ora trasporto.']);
            }
            if ($case->isDirty(['room_id', 'viewing_starts_at', 'viewing_ends_at'])) {
                app(ReservationConflicts::class)->guardRoom($case);
            }
            $case->transport_distance_km = blank($case->transport_distance_km) ? 100 : $case->transport_distance_km;
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
            if ($case->isDirty('identity_document_type')) {
                Validator::make(['identity_document_type' => $case->identity_document_type], ['identity_document_type' => ['nullable', new Enum(IdentityDocumentType::class)]])->validate();
            }
            foreach (['identity_front_path', 'identity_back_path', 'death_certificate_path', 'istat_path', 'delegate_identity_front_path', 'delegate_identity_back_path'] as $field) {
                if ($case->isDirty($field) && $case->$field) {
                    app(PrivateIdentityDocument::class)->validate($case->$field, str_starts_with($field, 'delegate_identity_') ? 'delegate-documents' : (in_array($field, ['death_certificate_path', 'istat_path'], true) ? 'case-documents' : 'identity-documents'));
                }
            }
        });
        static::saved(function (self $case) {
            if ($case->wasChanged('manifest_record_id') && $case->getOriginal('manifest_record_id')) {
                ManifestRecord::whereKey($case->getOriginal('manifest_record_id'))->where('source_case_id', $case->id)->update(['source_case_id' => null]);
            }
            if ($case->manifest_record_id) {
                ManifestRecord::whereKey($case->manifest_record_id)->update(['source_case_id' => $case->id, 'operator_id' => $case->operator_id]);
            }
            if ($case->allowRoomOverlap && $case->room_id && ($case->wasRecentlyCreated || $case->wasChanged(['room_id', 'viewing_starts_at', 'viewing_ends_at']))) {
                AuditEvent::create(['user_id' => auth()->id(), 'funeral_case_id' => $case->id, 'event' => 'room_overlap_forced', 'changed_fields' => ['room_id' => $case->room_id]]);
            }
        });
        static::deleting(function (self $case) {
            if (auth()->check()) {
                Gate::authorize('delete', $case);
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

    public function save(array $options = [])
    {
        return DB::transaction(fn () => parent::save($options), 3);
    }

    public function forceDelete(): ?bool
    {
        return app(DeleteFuneralCase::class)->delete($this);
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function manifestRecord(): BelongsTo
    {
        return $this->belongsTo(ManifestRecord::class);
    }

    public function vehicleCommitment()
    {
        return $this->hasOne(VehicleCommitment::class);
    }

    public function technicalDirector(): BelongsTo
    {
        return $this->belongsTo(FuneralStaffMember::class, 'technical_director_id');
    }

    public function transportStaff(): BelongsToMany
    {
        return $this->belongsToMany(FuneralStaffMember::class, 'funeral_case_staff')->using(FuneralCaseStaff::class)->withTimestamps()->orderBy('funeral_staff_members.name');
    }

    public function priorTransferVehicle(): BelongsTo
    {
        return $this->belongsTo(FuneralVehicle::class, 'prior_transfer_vehicle_id');
    }
}
