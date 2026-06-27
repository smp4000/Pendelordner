<?php

namespace App\Filament\Pages;

use App\Models\BankAccount;
use App\Models\ReportNote;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Hinweise an das Steuerbüro (Modul 12): frei sortierbare Karten je Bankkonto
 * und Monat (Überschrift + Zeilen aus Betrag | Text). Sie erscheinen im
 * Monatsbericht auf Seite 2 unter der Zusammenfassung.
 */
class SteuerbueroHinweise extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.steuerbuero-hinweise';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Auswertungen';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Steuerbüro-Hinweise';

    protected static ?string $navigationLabel = 'Steuerbüro-Hinweise';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'bank_account_id' => BankAccount::orderBy('label')->value('id'),
            'year' => (string) Carbon::now()->year,
            'month' => (string) Carbon::now()->month,
            'cards' => [],
        ]);

        $this->loadCards();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Auswahl')
                    ->columns(4)
                    ->schema([
                        Select::make('bank_account_id')
                            ->label('Bankkonto')
                            ->columnSpan(2)
                            ->options(BankAccount::orderBy('label')->pluck('label', 'id'))
                            ->live()
                            ->required(),
                        Select::make('month')
                            ->label('Monat')
                            ->options($this->monthNames())
                            ->live()
                            ->required(),
                        Select::make('year')
                            ->label('Jahr')
                            ->options($this->yearOptions())
                            ->live()
                            ->required(),
                    ]),

                Repeater::make('cards')
                    ->label('Hinweis-Karten (per Drag & Drop sortierbar)')
                    ->addActionLabel('Neue Karte')
                    ->reorderable()
                    ->reorderableWithDragAndDrop()
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(fn (array $state): string => $state['heading'] ?? 'Hinweis')
                    ->schema([
                        TextInput::make('heading')
                            ->label('Überschrift')
                            ->placeholder('z. B. Auszug 1 Blatt 5'),
                        Repeater::make('lines')
                            ->label('Zeilen')
                            ->addActionLabel('Zeile hinzufügen')
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->columns(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Betrag')
                                    ->placeholder('z. B. 120 € S'),
                                Textarea::make('text')
                                    ->label('Text')
                                    ->placeholder('z. B. Firmenwagen (mehrzeilig möglich)')
                                    ->rows(2)
                                    ->autosize(),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function updatedData(mixed $value, string $key): void
    {
        // Bei Wechsel von Konto, Monat oder Jahr die Karten neu laden.
        if (in_array($key, ['bank_account_id', 'month', 'year'], true)) {
            $this->loadCards();
        }
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Hinweise speichern')
            ->icon('heroicon-o-check')
            ->action(fn () => $this->save());
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $accId = $state['bank_account_id'] ?? null;
        $month = $this->selectedMonth($state);

        if (! $accId || ! $month) {
            Notification::make()->title('Bitte Konto, Monat und Jahr wählen')->warning()->send();

            return;
        }

        // Bestehende Karten dieses Konto/Monats ersetzen (Zeilen via FK-Cascade).
        ReportNote::where('bank_account_id', $accId)
            ->whereYear('period', $month->year)
            ->whereMonth('period', $month->month)
            ->delete();

        $cardCount = 0;
        foreach (($state['cards'] ?? []) as $i => $card) {
            $heading = trim((string) ($card['heading'] ?? ''));
            $lines = $card['lines'] ?? [];
            $hasLine = collect($lines)->contains(
                fn ($l) => trim((string) ($l['amount'] ?? '')) !== '' || trim((string) ($l['text'] ?? '')) !== ''
            );

            if ($heading === '' && ! $hasLine) {
                continue;
            }

            $note = ReportNote::create([
                'bank_account_id' => $accId,
                'period' => $month,
                'heading' => $heading ?: null,
                'sort_order' => $i,
            ]);

            foreach ($lines as $j => $line) {
                $amount = trim((string) ($line['amount'] ?? ''));
                $text = trim((string) ($line['text'] ?? ''));
                if ($amount === '' && $text === '') {
                    continue;
                }
                $note->lines()->create([
                    'amount' => $amount ?: null,
                    'text' => $text ?: null,
                    'sort_order' => $j,
                ]);
            }

            $cardCount++;
        }

        $this->loadCards();

        Notification::make()
            ->title('Hinweise gespeichert')
            ->body($cardCount . ' Karte(n) für ' . $this->monthNames()[(int) $month->month] . ' ' . $month->year . ' gespeichert.')
            ->success()->send();
    }

    /** Karten des gewählten Konto/Monats in das Formular laden. */
    private function loadCards(): void
    {
        $accId = $this->data['bank_account_id'] ?? null;
        $month = $this->selectedMonth($this->data);

        if (! $accId || ! $month) {
            $this->data['cards'] = [];

            return;
        }

        $notes = ReportNote::with('lines')
            ->where('bank_account_id', $accId)
            ->whereYear('period', $month->year)
            ->whereMonth('period', $month->month)
            ->orderBy('sort_order')
            ->get();

        $this->data['cards'] = $notes->map(fn (ReportNote $n) => [
            'heading' => $n->heading,
            'lines' => $n->lines->map(fn ($l) => [
                'amount' => $l->amount,
                'text' => $l->text,
            ])->values()->all(),
        ])->values()->all();
    }

    /** Aus den gewählten Werten (Monat + Jahr) den ersten Tag des Monats bilden. */
    private function selectedMonth(array $state): ?Carbon
    {
        $year = (int) ($state['year'] ?? 0);
        $month = (int) ($state['month'] ?? 0);

        if ($year < 2000 || $month < 1 || $month > 12) {
            return null;
        }

        return Carbon::create($year, $month, 1)->startOfMonth();
    }

    /** @return array<int, string> Monatsnamen 1..12 */
    private function monthNames(): array
    {
        return [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
            7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];
    }

    /** @return array<int, string> Jahre vom aktuellen bis 6 Jahre zurück */
    private function yearOptions(): array
    {
        $current = Carbon::now()->year;
        $options = [];
        for ($y = $current; $y >= $current - 6; $y--) {
            $options[$y] = (string) $y;
        }

        return $options;
    }
}
