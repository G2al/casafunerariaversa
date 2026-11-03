<?php

namespace App\Filament\Support;

use Filament\Schemas\Components\Section;

class CaseAttachmentForm
{
    public static function section(): Section
    {
        return Section::make('Certificato di morte e modello ISTAT')->description('Allegati facoltativi e privati. JPEG, PNG e WebP vengono ottimizzati; i PDF restano nel formato originale.')
            ->schema([
                PrivateDocumentUpload::make('death_certificate_path', 'Certificato di morte', 'case-documents', 'private.attachment', 'death-certificate'),
                PrivateDocumentUpload::make('istat_path', 'Modello ISTAT', 'case-documents', 'private.attachment', 'istat'),
            ])->columns(['default' => 1, 'md' => 2]);
    }
}
