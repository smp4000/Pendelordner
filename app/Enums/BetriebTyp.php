<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BetriebTyp: string implements HasLabel, HasColor, HasIcon
{
    case Tankstelle = 'tankstelle';
    case Werkstatt = 'werkstatt';
    case Sachverstaendigenbuero = 'sachverstaendigenbuero';
    case Shop = 'shop';
    case Sonstige = 'sonstige';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tankstelle => 'Tankstelle',
            self::Werkstatt => 'Werkstatt',
            self::Sachverstaendigenbuero => 'Sachverständigenbüro',
            self::Shop => 'Shop',
            self::Sonstige => 'Sonstige',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Tankstelle => 'info',
            self::Werkstatt => 'warning',
            self::Sachverstaendigenbuero => 'success',
            self::Shop => 'primary',
            self::Sonstige => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Tankstelle => 'heroicon-o-fire',
            self::Werkstatt => 'heroicon-o-wrench-screwdriver',
            self::Sachverstaendigenbuero => 'heroicon-o-document-magnifying-glass',
            self::Shop => 'heroicon-o-shopping-bag',
            self::Sonstige => 'heroicon-o-building-office',
        };
    }
}
