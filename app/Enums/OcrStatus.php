<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OcrStatus: string implements HasLabel, HasColor
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Ausstehend',
            self::Processed => 'Verarbeitet',
            self::Failed => 'Fehler',
            self::Skipped => 'Übersprungen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processed => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
        };
    }
}
