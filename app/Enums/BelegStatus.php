<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BelegStatus: string implements HasLabel, HasColor
{
    case Neu = 'neu';
    case Zugeordnet = 'zugeordnet';
    case Bezahlt = 'bezahlt';
    case Geprueft = 'geprueft';

    public function getLabel(): string
    {
        return match ($this) {
            self::Neu => 'Neu',
            self::Zugeordnet => 'Zugeordnet',
            self::Bezahlt => 'Bezahlt',
            self::Geprueft => 'Geprüft',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Neu => 'danger',
            self::Zugeordnet => 'warning',
            self::Bezahlt => 'info',
            self::Geprueft => 'success',
        };
    }
}
