<?php

namespace App\Http\Controllers;

use App\Models\FuneralCase;
use App\Services\ManifestEditor;
use App\Services\PdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ManifestEditorController extends Controller
{
    public function show(FuneralCase $case, ManifestEditor $editor)
    {
        return response()->json($editor->bootstrap($case))->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request, FuneralCase $case, ManifestEditor $editor)
    {
        Gate::authorize('update', $case);
        $input = $request->validate([
            'revision' => 'present|nullable|uuid', 'source' => 'required|string|size:64',
            'scene' => 'required|array', 'image' => 'required|string|max:8388608', 'generate' => 'sometimes|boolean',
        ]);
        $revision = $editor->save($case, $input);
        $document = ! empty($input['generate']) ? app(PdfGenerator::class)->generate($case->fresh(), 'manifest') : null;

        return response()->json(['revision' => $revision, 'documentUrl' => $document ? route('private.document', $document) : null])->header('Cache-Control', 'private, no-store');
    }

    public function reset(Request $request, FuneralCase $case, ManifestEditor $editor)
    {
        Gate::authorize('update', $case);
        $input = $request->validate(['revision' => 'present|nullable|uuid']);
        $editor->reset($case, $input['revision']);

        return response()->json(['revision' => null]);
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
