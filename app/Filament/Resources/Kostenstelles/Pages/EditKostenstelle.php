<?php

namespace App\Filament\Resources\Kostenstelles\Pages;

use App\Filament\Resources\Kostenstelles\KostenstelleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditKostenstelle extends EditRecord
{
    protected static string $resource = KostenstelleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
