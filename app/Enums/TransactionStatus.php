<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Status-Workflow eines Bankumsatzes (Modul 2).
 * Ampel: Rot=offen (kein Beleg), Gelb=teilweise, Grün=vollständig/geprüft.
 */
enum TransactionStatus: string implements HasLabel, HasColor, HasIcon
{
    case Open = 'open';
    case PartiallyAllocated = 'partially_allocated';
    case FullyAllocated = 'fully_allocated';
    case Reviewed = 'reviewed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::PartiallyAllocated => 'Teilweise zugeordnet',
            self::FullyAllocated => 'Vollständig zugeordnet',
            self::Reviewed => 'Geprüft',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::PartiallyAllocated => 'warning',
            self::FullyAllocated => 'success',
            self::Reviewed => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Open => 'heroicon-o-exclamation-circle',
            self::PartiallyAllocated => 'heroicon-o-clock',
            self::FullyAllocated => 'heroicon-o-check-circle',
            self::Reviewed => 'heroicon-o-shield-check',
        };
    }
}
