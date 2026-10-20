<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Operator = 'operator';

    public function getLabel(): string
    {
        return $this === self::Admin ? 'Amministratore' : 'Operatore';
    }
}
