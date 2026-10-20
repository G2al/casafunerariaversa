<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CaseType: string implements HasLabel
{
    case Cremation = 'cremation';
    case StandardTransport = 'standard_transport';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cremation => 'Cremazione', self::StandardTransport => 'Trasporto funebre normale'
        };
    }
}
