<?php

namespace App\Filament\Resources\Kategories\Pages;

use App\Filament\Resources\Kategories\KategorieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKategories extends ListRecords
{
    protected static string $resource = KategorieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
