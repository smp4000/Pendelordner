<?php

namespace App\Filament\Resources\AccountAssignments\Pages;

use App\Filament\Resources\AccountAssignments\AccountAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccountAssignment extends EditRecord
{
    protected static string $resource = AccountAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
