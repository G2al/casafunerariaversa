<?php

namespace App\Services;

use App\Enums\CaseType;
use App\Models\AuditEvent;
use App\Models\FuneralCase;
use App\Models\FuneralDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        $cremationRequest = $kind === 'document' && $case->type === CaseType::Cremation;
        foreach ($cremationRequest ? [9.2, 8.8, 8.4, 8.0, 7.6, 7.2] : [null] as $fontSize) {
            if ($cremationRequest) {
                $data['request']['font_size'] = $fontSize;
            }
            $pdf = Pdf::loadView($view, ['data' => $data])->setPaper('a4', $kind === 'manifest' ? 'landscape' : 'portrait')
                ->setOptions(['defaultFont' => $cremationRequest ? 'DejaVu Sans' : 'DejaVu Serif', 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);
            $bytes = $pdf->output();
            if (! $cremationRequest || $pdf->getDomPDF()->getCanvas()->get_page_count() === 4) {
                return ['bytes' => $bytes, 'snapshot' => $data];
            }
        }
        throw ValidationException::withMessages(['document' => 'I contenuti superano lo spazio delle quattro pagine. Riduci le note o verifica la lunghezza dei dati aziendali e delle abilitazioni del personale. Nessun testo è stato tagliato.']);
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
