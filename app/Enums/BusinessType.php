<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/** Betriebsart (Modul 7). */
enum BusinessType: string implements HasLabel, HasColor, HasIcon
{
    case GasStation = 'gas_station';
    case Workshop = 'workshop';
    case ExpertOffice = 'expert_office';
    case Shop = 'shop';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::GasStation => 'Tankstelle',
            self::Workshop => 'Werkstatt',
            self::ExpertOffice => 'Sachverständigenbüro',
            self::Shop => 'Shop',
            self::Other => 'Sonstige',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::GasStation => 'info',
            self::Workshop => 'warning',
            self::ExpertOffice => 'success',
            self::Shop => 'primary',
            self::Other => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::GasStation => 'heroicon-o-fire',
            self::Workshop => 'heroicon-o-wrench-screwdriver',
            self::ExpertOffice => 'heroicon-o-document-magnifying-glass',
            self::Shop => 'heroicon-o-shopping-bag',
            self::Other => 'heroicon-o-building-office',
        };
    }
}
