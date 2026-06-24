<?php

namespace App\Filament\Resources\Lieferants\Pages;

use App\Filament\Resources\Lieferants\LieferantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLieferants extends ListRecords
{
    protected static string $resource = LieferantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
