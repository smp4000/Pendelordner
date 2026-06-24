<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReceiptStatus: string implements HasLabel, HasColor
{
    case New = 'new';
    case Allocated = 'allocated';
    case Paid = 'paid';
    case Reviewed = 'reviewed';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Neu',
            self::Allocated => 'Zugeordnet',
            self::Paid => 'Bezahlt',
            self::Reviewed => 'Geprüft',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::Allocated => 'warning',
            self::Paid => 'info',
            self::Reviewed => 'success',
        };
    }
}
