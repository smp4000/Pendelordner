<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OcrStatus: string implements HasLabel, HasColor
{
    case Ausstehend = 'ausstehend';
    case Verarbeitet = 'verarbeitet';
    case Fehler = 'fehler';
    case Uebersprungen = 'uebersprungen';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ausstehend => 'Ausstehend',
            self::Verarbeitet => 'Verarbeitet',
            self::Fehler => 'Fehler',
            self::Uebersprungen => 'Übersprungen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ausstehend => 'warning',
            self::Verarbeitet => 'success',
            self::Fehler => 'danger',
            self::Uebersprungen => 'gray',
        };
    }
}
