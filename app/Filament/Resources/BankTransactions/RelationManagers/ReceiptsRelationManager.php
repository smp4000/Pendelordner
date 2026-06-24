<?php

namespace App\Filament\Resources\BankTransactions\RelationManagers;

use App\Models\BankTransaction;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Verwaltet die zugeordneten Belege eines Bankumsatzes (Modul 5/6). Über den
 * Pivot-Betrag lässt sich ein Umsatz auf mehrere Belege aufteilen. Nach jeder
 * Änderung wird der Status des Umsatzes neu berechnet.
 */
class ReceiptsRelationManager extends RelationManager
{
    protected static string $relationship = 'receipts';

    protected static ?string $title = 'Zugeordnete Belege';

    protected static ?string $modelLabel = 'Beleg';

    protected static ?string $pluralModelLabel = 'Belege';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('amount')
                ->label('Zugeordneter Betrag')
                ->numeric()->prefix('€')->required(),
            Select::make('match_type')
                ->label('Zuordnungsart')
                ->options([
                    'manual' => 'Manuell',
                    'automatic' => 'Automatisch',
                    'confirmed' => 'Bestätigt',
                ])
                ->default('manual'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_number')
            ->columns([
                TextColumn::make('invoice_number')->label('Rechnungs-Nr.')->searchable(),
                TextColumn::make('supplier.name')->label('Lieferant')->placeholder('—'),
                TextColumn::make('invoice_date')->label('Datum')->date('d.m.Y'),
                TextColumn::make('gross_amount')->label('Belegbetrag')->money('EUR')->alignEnd(),
                TextColumn::make('pivot.amount')->label('Zugeordnet')->money('EUR')->alignEnd()->weight('bold'),
                TextColumn::make('pivot.match_score')->label('Treffer')->suffix(' %')->placeholder('—')->alignCenter(),
                TextColumn::make('pivot.match_type')->label('Art')->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Beleg zuordnen')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['invoice_number', 'receipt_number'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label('Beleg'),
                        TextInput::make('amount')
                            ->label('Zugeordneter Betrag')
                            ->numeric()->prefix('€')->required(),
                    ])
                    ->after(fn () => $this->refreshTransactionStatus()),
                CreateAction::make()->label('Neuer Beleg'),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()
                    ->label('Lösen')
                    ->after(fn () => $this->refreshTransactionStatus()),
            ]);
    }

    protected function refreshTransactionStatus(): void
    {
        $owner = $this->getOwnerRecord();
        if ($owner instanceof BankTransaction) {
            $owner->recalculateStatus();
        }
    }
}
