<?php

namespace App\Filament\Resources\Kostenstelles\Pages;

use App\Filament\Resources\Kostenstelles\KostenstelleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKostenstelles extends ListRecords
{
    protected static string $resource = KostenstelleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
