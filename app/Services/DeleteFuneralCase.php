<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\FuneralDocument;
use App\Models\ManifestTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteFuneralCase
{
    public function delete(FuneralCase $case): bool
    {
        Gate::authorize('forceDelete', $case);

        return DB::transaction(function () use ($case) {
            $locked = FuneralCase::withTrashed()->whereKey($case->id)->lockForUpdate()->first();
            if (! $locked) {
                return true;
            }
            Gate::authorize('forceDelete', $locked);
            $paths = collect([$locked->photo_path, $locked->identity_front_path, $locked->identity_back_path])
                ->merge($locked->documents()->pluck('file_path'))->filter()->unique()->values()->all();

            // Le versioni restano immutabili finché esiste la pratica; qui si elimina
            // esplicitamente l'intero fascicolo, dopo autorizzazione e lock del padre.
            DB::table('funeral_documents')->where('funeral_case_id', $locked->id)->delete();
            DB::table('funeral_case_staff')->where('funeral_case_id', $locked->id)->delete();
            AuditEvent::where('funeral_case_id', $locked->id)->delete();
            DB::table('funeral_cases')->where('id', $locked->id)->delete();
            $audit = AuditEvent::create([
                'user_id' => auth()->id(), 'funeral_case_id' => null, 'event' => 'permanently_deleted',
                'changed_fields' => ['code' => $locked->code, 'record_id' => $locked->id, 'pending_files' => $paths],
            ]);
            // I file si rimuovono solo dopo il commit. Se il disco non risponde,
            // l'elenco resta persistito per il successivo tentativo di pulizia.
            DB::afterCommit(function () use ($audit) {
                try {
                    $this->cleanup($audit->id);
                } catch (\Throwable $error) {
                    report($error);
                }
            });

            return true;
        }, 3);
    }

    public function cleanup(int $auditId): int
    {
        return DB::transaction(function () use ($auditId) {
            $audit = AuditEvent::where('event', 'permanently_deleted')->whereKey($auditId)->lockForUpdate()->first();
            if (! $audit) {
                return 0;
            }
            $remaining = [];
            foreach ($audit->changed_fields['pending_files'] ?? [] as $path) {
                if (! $this->ownedPath($path) || $this->stillUsed($path)) {
                    continue;
                }
                try {
                    if (Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) {
                        $remaining[] = $path;
                    }
                } catch (\Throwable $error) {
                    $remaining[] = $path;
                    report($error);
                }
            }
            $audit->update(['changed_fields' => array_replace($audit->changed_fields, ['pending_files' => $remaining])]);
            if ($remaining) {
                Log::warning('Pulizia file pratica da ripetere.', ['audit_id' => $audit->id, 'count' => count($remaining)]);
            }

            return count($remaining);
        });
    }

    private function ownedPath(mixed $path): bool
    {
        return is_string($path) && ! str_contains($path, '..') && ! str_contains($path, '\\')
            && (str_starts_with($path, 'photos/') || str_starts_with($path, 'identity-documents/') || str_starts_with($path, 'pdf/'));
    }

    private function stillUsed(string $path): bool
    {
        return FuneralCase::withTrashed()->where(fn ($query) => $query->where('photo_path', $path)->orWhere('identity_front_path', $path)->orWhere('identity_back_path', $path))->exists()
            || FuneralDocument::where('file_path', $path)->exists()
            || ManifestTemplate::where('image_path', $path)->exists()
            || (CompanySettings::current()['logo_path'] ?? null) === $path;
    }
}
