<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IdentityDocumentType: string implements HasLabel
{
    case DrivingLicense = 'driving_license';
    case PaperIdentityCard = 'paper_id';
    case ElectronicIdentityCard = 'electronic_id';
    case Passport = 'passport';

    public function getLabel(): string
    {
        return match ($this) {
            self::DrivingLicense => 'Patente',
            self::PaperIdentityCard => 'Carta d’identità cartacea',
            self::ElectronicIdentityCard => 'Carta d’identità elettronica',
            self::Passport => 'Passaporto',
        };
    }
}
