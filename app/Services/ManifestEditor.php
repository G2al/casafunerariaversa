<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\FuneralCase;
use App\Models\ManifestTemplate;
use App\Models\Symbol;
use App\Models\SymbolFolder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManifestEditor
{
    public const WIDTH = 841.89;

    public const HEIGHT = 595.28;

    public string $kind = 'manifest';

    public function field(): string
    {
        return $this->kind === 'prelapide' ? 'prelapide_layout' : 'manifest_layout';
    }

    public function bootstrap(FuneralCase $case): array
    {
        $this->authorize($case);
        $data = app(DocumentData::class)->assemble($case, $this->kind);
        $saved = $case->{$this->field()};
        try {
            $automatic = $this->automatic($data);
            $automaticError = null;
        } catch (ValidationException $error) {
            $automaticError = $error->getMessage();
            // Una composizione già salvata resta modificabile anche con testi nuovi troppo lunghi.
            if (! $saved) {
                throw $error;
            }
            $automatic = null;
        }

        return [
            'scene' => $saved['scene'] ?? $automatic,
            'symbolFolders' => SymbolFolder::orderBy('name')->get(['id', 'name'])->toArray(),
            'catalog' => $this->catalog($case), 'uploadUrl' => route('private.manifest-editor.upload', $case), 'kind' => $this->kind,
            'automatic' => $automatic, 'automaticError' => $automaticError,
            'assets' => $saved['assets'] ?? $this->assets($case, $data),
            'currentAssets' => $this->assets($case, $data),
            'source' => $saved['source_stamp'] ?? $this->stamp($data), 'currentSource' => $this->stamp($data), 'revision' => $saved['revision'] ?? null,
            'stale' => $saved && ($saved['source_stamp'] ?? null) !== $this->stamp($data),
            'saveUrl' => route('private.manifest-editor.save', [$case, 'kind' => $this->kind]),
            'resetUrl' => route('private.manifest-editor.reset', [$case, 'kind' => $this->kind]),
            'previewUrl' => route('private.preview', [$case, $this->kind]),
        ];
    }

    public function save(FuneralCase $case, array $input): string
    {
        $this->authorize($case);
        $scene = $this->validateScene($input['scene'] ?? []);
        $bytes = $this->imageBytes($input['image'] ?? '');
        $path = 'manifest-editor/'.Str::uuid().'.jpg';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw ValidationException::withMessages(['image' => 'Salvataggio della composizione non riuscito.']);
        }
        try {
            return DB::transaction(function () use ($case, $input, $scene, $path) {
                $locked = FuneralCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
                $this->authorize($locked);
                $this->checkRevision($locked, $input['revision'] ?? null);
                $data = app(DocumentData::class)->assemble($locked, $this->kind);
                abort_unless(hash_equals($this->stamp($data), (string) ($input['source'] ?? '')), 409, 'I dati della pratica sono cambiati. Ricarica i dati prima di salvare.');
                $assets = $this->assets($locked, $data);
                $frozen = $locked->{$this->field()}['assets'] ?? [];
                foreach (array_unique(array_filter(array_merge(array_column($scene['elements'], 'asset'), [$scene['backgroundAsset'] ?? null]))) as $key) {
                    if (isset($frozen[$key]) && (! in_array($key, $input['freshAssets'] ?? [], true) || empty($assets[$key]))) {
                        $assets[$key] = $frozen[$key];
                    }
                    if (empty($assets[$key])) {
                        throw ValidationException::withMessages(['scene' => 'Immagine non disponibile: aggiorna o rimuovi il riferimento.']);
                    }
                }
                $keys = array_merge(['background', 'photo', 'logo', 'symbol'], array_column($scene['elements'], 'asset'), [$scene['backgroundAsset'] ?? null]);
                $assets = Arr::only($assets, array_filter($keys));
                $revision = (string) Str::uuid();
                $locked->{$this->field()} = [
                    'revision' => $revision, 'scene' => $scene, 'image_path' => $path,
                    'assets' => $this->freezeAssets($locked, $assets),
                    'source_stamp' => $this->stamp($data),
                    'source' => Arr::except($data, ['photo', 'logo', 'background', 'generated_at']),
                ];
                $locked->save();
                // Conserva le immagini delle revisioni con il fascicolo, per poi rimuoverle nel purge.
                AuditEvent::create(['user_id' => auth()->id(), 'funeral_case_id' => $locked->id, 'event' => $this->field().'_saved', 'changed_fields' => ['image_path' => $path, 'revision' => $revision]]);

                return $revision;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function reset(FuneralCase $case, ?string $revision): void
    {
        $this->authorize($case);
        DB::transaction(function () use ($case, $revision) {
            $locked = FuneralCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $this->authorize($locked);
            $this->checkRevision($locked, $revision);
            $locked->{$this->field()} = null;
            $locked->save();
            AuditEvent::create(['user_id' => auth()->id(), 'funeral_case_id' => $locked->id, 'event' => $this->field().'_reset', 'changed_fields' => []]);
        });
    }

    public function pdfData(FuneralCase $case): array
    {
        $saved = $case->{$this->field()};
        $path = $saved['image_path'] ?? '';
        abort_unless(str_starts_with($path, 'manifest-editor/') && ! str_contains($path, '..') && ! str_contains($path, '\\') && Storage::disk('local')->exists($path), 404);

        return ($saved['source'] ?? []) + [
            'editor_scene' => $saved['scene'], 'editor_revision' => $saved['revision'],
            'editor_image' => 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($path)),
        ];
    }

    private function authorize(FuneralCase $case): void
    {
        Gate::authorize('update', $case);
        abort_if($case->trashed(), 403);
    }

    private function checkRevision(FuneralCase $case, ?string $revision): void
    {
        abort_unless(($case->{$this->field()}['revision'] ?? null) === $revision, 409, 'La composizione è stata modificata in un’altra finestra. Ricarica prima di salvare.');
    }

    private function stamp(array $data): string
    {
        return hash('sha256', json_encode(Arr::except($data, ['generated_at']), JSON_UNESCAPED_UNICODE));
    }

    private function imageBytes(string $uri): string
    {
        if (! str_starts_with($uri, 'data:image/jpeg;base64,') || strlen($uri) > 8 * 1024 * 1024) {
            throw ValidationException::withMessages(['image' => 'Esporta una composizione JPEG valida, massimo 6 MB.']);
        }
        $bytes = base64_decode(substr($uri, strlen('data:image/jpeg;base64,')), true);
        $size = $bytes ? @getimagesizefromstring($bytes) : false;
        if (! $size || $size['mime'] !== 'image/jpeg' || $size[0] !== 3508 || $size[1] !== 2480) {
            throw ValidationException::withMessages(['image' => 'La composizione deve essere un A4 orizzontale a 300 DPI (3508 × 2480).']);
        }

        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw ValidationException::withMessages(['image' => 'Immagine della composizione non decodificabile.']);
        }
        imagedestroy($image);

        return $bytes;
    }

    public function catalog(FuneralCase $case): array
    {
        return [
            'symbols' => Symbol::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->map(fn ($s) => ['folder' => $s->symbol_folder_id, 'key' => 'symbol_'.$s->id, 'title' => $s->title, 'url' => route('private.symbol', $s)])->all(),
            'frames' => ManifestTemplate::where('is_active', true)->orderBy('sort_order')->get()->map(fn ($s) => ['key' => 'frame_'.$s->id, 'title' => $s->title, 'url' => route('private.template', $s)])->all(),
            'uploads' => DB::table('editor_attachments')->where('funeral_case_id', $case->id)->get()->map(fn ($s) => ['key' => 'upload_'.$s->id, 'title' => 'Immagine '.$s->id, 'url' => route('private.manifest-editor.attachment', [$case, $s->id])])->all(),
        ];
    }

    private function assets(FuneralCase $case, array $data): array
    {
        $assets = Arr::only($data, ['background', 'photo', 'logo', 'symbol']);
        // Le immagini di catalogo vengono risolte solo al salvataggio o su richiesta del client.
        foreach ($this->catalog($case) as $group) {
            foreach ($group as $item) {
                $assets[$item['key']] = $item['url'];
            }
        }

        return $assets;
    }

    private function freezeAssets(FuneralCase $case, array $assets): array
    {
        $paths = [];
        foreach (Symbol::where('is_active', true)->get() as $s) {
            $paths['symbol_'.$s->id] = $s->image_path;
        }
        foreach (ManifestTemplate::where('is_active', true)->get() as $s) {
            $paths['frame_'.$s->id] = $s->image_path;
        }
        foreach (DB::table('editor_attachments')->where('funeral_case_id', $case->id)->get() as $s) {
            $paths['upload_'.$s->id] = $s->image_path;
        }
        foreach ($assets as $key => $value) {
            if ($value && ! str_starts_with($value, 'data:')) {
                if (! isset($paths[$key])) {
                    throw ValidationException::withMessages(['scene' => 'Riferimento immagine non valido.']);
                }
                $assets[$key] = app(PrivateImage::class)->dataUri($paths[$key]);
            }
        }

        return $assets;
    }

    private function validateScene(array $scene): array
    {
        $validated = Validator::make(['scene' => $scene], [
            'scene' => 'required|array:version,elements,backgroundAsset', 'scene.version' => 'required|integer|in:1',
            'scene.backgroundAsset' => 'nullable|string|regex:/^frame_[0-9]+$/',
            'scene.elements' => 'present|array|max:40',
            'scene.elements.*' => 'array:id,type,text,asset,x,y,width,height,fontSize,fontFamily,bold,italic,color,align,lineHeight,locked,fill,stroke,strokeWidth',
            'scene.elements.*.id' => 'required|string|regex:/^[a-zA-Z0-9_-]{1,50}$/|distinct',
            'scene.elements.*.type' => 'required|in:text,image,rect,ellipse,triangle',
            'scene.elements.*.x' => 'required|numeric|min:0|max:841.89',
            'scene.elements.*.y' => 'required|numeric|min:0|max:595.28',
            'scene.elements.*.width' => 'required|numeric|min:10|max:841.89',
            'scene.elements.*.height' => 'required|numeric|min:0.5|max:595.28',
            'scene.elements.*.fill' => ['nullable', 'regex:/^(#[a-fA-F0-9]{6}|transparent)$/'],
            'scene.elements.*.stroke' => 'nullable|regex:/^#[a-fA-F0-9]{6}$/',
            'scene.elements.*.strokeWidth' => 'nullable|numeric|min:0|max:40',
            'scene.elements.*.text' => 'nullable|string|max:4000',
            'scene.elements.*.asset' => ['nullable', 'string', 'regex:/^(photo|logo|symbol|symbol_[0-9]+|frame_[0-9]+|upload_[0-9]+)$/'],
            'scene.elements.*.fontSize' => 'required|numeric|min:6|max:100',
            'scene.elements.*.fontFamily' => 'required|in:DejaVu Serif,DejaVu Sans',
            'scene.elements.*.bold' => 'required|boolean', 'scene.elements.*.italic' => 'required|boolean',
            'scene.elements.*.locked' => 'required|boolean',
            'scene.elements.*.color' => 'required|regex:/^#[a-fA-F0-9]{6}$/',
            'scene.elements.*.align' => 'required|in:left,center,right',
            'scene.elements.*.lineHeight' => 'required|numeric|min:1|max:2',
        ])->validate()['scene'];
        foreach ($validated['elements'] as $element) {
            if (self::WIDTH + 0.1 < $element['x'] + $element['width'] || self::HEIGHT + 0.1 < $element['y'] + $element['height'] || ($element['type'] === 'image' && empty($element['asset']))) {
                throw ValidationException::withMessages(['scene' => 'Mantieni tutti gli elementi dentro il foglio A4.']);
            }
        }

        return $validated;
    }

    private function automatic(array $data): array
    {
        if ($this->kind === 'prelapide') {
            return app(PrelapideLayout::class)->scene($data);
        }
        $layout = app(ManifestLayout::class)->fit($data);
        $elements = [];
        foreach (collect($layout['lines'])->groupBy('key') as $key => $lines) {
            $first = $lines->first();
            $elements[] = array_replace($this->element($key, 'text', $first['x'], $first['y'], $first['width'], $lines->count() * $first['size'] * 1.5), [
                'fontFamily' => $first['fontFamily'] ?? 'DejaVu Serif', 'color' => $first['color'] ?? '#000000',
                'text' => $lines->pluck('text')->implode("\n"), 'fontSize' => $first['size'], 'bold' => $first['bold'],
            ]);
        }
        $separator = $layout['separator'];
        $elements[] = $this->element('separator', 'rect', $separator['x'], $separator['y'], $separator['width'], 1) + ['fill' => '#000000', 'stroke' => '#000000', 'strokeWidth' => 0, 'fontSize' => 12, 'bold' => false];
        foreach (['photo', 'logo'] as $asset) {
            if ($image = $layout[$asset]) {
                $elements[] = $this->element($asset, 'image', $image['x'], $image['y'], $image['width'], $image['height']) + ['asset' => $asset, 'fontSize' => 12, 'bold' => false];
            }
        }

        return ['version' => 1, 'elements' => $elements];
    }

    private function element(string $id, string $type, float $x, float $y, float $width, float $height): array
    {
        return compact('id', 'type', 'x', 'y', 'width', 'height') + ['fontFamily' => 'DejaVu Serif', 'italic' => false, 'color' => '#000000', 'align' => 'center', 'lineHeight' => 1.5, 'locked' => false];
    }
}
