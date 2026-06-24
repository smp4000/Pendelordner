<?php

namespace App\Filament\Resources\Kategories\Pages;

use App\Filament\Resources\Kategories\KategorieResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditKategorie extends EditRecord
{
    protected static string $resource = KategorieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
