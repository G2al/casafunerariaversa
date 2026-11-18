<?php

namespace App\Services;

use App\Models\CompanySettings;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportPdf
{
    public function download(string $filename, string $title, array $data)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $logo = app(PrivateImage::class)->dataUri(CompanySettings::current()['logo_path'] ?? null)
            ?? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('images/logo-casafuneraria.png')));
        $pdf = Pdf::loadView('reports.pdf', $data + ['title' => $title, 'logo' => $logo, 'generated' => now()->format('d/m/Y H:i')])
            ->setPaper('a4', 'landscape')->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
    }
}
