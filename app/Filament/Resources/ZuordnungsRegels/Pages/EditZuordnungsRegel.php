<?php

namespace App\Filament\Resources\ZuordnungsRegels\Pages;

use App\Filament\Resources\ZuordnungsRegels\ZuordnungsRegelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditZuordnungsRegel extends EditRecord
{
    protected static string $resource = ZuordnungsRegelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
