<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Kontenrahmen für die Kontierung – im Tankstellenbereich nur edtas. */
enum ChartOfAccounts: string implements HasLabel
{
    case Edtas = 'edtas';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Edtas => 'eDTAS',
            self::Other => 'Sonstige',
        };
    }
}
