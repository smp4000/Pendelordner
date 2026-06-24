<?php

namespace App\Filament\Resources\Bankumsatzs\Pages;

use App\Filament\Resources\Bankumsatzs\BankumsatzResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBankumsatz extends EditRecord
{
    protected static string $resource = BankumsatzResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
