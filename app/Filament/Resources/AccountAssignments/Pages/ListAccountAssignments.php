<?php

namespace App\Filament\Resources\AccountAssignments\Pages;

use App\Filament\Resources\AccountAssignments\AccountAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccountAssignments extends ListRecords
{
    protected static string $resource = AccountAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
