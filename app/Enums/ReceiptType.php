<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Belegart – entspricht den Upload-Tabs (Modul 3). */
enum ReceiptType: string implements HasLabel, HasColor
{
    case IncomingInvoice = 'incoming_invoice';
    case OutgoingInvoice = 'outgoing_invoice';
    case Cash = 'cash';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::IncomingInvoice => 'Rechnungseingang',
            self::OutgoingInvoice => 'Rechnungsausgang',
            self::Cash => 'Kasse',
            self::Other => 'Sonstige',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::IncomingInvoice => 'danger',
            self::OutgoingInvoice => 'success',
            self::Cash => 'warning',
            self::Other => 'gray',
        };
    }
}
