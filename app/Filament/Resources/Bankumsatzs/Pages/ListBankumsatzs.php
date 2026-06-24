<?php

namespace App\Filament\Resources\Bankumsatzs\Pages;

use App\Filament\Resources\Bankumsatzs\BankumsatzResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankumsatzs extends ListRecords
{
    protected static string $resource = BankumsatzResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
