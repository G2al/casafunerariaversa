<?php

namespace App\Services;

use App\Models\ManifestRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ManifestEditorFields
{
    public const TEXTS = ['intro', 'family', 'viewing', 'transfer', 'ceremony', 'closing', 'date', 'spouse', 'age'];

    public function values(ManifestRecord $record): array
    {
        $data = $record->data ?? [];
        $data['transport_at'] ??= $data['transfer_at'] ?? null;
        foreach (['birth_date', 'death_date'] as $key) {
            if (! empty($data[$key])) {
                $data[$key] = Carbon::parse($data[$key])->format('Y-m-d');
            }
        }

        return Arr::only($data, ['first_name', 'last_name', 'sex', 'birth_date', 'death_date', 'spouse_name', 'marital_status', 'viewing_venue', 'viewing_address', 'viewing_starts_at', 'viewing_ends_at', 'church', 'ceremony_city', 'ceremony_at', 'transfer_place', 'transport_at']);
    }

    public function apply(ManifestRecord $record, array $fields, array $scene): void
    {
        $rules = [];
        foreach (['first_name', 'last_name', 'spouse_name', 'marital_status', 'viewing_venue', 'viewing_address', 'church', 'ceremony_city', 'transfer_place'] as $key) {
            $rules[$key] = 'nullable|string|max:255';
        }
        $rules['sex'] = 'nullable|in:male,female';
        $rules['birth_date'] = 'nullable|date_format:Y-m-d|before_or_equal:today';
        $rules['death_date'] = 'nullable|date_format:Y-m-d|before_or_equal:today';
        foreach (['viewing_starts_at', 'viewing_ends_at', 'ceremony_at', 'transport_at'] as $key) {
            $rules[$key] = 'nullable|date';
        }
        $fields = Validator::make($fields, $rules)->validate();
        $data = array_replace($record->data ?? [], $fields);
        $options = $data['manifest_options'] ?? [];
        foreach ($scene['elements'] as $element) {
            if ($element['type'] !== 'text') {
                continue;
            }
            $id = $element['id'];
            if (in_array($id, ['first_name', 'last_name'], true)) {
                Validator::make([$id => $element['text'] ?? ''], [$id => 'nullable|string|max:255'])->validate();
                $data[$id] = trim($element['text'] ?? '');
            }
            if (in_array($id, self::TEXTS, true)) {
                $options['editor_texts'][$id] = $element['text'] ?? '';
                if ($id === 'intro') {
                    $options['intro'] = $element['text'] ?? '';
                }
                if ($id === 'family') {
                    $options['family_text'] = $element['text'] ?? '';
                }
            }
        }
        foreach ($scene['elements'] as $element) {
            if ($element['id'] === 'photo' && preg_match('/^upload_([0-9]+)$/', $element['asset'] ?? '', $match)) {
                $path = DB::table('editor_attachments')->where('manifest_record_id', $record->id)->where('id', $match[1])->value('image_path');
                if ($path) {
                    $data['photo_path'] = $path;
                }
            }
        }
        if (isset($scene['backgroundAsset'])) {
            $data['manifest_template_id'] = $scene['backgroundAsset'] === 'none' ? null : (int) substr($scene['backgroundAsset'], 6);
        }
        $data['transfer_at'] = $data['transport_at'] ?? $data['transfer_at'] ?? null;
        $data['manifest_options'] = $options;
        $record->data = $data;
    }
}
