<?php

namespace App\Filament\Resources\Bankumsatzs\RelationManagers;

use App\Models\Bankumsatz;
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
 * Verwaltet die zugeordneten Belege eines Bankumsatzes (Modul 5/6).
 * Über den Pivot-Betrag lässt sich ein Umsatz auf mehrere Belege aufteilen.
 * Nach jeder Änderung wird der Status des Umsatzes neu berechnet.
 */
class BelegeRelationManager extends RelationManager
{
    protected static string $relationship = 'belege';

    protected static ?string $title = 'Zugeordnete Belege';

    protected static ?string $modelLabel = 'Beleg';

    protected static ?string $pluralModelLabel = 'Belege';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('betrag')
                ->label('Zugeordneter Betrag')
                ->numeric()
                ->prefix('€')
                ->required(),
            Select::make('zuordnungs_art')
                ->label('Zuordnungsart')
                ->options([
                    'manuell' => 'Manuell',
                    'automatisch' => 'Automatisch',
                    'bestaetigt' => 'Bestätigt',
                ])
                ->default('manuell'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('rechnungsnummer')
            ->columns([
                TextColumn::make('rechnungsnummer')
                    ->label('Rechnungs-Nr.')
                    ->searchable(),
                TextColumn::make('lieferant.name')
                    ->label('Lieferant')
                    ->placeholder('—'),
                TextColumn::make('rechnungsdatum')
                    ->label('Datum')
                    ->date('d.m.Y'),
                TextColumn::make('betrag_brutto')
                    ->label('Belegbetrag')
                    ->money('EUR')
                    ->alignEnd(),
                TextColumn::make('pivot.betrag')
                    ->label('Zugeordnet')
                    ->money('EUR')
                    ->alignEnd()
                    ->weight('bold'),
                TextColumn::make('pivot.trefferquote')
                    ->label('Treffer')
                    ->suffix(' %')
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('pivot.zuordnungs_art')
                    ->label('Art')
                    ->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Beleg zuordnen')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['rechnungsnummer', 'beleg_nummer'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label('Beleg'),
                        TextInput::make('betrag')
                            ->label('Zugeordneter Betrag')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                    ])
                    ->after(fn () => $this->umsatzStatusAktualisieren()),
                CreateAction::make()->label('Neuer Beleg'),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()
                    ->label('Lösen')
                    ->after(fn () => $this->umsatzStatusAktualisieren()),
            ]);
    }

    protected function umsatzStatusAktualisieren(): void
    {
        $owner = $this->getOwnerRecord();
        if ($owner instanceof Bankumsatz) {
            $owner->statusNeuBerechnen();
        }
    }
}
