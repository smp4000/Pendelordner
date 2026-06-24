<?php

namespace App\Filament\Resources\Betriebs\Pages;

use App\Filament\Resources\Betriebs\BetriebResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBetrieb extends EditRecord
{
    protected static string $resource = BetriebResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
