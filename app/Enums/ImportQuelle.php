<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImportQuelle: string implements HasLabel
{
    case Fints = 'fints';
    case Mt940 = 'mt940';
    case Camt = 'camt';
    case Csv = 'csv';
    case Manuell = 'manuell';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fints => 'FinTS (Direktabruf)',
            self::Mt940 => 'MT940-Datei',
            self::Camt => 'CAMT.053 (XML)',
            self::Csv => 'CSV-Datei',
            self::Manuell => 'Manuell erfasst',
        };
    }
}
