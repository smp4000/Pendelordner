<?php

namespace App\Filament\Resources\Belegs\Pages;

use App\Filament\Resources\Belegs\BelegResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBeleg extends EditRecord
{
    protected static string $resource = BelegResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
