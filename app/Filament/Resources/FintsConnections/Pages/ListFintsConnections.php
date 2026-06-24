<?php

namespace App\Filament\Resources\FintsConnections\Pages;

use App\Filament\Resources\FintsConnections\FintsConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFintsConnections extends ListRecords
{
    protected static string $resource = FintsConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
