<?php

namespace App\Filament\Resources\FintsZugangs\Pages;

use App\Filament\Resources\FintsZugangs\FintsZugangResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFintsZugang extends EditRecord
{
    protected static string $resource = FintsZugangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
