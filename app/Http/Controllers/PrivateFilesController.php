<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\FuneralDocument;
use App\Models\ManifestTemplate;
use App\Services\PdfGenerator;
use App\Services\PrivateIdentityDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PrivateFilesController extends Controller
{
    public function preview(FuneralCase $case, string $kind, PdfGenerator $generator)
    {
        Gate::authorize('view', $case);
        try {
            $rendered = $generator->render($case, $kind);
        } catch (ValidationException $error) {
            return response()->view('pdf.missing', ['errors' => $error->errors(), 'case' => $case], 422)->header('Cache-Control', 'private, no-store');
        }

        return response($rendered['bytes'])->withHeaders(['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="anteprima.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function document(FuneralDocument $document)
    {
        Gate::authorize('view', $document->funeralCase);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->funeralCase->code.'-'.$document->kind.'-v'.$document->version.'.pdf', ['Cache-Control' => 'private, no-store']);
    }

    public function photo(FuneralCase $case)
    {
        Gate::authorize('view', $case);
        abort_unless($case->photo_path && Storage::disk('local')->exists($case->photo_path), 404);

        return Storage::disk('local')->response($case->photo_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function template(ManifestTemplate $template)
    {
        abort_unless(auth()->user()->isAdmin() || $template->is_active, 404);
        abort_unless(Storage::disk('local')->exists($template->image_path), 404);

        return Storage::disk('local')->response($template->image_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function identity(FuneralCase $case, string $side)
    {
        Gate::authorize('view', $case);
        abort_unless(in_array($side, ['front', 'back'], true), 404);
        $path = $case->{'identity_'.$side.'_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        app(PrivateIdentityDocument::class)->validate($path);

        return Storage::disk('local')->download($path, $case->code.'-documento-'.$side.'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function logo()
    {
        $path = CompanySettings::current()['logo_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
