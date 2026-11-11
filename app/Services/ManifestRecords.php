<?php

namespace App\Services;

use App\Models\FuneralCase;
use App\Models\ManifestRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManifestRecords
{
    public const FIELDS = ['type', 'first_name', 'last_name', 'sex', 'birth_date', 'death_date', 'spouse_name', 'marital_status', 'photo_path', 'manifest_template_id', 'manifest_options', 'family_members', 'viewing_venue', 'viewing_address', 'viewing_starts_at', 'viewing_ends_at', 'church', 'ceremony_city', 'ceremony_at', 'transfer_place', 'transfer_at'];

    public function forCase(FuneralCase $case): ManifestRecord
    {
        Gate::authorize('update', $case);

        return DB::transaction(function () use ($case) {
            $case = FuneralCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $case);
            $record = $case->manifest_record_id ? ManifestRecord::findOrFail($case->manifest_record_id) : new ManifestRecord;
            if ($record->exists) {
                Gate::authorize('update', $record);
            }
            $data = Arr::only($case->getAttributes(), self::FIELDS);
            foreach (['manifest_options', 'family_members'] as $field) {
                $data[$field] = $case->$field;
            }
            // I campi vuoti della pratica non cancellano foto e contenuti già preparati autonomamente.
            $record->data = array_replace($record->data ?? [], array_filter($data, fn ($v) => $v !== null && $v !== ''));
            $record->source_case_id = $case->id;
            $record->operator_id = $case->operator_id;
            if (! $record->exists) {
                $record->manifest_layout = $case->manifest_layout;
            }
            $record->save();
            $case->manifest_record_id = $record->id;
            $case->save();

            return $record;
        });
    }

    public function duplicate(ManifestRecord $source): ManifestRecord
    {
        Gate::authorize('view', $source);
        Gate::authorize('create', ManifestRecord::class);

        return DB::transaction(function () use ($source) {
            $copy = new ManifestRecord(['operator_id' => auth()->id(), 'data' => $source->data]);
            $layout = $source->manifest_layout;
            if ($layout) {
                $layout['revision'] = (string) Str::uuid();
            }
            $copy->manifest_layout = $layout;
            $copy->save();
            // Gli ID upload referenziati nella scena devono essere rimappati sul nuovo manifesto.
            $mapping = [];
            foreach (DB::table('editor_attachments')->where('manifest_record_id', $source->id)->get() as $a) {
                $id = DB::table('editor_attachments')->insertGetId(['manifest_record_id' => $copy->id, 'image_path' => $a->image_path, 'created_at' => now(), 'updated_at' => now()]);
                $mapping['upload_'.$a->id] = 'upload_'.$id;
            }
            if ($layout) {
                foreach ($layout['scene']['elements'] as &$element) {
                    if (isset($mapping[$element['asset'] ?? ''])) {
                        $element['asset'] = $mapping[$element['asset']];
                    }
                }
                unset($element);
                foreach ($mapping as $old => $new) {
                    if (isset($layout['assets'][$old])) {
                        $layout['assets'][$new] = $layout['assets'][$old];
                        unset($layout['assets'][$old]);
                    }
                }
                try {
                    $editor = app(ManifestEditor::class);
                    if (hash_equals($layout['source_stamp'] ?? '', $editor->stamp(app(DocumentData::class)->assemble($source, 'manifest')))) {
                        $layout['source_stamp'] = $editor->stamp(app(DocumentData::class)->assemble($copy, 'manifest'));
                        $layout['source']['code'] = $copy->code;
                    }
                } catch (ValidationException) {
                    // Una copia conserva anche l'avviso di dati incompleti o non aggiornati dell'originale.
                }
                $copy->manifest_layout = $layout;
                $copy->save();
            }

            return $copy;
        });
    }
}
