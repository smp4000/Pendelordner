<?php

namespace App\Filament\Resources\MatchingRules\Pages;

use App\Filament\Resources\MatchingRules\MatchingRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMatchingRules extends ListRecords
{
    protected static string $resource = MatchingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
