<?php

namespace App\Filament\Resources\Lieferants\Pages;

use App\Filament\Resources\Lieferants\LieferantResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditLieferant extends EditRecord
{
    protected static string $resource = LieferantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
