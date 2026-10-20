<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CaseStatus: string implements HasLabel
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Bozza', self::InProgress => 'In lavorazione', self::Completed => 'Completata', self::Archived => 'Archiviata'
        };
    }
}
