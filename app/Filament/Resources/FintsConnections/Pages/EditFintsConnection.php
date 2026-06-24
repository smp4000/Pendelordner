<?php

namespace App\Filament\Resources\FintsConnections\Pages;

use App\Filament\Resources\FintsConnections\FintsConnectionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFintsConnection extends EditRecord
{
    protected static string $resource = FintsConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
