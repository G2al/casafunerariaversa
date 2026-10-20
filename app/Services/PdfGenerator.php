<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\FuneralCase;
use App\Models\FuneralDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfGenerator
{
    public function render(FuneralCase $case, string $kind): array
    {
        Gate::authorize('view', $case);
        abort_unless(in_array($kind, ['document', 'manifest'], true), 404);
        $data = app(DocumentData::class)->assemble($case, $kind);
        if ($kind === 'manifest') {
            $data['layout'] = app(ManifestLayout::class)->fit($data);
        }
        $view = $kind === 'manifest' ? 'pdf.manifest' : 'pdf.'.$case->type->value;
        $pdf = Pdf::loadView($view, ['data' => $data])->setPaper('a4', $kind === 'manifest' ? 'landscape' : 'portrait')
            ->setOptions(['defaultFont' => 'DejaVu Serif', 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);

        return ['bytes' => $pdf->output(), 'snapshot' => $data];
    }

    public function generate(FuneralCase $case, string $kind): FuneralDocument
    {
        Gate::authorize('update', $case);
        $path = null;
        try {
            return DB::transaction(function () use ($case, $kind, &$path) {
                $locked = FuneralCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('update', $locked);
                $rendered = $this->render($locked, $kind);
                $path = 'pdf/'.Str::uuid().'.pdf';
                if (! Storage::disk('local')->put($path, $rendered['bytes'])) {
                    throw new \RuntimeException('Salvataggio PDF non riuscito.');
                }
                $document = FuneralDocument::create([
                    'funeral_case_id' => $locked->id, 'created_by' => auth()->id(), 'kind' => $kind,
                    'case_type' => $locked->type->value, 'version' => FuneralDocument::where('funeral_case_id', $locked->id)->where('kind', $kind)->max('version') + 1,
                    'file_path' => $path, 'snapshot' => $rendered['snapshot'],
                    'manifest_template_id' => $kind === 'manifest' ? $locked->manifest_template_id : null,
                ]);
                AuditEvent::create(['user_id' => auth()->id(), 'funeral_case_id' => $locked->id, 'event' => 'generated_'.$kind, 'changed_fields' => ['document_id' => $document->id]]);

                return $document;
            }, 3);
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }
}
