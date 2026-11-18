<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Operator = 'operator';
    case ServiceOrderOperator = 'service_order_operator';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Amministratore', self::Operator => 'Operatore', self::ServiceOrderOperator => 'Operatore ordine di servizio'
        };
    }
}
