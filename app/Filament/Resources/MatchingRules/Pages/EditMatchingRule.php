<?php

namespace App\Filament\Resources\MatchingRules\Pages;

use App\Filament\Resources\MatchingRules\MatchingRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMatchingRule extends EditRecord
{
    protected static string $resource = MatchingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
