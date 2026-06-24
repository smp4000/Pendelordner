<?php

namespace App\Filament\Resources\Bankkontos\Pages;

use App\Filament\Resources\Bankkontos\BankkontoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBankkonto extends EditRecord
{
    protected static string $resource = BankkontoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
