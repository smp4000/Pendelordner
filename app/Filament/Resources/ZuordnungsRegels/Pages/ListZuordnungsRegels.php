<?php

namespace App\Filament\Resources\ZuordnungsRegels\Pages;

use App\Filament\Resources\ZuordnungsRegels\ZuordnungsRegelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListZuordnungsRegels extends ListRecords
{
    protected static string $resource = ZuordnungsRegelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
