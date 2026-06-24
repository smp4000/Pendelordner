<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Status-Workflow eines Bankumsatzes (Modul 2).
 *
 * Farblogik laut Spezifikation:
 *   Rot   = kein Beleg (offen)
 *   Gelb  = teilweise bearbeitet
 *   Grün  = vollständig bearbeitet / geprüft
 */
enum BankumsatzStatus: string implements HasLabel, HasColor, HasIcon
{
    case Offen = 'offen';
    case TeilweiseZugeordnet = 'teilweise_zugeordnet';
    case VollstaendigZugeordnet = 'vollstaendig_zugeordnet';
    case Geprueft = 'geprueft';

    public function getLabel(): string
    {
        return match ($this) {
            self::Offen => 'Offen',
            self::TeilweiseZugeordnet => 'Teilweise zugeordnet',
            self::VollstaendigZugeordnet => 'Vollständig zugeordnet',
            self::Geprueft => 'Geprüft',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Offen => 'danger',
            self::TeilweiseZugeordnet => 'warning',
            self::VollstaendigZugeordnet => 'success',
            self::Geprueft => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Offen => 'heroicon-o-exclamation-circle',
            self::TeilweiseZugeordnet => 'heroicon-o-clock',
            self::VollstaendigZugeordnet => 'heroicon-o-check-circle',
            self::Geprueft => 'heroicon-o-shield-check',
        };
    }
}
