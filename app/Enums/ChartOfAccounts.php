<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Kontenrahmen für die Kontierung (Modul 13/14). */
enum ChartOfAccounts: string implements HasLabel
{
    case Skr03 = 'skr03';
    case Skr04 = 'skr04';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Skr03 => 'SKR03',
            self::Skr04 => 'SKR04',
            self::Other => 'Sonstige',
        };
    }
}
