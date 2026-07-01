<?php

namespace App\Filament\Pages;

use App\Models\BankAccount;
use App\Models\ReportNote;
use App\Models\SteuerDocument;
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
use Illuminate\Support\Collection;
use Livewire\WithFileUploads;
use Throwable;
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
    use WithFileUploads;

    protected string $view = 'filament.pages.steuerbuero-hinweise';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Auswertungen';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Steuerbüro-Hinweise';

    protected static ?string $navigationLabel = 'Steuerbüro-Hinweise';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<int, mixed> Hochzuladende Dateien */
    public array $docUploads = [];

    public string $docCategory = 'Monatsrechnung';

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
                    ->itemLabel(fn (array $state): ?string => $state['heading'] ?? null)
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
        // array_values: Sortierung ergibt sich aus der (ggf. verschobenen)
        // Reihenfolge, nicht aus den Schlüsseln.
        foreach (array_values($state['cards'] ?? []) as $i => $card) {
            $heading = trim((string) ($card['heading'] ?? ''));
            $lines = array_values($card['lines'] ?? []);
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

    /** Hochgeladene Dokumente des gewählten Konto/Monats. */
    public function getDocumentsProperty(): Collection
    {
        $accId = $this->data['bank_account_id'] ?? null;
        $month = $this->selectedMonth($this->data ?? []);
        if (! $accId || ! $month) {
            return collect();
        }

        return SteuerDocument::where('bank_account_id', $accId)
            ->whereYear('period', $month->year)
            ->whereMonth('period', $month->month)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    /** Bereits verwendete Kategorien als Vorschläge (frei erweiterbar). */
    public function getCategorySuggestionsProperty(): array
    {
        $used = SteuerDocument::query()->select('category')->distinct()
            ->orderBy('category')->pluck('category')->filter()->all();

        return array_values(array_unique(array_merge(['Monatsrechnung'], $used)));
    }

    /** PDF-Dateien hochladen und Konto/Monat + Kategorie zuordnen. */
    public function uploadDocuments(): void
    {
        $accId = $this->data['bank_account_id'] ?? null;
        $month = $this->selectedMonth($this->data ?? []);
        if (! $accId || ! $month) {
            Notification::make()->title('Bitte Konto, Monat und Jahr wählen')->warning()->send();

            return;
        }

        $this->validate([
            'docUploads' => 'required|array',
            'docUploads.*' => 'file|max:20480',
        ], [], ['docUploads' => 'Dateien']);

        $category = trim($this->docCategory) ?: 'Monatsrechnung';
        $diskName = config('pendelordner.belege_disk', 'belege');
        $count = 0;
        $skipped = 0;
        $sort = (int) SteuerDocument::where('bank_account_id', $accId)
            ->whereYear('period', $month->year)->whereMonth('period', $month->month)
            ->max('sort_order');

        foreach ($this->docUploads as $file) {
            try {
                $hash = hash('sha256', $file->get());
                $dupe = SteuerDocument::where('bank_account_id', $accId)
                    ->whereYear('period', $month->year)->whereMonth('period', $month->month)
                    ->where('file_hash', $hash)->exists();
                if ($dupe) {
                    $skipped++;

                    continue;
                }
                $path = $file->store($month->format('Y/m'), $diskName);
                SteuerDocument::create([
                    'bank_account_id' => $accId,
                    'period' => $month,
                    'category' => $category,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'file_hash' => $hash,
                    'sort_order' => ++$sort,
                ]);
                $count++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->reset('docUploads');

        Notification::make()
            ->title($count . ' Datei(en) hochgeladen')
            ->body($skipped > 0 ? $skipped . ' Dublette(n) übersprungen.' : '')
            ->success()->send();
    }

    /** Ein Dokument löschen (inkl. Datei). */
    public function deleteDocument(int $id): void
    {
        $doc = SteuerDocument::find($id);
        if ($doc) {
            try {
                \Illuminate\Support\Facades\Storage::disk(config('pendelordner.belege_disk', 'belege'))
                    ->delete($doc->file_path);
            } catch (Throwable $e) {
                report($e);
            }
            $doc->delete();
            Notification::make()->title('Dokument gelöscht')->success()->send();
        }
    }

    /** Karten des gewählten Konto/Monats in das Formular laden. */
    private function loadCards(): void
    {
        $accId = $this->data['bank_account_id'] ?? null;
        $month = $this->selectedMonth($this->data);

        $cards = [];
        if ($accId && $month) {
            $notes = ReportNote::with('lines')
                ->where('bank_account_id', $accId)
                ->whereYear('period', $month->year)
                ->whereMonth('period', $month->month)
                ->orderBy('sort_order')
                ->get();

            $cards = $notes->map(fn (ReportNote $n) => [
                'heading' => $n->heading,
                'lines' => $n->lines->map(fn ($l) => [
                    'amount' => $l->amount,
                    'text' => $l->text,
                ])->values()->all(),
            ])->values()->all();
        }

        // Über form->fill() laden, damit Filament gültige Schlüssel für die
        // Repeater-Einträge vergibt – nötig fürs korrekte Sortieren (Drag & Drop).
        $this->form->fill([
            'bank_account_id' => $accId,
            'year' => $this->data['year'] ?? null,
            'month' => $this->data['month'] ?? null,
            'cards' => $cards,
        ]);
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
