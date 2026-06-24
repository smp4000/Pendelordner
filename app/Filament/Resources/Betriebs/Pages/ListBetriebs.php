<?php

namespace App\Filament\Resources\Betriebs\Pages;

use App\Filament\Resources\Betriebs\BetriebResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBetriebs extends ListRecords
{
    protected static string $resource = BetriebResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
