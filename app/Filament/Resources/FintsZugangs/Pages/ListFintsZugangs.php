<?php

namespace App\Filament\Resources\FintsZugangs\Pages;

use App\Filament\Resources\FintsZugangs\FintsZugangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFintsZugangs extends ListRecords
{
    protected static string $resource = FintsZugangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
