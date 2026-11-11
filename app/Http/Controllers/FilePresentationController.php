<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use App\Models\FuneralCase;
use App\Models\FuneralVehicle;
use App\Models\ManifestRecord;
use App\Models\ManifestTemplate;
use App\Models\Symbol;
use App\Services\SafeSvg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class FilePresentationController extends Controller
{
    public function show(Request $request, string $type, int $record, string $part = '0')
    {
        $path = null;
        if ($type === 'case') {
            $case = FuneralCase::findOrFail($record);
            Gate::authorize('view', $case);
            $field = ['photo' => 'photo_path', 'identity-front' => 'identity_front_path', 'identity-back' => 'identity_back_path', 'delegate-front' => 'delegate_identity_front_path', 'delegate-back' => 'delegate_identity_back_path', 'death-certificate' => 'death_certificate_path', 'istat' => 'istat_path'][$part] ?? null;
            abort_unless($field, 404);
            $path = $case->$field;
        } elseif (in_array($type, ['vehicle', 'vehicle-photo'], true)) {
            $vehicle = FuneralVehicle::findOrFail($record);
            if ($type === 'vehicle') {
                Gate::authorize('view', $vehicle);
            } else {
                Gate::authorize('create', FuneralCase::class);
                abort_unless(auth()->user()->isAdmin() || $vehicle->is_active || FuneralCase::visibleTo(auth()->user())->where('vehicle_id', $vehicle->id)->exists(), 403);
            }
            $path = $vehicle->attachments[(int) $part] ?? null;
            if ($type === 'vehicle-photo') {
                abort_unless($path && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true), 404);
            }
        } elseif (in_array($type, ['editor-case', 'editor-manifest'], true)) {
            $owner = $type === 'editor-case' ? FuneralCase::findOrFail($record) : ManifestRecord::findOrFail($record);
            Gate::authorize('view', $owner);
            $file = DB::table('editor_attachments')->where($type === 'editor-case' ? 'funeral_case_id' : 'manifest_record_id', $record)->where('id', (int) $part)->first();
            $path = $file?->image_path;
        } elseif ($type === 'manifest') {
            $manifest = ManifestRecord::findOrFail($record);
            Gate::authorize('view', $manifest);
            $path = $manifest->photo_path;
        } elseif ($type === 'symbol') {
            $symbol = Symbol::findOrFail($record);
            Gate::authorize('view', $symbol);
            $path = $symbol->image_path;
        } elseif ($type === 'template') {
            $template = ManifestTemplate::findOrFail($record);
            Gate::authorize('view', $template);
            $path = $template->image_path;
        } elseif ($type === 'company') {
            Gate::authorize('viewAny', CompanySettings::class);
            $path = CompanySettings::current()['logo_path'] ?? null;
        }
        abort_unless($path && ! str_contains($path, '..') && ! str_contains($path, '\\') && Storage::disk('local')->exists($path), 404);
        $mime = Storage::disk('local')->mimeType($path);
        $bytes = Storage::disk('local')->get($path);
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
            $bytes = app(SafeSvg::class)->sanitize($bytes)['bytes'];
            $mime = 'image/svg+xml';
        }
        if ($request->boolean('download')) {
            return response($bytes, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'attachment; filename="'.basename($path).'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        if ($request->boolean('raw')) {
            return response($bytes, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'inline; filename="'.basename($path).'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox allow-same-origin allow-modals"]);
        }

        return response()->view('files.print', ['pdf' => $mime === 'application/pdf', 'source' => route('private.file-present', [$type, $record, $part, 'raw' => 1]), 'download' => route('private.file-present', [$type, $record, $part, 'download' => 1])])->header('Cache-Control', 'private, no-store');
    }
}
