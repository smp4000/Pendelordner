<?php

namespace App\Filament\Resources\Belegs\Pages;

use App\Filament\Resources\Belegs\BelegResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBelegs extends ListRecords
{
    protected static string $resource = BelegResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
