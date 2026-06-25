<?php

namespace App\Filament\Resources\AccountAssignments\Schemas;

use App\Enums\ChartOfAccounts;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AccountAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('assignable_type')
                    ->required(),
                TextInput::make('assignable_id')
                    ->required()
                    ->numeric(),
                Select::make('chart_of_accounts')
                    ->options(ChartOfAccounts::class)
                    ->default('skr03')
                    ->required(),
                TextInput::make('account')
                    ->default(null),
                TextInput::make('contra_account')
                    ->default(null),
                TextInput::make('tax_key')
                    ->default(null),
                Select::make('cost_center_id')
                    ->relationship('costCenter', 'name')
                    ->default(null),
                TextInput::make('cost_center_2')
                    ->default(null),
                TextInput::make('document_number')
                    ->default(null),
                TextInput::make('booking_text')
                    ->default(null),
                DatePicker::make('service_date'),
                DatePicker::make('booking_date'),
                TextInput::make('amount')
                    ->numeric()
                    ->default(null),
                Toggle::make('exported')
                    ->required(),
                Select::make('datev_export_id')
                    ->relationship('datevExport', 'id')
                    ->default(null),
            ]);
    }
}
