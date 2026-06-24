<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Kontenrahmen für die Kontierung (Modul 13/14). SKR03 und SKR04 sind die in
 * Deutschland gebräuchlichsten; weitere können ergänzt werden.
 */
enum Kontenrahmen: string implements HasLabel
{
    case Skr03 = 'skr03';
    case Skr04 = 'skr04';
    case Sonstige = 'sonstige';

    public function getLabel(): string
    {
        return match ($this) {
            self::Skr03 => 'SKR03',
            self::Skr04 => 'SKR04',
            self::Sonstige => 'Sonstige',
        };
    }
}
