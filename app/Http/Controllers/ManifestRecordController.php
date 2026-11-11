<?php

namespace App\Http\Controllers;

use App\Models\ManifestExport;
use App\Models\ManifestRecord;
use App\Services\ManifestEditor;
use App\Services\OptimizedUpload;
use App\Services\PdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManifestRecordController extends Controller
{
    public function show(ManifestRecord $manifest, ManifestEditor $editor)
    {
        return response()->json($editor->bootstrap($manifest))->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, ManifestRecord $manifest, ManifestEditor $editor)
    {
        Gate::authorize('update', $manifest);
        $input = $request->validate(['revision' => 'present|nullable|uuid', 'source' => 'required|string|size:64',
            'freshAssets' => 'sometimes|array|max:100', 'freshAssets.*' => ['string', 'regex:/^(background|photo|logo|symbol|symbol_[0-9]+|frame_[0-9]+|upload_[0-9]+)$/'],
            'scene' => 'required|array', 'image' => 'required|string|max:8388608', 'generate' => 'sometimes|boolean']);
        $revision = $editor->save($manifest, $input);
        $export = ! empty($input['generate']) ? $this->generate($manifest->fresh()) : null;

        return response()->json(['revision' => $revision, 'documentUrl' => $export ? route('private.manifests.export', $export) : null])->header('Cache-Control', 'private, no-store');
    }

    public function reset(Request $request, ManifestRecord $manifest, ManifestEditor $editor)
    {
        $input = $request->validate(['revision' => 'present|nullable|uuid']);
        $editor->reset($manifest, $input['revision']);

        return response()->json(['revision' => null]);
    }

    public function preview(ManifestRecord $manifest, PdfGenerator $generator)
    {
        Gate::authorize('view', $manifest);
        try {
            $render = $generator->render($manifest, 'manifest');
        } catch (ValidationException $e) {
            return response()->view('pdf.missing', ['case' => $manifest, 'errors' => $e->errors()], 422)->header('Cache-Control', 'private, no-store');
        }

        return response($render['bytes'], 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$manifest->code.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function generate(ManifestRecord $manifest): ManifestExport
    {
        Gate::authorize('update', $manifest);
        $path = null;
        try {
            return DB::transaction(function () use ($manifest, &$path) {
                $locked = ManifestRecord::whereKey($manifest->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('update', $locked);
                $render = app(PdfGenerator::class)->render($locked, 'manifest');
                $path = 'pdf/manifests/'.Str::uuid().'.pdf';
                if (! Storage::disk('local')->put($path, $render['bytes'])) {
                    throw new \RuntimeException('Salvataggio PDF non riuscito.');
                }

                return ManifestExport::create(['manifest_record_id' => $locked->id, 'created_by' => auth()->id(), 'version' => ($locked->exports()->max('version') ?? 0) + 1, 'file_path' => $path, 'snapshot' => $render['snapshot']]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
    }

    public function export(ManifestExport $export)
    {
        Gate::authorize('view', $export->manifestRecord);
        abort_unless(Storage::disk('local')->exists($export->file_path), 404);

        return Storage::disk('local')->response($export->file_path, $export->manifestRecord->code.'-v'.$export->version.'.pdf', ['Cache-Control' => 'private, no-store']);
    }

    public function photo(ManifestRecord $manifest)
    {
        Gate::authorize('view', $manifest);
        $path = $manifest->photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function upload(Request $request, ManifestRecord $manifest)
    {
        Gate::authorize('update', $manifest);
        $file = $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:10240'])['image'];

        return DB::transaction(function () use ($manifest, $file) {
            $locked = ManifestRecord::whereKey($manifest->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $locked);
            abort_if(DB::table('editor_attachments')->where('manifest_record_id', $manifest->id)->count() >= 50, 422, 'Massimo 50 immagini.');
            $path = app(OptimizedUpload::class)->store($file, 'editor-uploads');
            try {
                $id = DB::table('editor_attachments')->insertGetId(['manifest_record_id' => $manifest->id, 'image_path' => $path, 'created_at' => now(), 'updated_at' => now()]);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($path);
                throw $e;
            }

            return response()->json(['key' => 'upload_'.$id, 'title' => 'Immagine '.$id, 'url' => route('private.manifests.attachment', [$manifest, $id])]);
        });
    }

    public function attachment(ManifestRecord $manifest, int $attachment)
    {
        Gate::authorize('view', $manifest);
        $file = DB::table('editor_attachments')->where('manifest_record_id', $manifest->id)->where('id', $attachment)->first();
        abort_unless($file, 404);

        return response()->file(Storage::disk('local')->path($file->image_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
