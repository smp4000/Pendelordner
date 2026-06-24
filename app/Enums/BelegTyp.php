<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Belegtyp – entspricht den Upload-Tabs (Rechnungseingang, Rechnungsausgang,
 * Kasse, Sonstige).
 */
enum BelegTyp: string implements HasLabel, HasColor
{
    case Rechnungseingang = 'rechnungseingang';
    case Rechnungsausgang = 'rechnungsausgang';
    case Kasse = 'kasse';
    case Sonstige = 'sonstige';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rechnungseingang => 'Rechnungseingang',
            self::Rechnungsausgang => 'Rechnungsausgang',
            self::Kasse => 'Kasse',
            self::Sonstige => 'Sonstige',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Rechnungseingang => 'danger',
            self::Rechnungsausgang => 'success',
            self::Kasse => 'warning',
            self::Sonstige => 'gray',
        };
    }
}
