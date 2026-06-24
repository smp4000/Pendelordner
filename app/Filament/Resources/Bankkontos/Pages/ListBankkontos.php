<?php

namespace App\Filament\Resources\Bankkontos\Pages;

use App\Filament\Resources\Bankkontos\BankkontoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankkontos extends ListRecords
{
    protected static string $resource = BankkontoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
