<?php

namespace App\Http\Controllers;

use App\Models\FuneralCase;
use App\Models\Symbol;
use App\Services\ManifestEditor;
use App\Services\OptimizedUpload;
use App\Services\PdfGenerator;
use App\Services\PrivateImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ManifestEditorController extends Controller
{
    public function show(Request $request, FuneralCase $case, ManifestEditor $editor)
    {
        $editor->kind = $request->validate(['kind' => 'sometimes|in:manifest,prelapide'])['kind'] ?? 'manifest';

        return response()->json($editor->bootstrap($case))->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, FuneralCase $case, ManifestEditor $editor)
    {
        $editor->kind = $request->validate(['kind' => 'sometimes|in:manifest,prelapide'])['kind'] ?? 'manifest';
        Gate::authorize('update', $case);
        $input = $request->validate([
            'revision' => 'present|nullable|uuid', 'source' => 'required|string|size:64',
            'freshAssets' => 'sometimes|array|max:100', 'freshAssets.*' => ['string', 'regex:/^(background|photo|logo|symbol|symbol_[0-9]+|frame_[0-9]+|upload_[0-9]+)$/'],
            'scene' => 'required|array', 'image' => 'required|string|max:8388608', 'generate' => 'sometimes|boolean',
        ]);
        $revision = $editor->save($case, $input);
        $document = ! empty($input['generate']) ? app(PdfGenerator::class)->generate($case->fresh(), $editor->kind) : null;

        return response()->json(['revision' => $revision, 'documentUrl' => $document ? route('private.document', $document) : null])->header('Cache-Control', 'private, no-store');
    }

    public function reset(Request $request, FuneralCase $case, ManifestEditor $editor)
    {
        $editor->kind = $request->validate(['kind' => 'sometimes|in:manifest,prelapide'])['kind'] ?? 'manifest';
        Gate::authorize('update', $case);
        $input = $request->validate(['revision' => 'present|nullable|uuid']);
        $editor->reset($case, $input['revision']);

        return response()->json(['revision' => null]);
    }

    public function symbol(Symbol $symbol)
    {
        Gate::authorize('viewAny', FuneralCase::class);
        abort_unless($symbol->is_active || auth()->user()->isAdmin(), 404);

        $uri = app(PrivateImage::class)->dataUri($symbol->image_path);
        $svg = str_starts_with($uri, 'data:image/svg+xml;base64,');

        return response($svg ? base64_decode(explode(',', $uri, 2)[1]) : Storage::disk('local')->get($symbol->image_path), 200, ['Content-Type' => $svg ? 'image/svg+xml' : Storage::disk('local')->mimeType($symbol->image_path), 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox"]);
    }

    public function upload(Request $request, FuneralCase $case)
    {
        Gate::authorize('update', $case);
        abort_if($case->trashed(), 403);
        $file = $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:10240'])['image'];

        return DB::transaction(function () use ($case, $file) {
            $locked = FuneralCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $locked);
            abort_if($locked->trashed(), 403);
            abort_if(DB::table('editor_attachments')->where('funeral_case_id', $case->id)->count() >= 50, 422, 'Massimo 50 immagini aggiuntive per pratica.');
            $path = app(OptimizedUpload::class)->store($file, 'editor-uploads');
            try {
                $id = DB::table('editor_attachments')->insertGetId(['funeral_case_id' => $case->id, 'image_path' => $path, 'created_at' => now(), 'updated_at' => now()]);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($path);
                throw $e;
            }

            return response()->json(['key' => 'upload_'.$id, 'title' => 'Immagine '.$id, 'url' => route('private.manifest-editor.attachment', [$case, $id])]);
        });
    }

    public function attachment(FuneralCase $case, int $attachment)
    {
        Gate::authorize('view', $case);
        $file = DB::table('editor_attachments')->where('funeral_case_id', $case->id)->where('id', $attachment)->first();
        abort_unless($file, 404);

        return response()->file(Storage::disk('local')->path($file->image_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function font(string $font)
    {
        Gate::authorize('viewAny', FuneralCase::class);
        $files = [
            'serif' => 'DejaVuSerif.ttf', 'serif-bold' => 'DejaVuSerif-Bold.ttf',
            'serif-italic' => 'DejaVuSerif-Italic.ttf', 'serif-bold-italic' => 'DejaVuSerif-BoldItalic.ttf',
            'sans' => 'DejaVuSans.ttf', 'sans-bold' => 'DejaVuSans-Bold.ttf',
            'sans-italic' => 'DejaVuSans-Oblique.ttf', 'sans-bold-italic' => 'DejaVuSans-BoldOblique.ttf',
        ];
        abort_unless(isset($files[$font]), 404);

        return response()->file(base_path('vendor/dompdf/dompdf/lib/fonts/'.$files[$font]), ['Content-Type' => 'font/ttf', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}
