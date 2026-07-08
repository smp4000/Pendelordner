<?php

namespace App\Filament\Pages;

use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\CostCenter;
use App\Models\LedgerAccount;
use App\Models\MatchingRule;
use App\Models\Receipt;
use App\Services\Matching\MatchingEngine;
use App\Services\Ocr\OcrService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Throwable;
use UnitEnum;

/**
 * Kontoumsatzdetails (Modul 6) – 3-Spalten-Ansicht im DATEV-/Lexware-Stil:
 *   links  = offene Bankumsätze
 *   mitte  = Tabs: Zugeordnete Belege · Vorschläge · Belegsuche · Hochladen
 *   rechts = Vorschau des gewählten Belegs (PDF/Bild)
 */
class Kontoumsatzdetails extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.kontoumsatzdetails';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Kontoumsatzdetails';

    protected static ?string $navigationLabel = 'Kontoumsatzdetails';

    public ?int $selectedTransactionId = null;

    /**
     * Optionale Auswahl-Menge: nur diese Umsatz-IDs werden durchblättert (z. B.
     * aus dem Dashboard-Widget "Offene Aufteilungen" mehrfach ausgewählt).
     *
     * @var list<int>
     */
    public array $navIds = [];

    public ?int $selectedReceiptId = null;

    // Filterkontext aus der Umsatzliste (beschränkt die Navigation).
    public ?int $filterAccountId = null;

    public ?int $filterBusinessId = null;

    public ?int $filterCategoryId = null;

    public ?int $filterCostCenterId = null;

    public ?string $filterFrom = null;

    public ?string $filterTo = null;

    public ?string $filterStatus = null;

    public ?string $filterReviewed = null;

    public ?string $filterWithoutReceipt = null;

    /** Aktiver Tab in der Mitte: assigned | suggestions | search | upload */
    public string $activeTab = 'assigned';

    // --- Manuelle Belegsuche -------------------------------------------------
    public string $searchQuery = '';

    public string $searchAssigned = 'unassigned'; // unassigned | assigned | all

    public string $searchPaid = 'all';            // all | paid | unpaid

    public string $searchType = 'all';            // all | <ReceiptType>

    // --- Upload --------------------------------------------------------------
    /** @var array<int, mixed> */
    public array $uploadFiles = [];

    public string $uploadType = 'incoming_invoice';

    // --- Inline-Zuordnung (Kategorie / Kostenstelle / Sachkonto) -------------
    public ?int $assignCategoryId = null;

    // Durchsuchbare Kategorie-Auswahl (Name oder eDTAS-Konto).
    public string $categorySearch = '';

    public bool $editingCategory = false;

    public ?int $assignCostCenterId = null;

    public ?int $assignLedgerAccountId = null;

    public string $ledgerSearch = '';

    // Neue Kategorie inline anlegen
    public bool $showNewCategory = false;

    public string $newCategory = '';

    // --- Mitteilung an den Steuerberater -------------------------------------
    public bool $showNote = false;

    public string $accountantNote = '';

    /** Hinweis erfordert Reaktion – bleibt als offenes To-Do im Dashboard sichtbar. */
    public bool $noteOpen = false;

    /** Aufteilung noch offen – Umsatz erscheint im Dashboard "Offene Aufteilungen". */
    public bool $splitOpen = false;

    /** Name für "aktuelle Aufteilung als Vorlage speichern". */
    public string $newTemplateName = '';

    // --- Aufteilung auf Sachkonten (G&V) -------------------------------------
    public bool $showSplit = false;

    /** Eingabe der Split-Beträge als 'brutto' oder 'netto'. */
    public string $splitMode = 'brutto';

    /**
     * Split-Positionen des aktuellen Umsatzes – Aufteilung des Betrags auf
     * mehrere Sachkonten. Kategorie/Kostenstelle kommen aus der Haupt-
     * Zuordnung des Umsatzes. Jede Zeile:
     * ['ledger_account_id', 'ledger_label', 'ledger_search', 'tax_rate', 'amount'].
     *
     * @var array<int, array<string, mixed>>
     */
    public array $splits = [];

    // --- Regel aus Umsatz erstellen ------------------------------------------
    public bool $showRuleForm = false;

    public string $rulePattern = '';

    public string $rulePatternType = 'counterparty';

    /** Optionales zweites Kriterium (UND-Verknüpfung), z. B. Vertragsnummer im Verwendungszweck. */
    public string $rulePattern2 = '';

    public string $rulePatternType2 = 'purpose';

    public bool $ruleApplyExisting = true;

    public function mount(): void
    {
        // Filterkontext aus der Umsatzliste übernehmen (Query-Parameter).
        $this->filterAccountId = request('account_id') ? (int) request('account_id') : null;
        $this->filterBusinessId = request('business_id') ? (int) request('business_id') : null;
        $this->filterCategoryId = request('category_id') ? (int) request('category_id') : null;
        $this->filterCostCenterId = request('cost_center_id') ? (int) request('cost_center_id') : null;
        $this->filterFrom = request('from') ?: null;
        $this->filterTo = request('to') ?: null;
        $this->filterStatus = request('status') ?: null;
        $this->filterReviewed = request()->has('reviewed') ? (string) request('reviewed') : null;
        $this->filterWithoutReceipt = request()->has('without_receipt') ? (string) request('without_receipt') : null;

        // Auswahl-Menge (z. B. mehrere aus "Offene Aufteilungen"): ?ids=1,2,3
        $this->navIds = request('ids')
            ? array_values(array_filter(array_map('intval', explode(',', (string) request('ids')))))
            : [];

        $this->selectedTransactionId = request('tx')
            ? (int) request('tx')
            : ($this->navIds[0] ?? $this->navigationQuery()->value('id'));

        $this->fillAssign();
    }

    /** Zuordnungsfelder aus dem aktuellen Umsatz vorbelegen. */
    private function fillAssign(): void
    {
        $t = $this->selectedTransaction;
        $this->assignCategoryId = $t?->category_id;
        $this->assignCostCenterId = $t?->cost_center_id;
        $this->assignLedgerAccountId = $t?->ledger_account_id;
        $this->ledgerSearch = '';
        $this->categorySearch = '';
        $this->editingCategory = false;
        $this->accountantNote = (string) ($t?->accountant_note ?? '');
        $this->noteOpen = (bool) ($t?->note_open ?? false);
        $this->splitOpen = (bool) ($t?->split_open ?? false);
        $this->showNote = $this->accountantNote !== '';
        $this->loadSplits();
    }

    /**
     * Verwirft den Livewire-Cache der berechneten Eigenschaften des ausgewählten
     * Umsatzes und Belegs. Livewire merkt sich das Ergebnis von
     * getSelectedTransactionProperty()/getSelectedReceiptProperty() für die
     * Dauer einer Anfrage. Wird innerhalb derselben Anfrage die Belegzuordnung
     * geändert (hochladen, verschieben, lösen, Betrag ändern), rendert die
     * Seite sonst mit der ALTEN, gecachten Belegsammlung neu – man müsste die
     * Seite manuell neu laden. Nach diesem Unset liest der anschließende Render
     * frisch aus der Datenbank.
     */
    private function refreshSelected(): void
    {
        unset($this->selectedTransaction);
        unset($this->selectedReceipt);
    }

    /** Sichert noch nicht gespeicherte Eingaben des aktuellen Umsatzes (Mitteilung). */
    private function persistPending(): void
    {
        if (! $this->selectedTransactionId) {
            return;
        }

        $note = trim($this->accountantNote);
        BankTransaction::whereKey($this->selectedTransactionId)
            ->update(['accountant_note' => $note !== '' ? $note : null]);
    }

    // --- Aufteilung nach Kategorie (für die G&V) -----------------------------

    public function toggleSplit(): void
    {
        $this->showSplit = ! $this->showSplit;

        if ($this->showSplit && empty($this->splits)) {
            $this->addSplit();
        }
    }

    /** Vorhandene Aufteilungs-Positionen des Umsatzes in den Editor laden. */
    private function loadSplits(): void
    {
        $t = $this->selectedTransaction;
        $this->splits = $t
            ? $t->accountAssignments->map(fn (\App\Models\AccountAssignment $a) => [
                'ledger_account_id' => $a->ledger_account_id,
                'ledger_label' => $a->ledgerAccount ? $a->ledgerAccount->number . ' – ' . $a->ledgerAccount->name : '',
                'ledger_search' => '',
                'tax_rate' => $a->tax_rate !== null ? rtrim(rtrim(number_format((float) $a->tax_rate, 2, ',', ''), '0'), ',') : '19',
                'amount' => $a->amount !== null ? number_format((float) $a->amount, 2, ',', '') : '',
            ])->values()->all()
            : [];
        // Gespeicherte Beträge sind brutto (sie summieren sich zum Umsatzbetrag).
        $this->splitMode = 'brutto';
        $this->showSplit = ! empty($this->splits);
    }

    /** Verfügbare Aufteilungsvorlagen (für das Auswahl-Menü). */
    public function getSplitTemplatesProperty(): Collection
    {
        return \App\Models\SplitTemplate::orderBy('name')->get();
    }

    /**
     * Aufteilungsvorlage anwenden: Konten + USt-Sätze vorbelegen, Beträge leer
     * lassen (die trägt der Nutzer je Umsatz ein). Überschreibt die aktuellen
     * Split-Zeilen.
     */
    public function applyTemplate(int $templateId): void
    {
        $template = \App\Models\SplitTemplate::find($templateId);
        if (! $template) {
            return;
        }

        $rows = [];
        foreach ($template->rows as $r) {
            $la = LedgerAccount::whereIn('chart', ['edtas', 'kfz', 'gastro'])
                ->where('number', (string) ($r['ledger_number'] ?? ''))->first();
            $rows[] = [
                'ledger_account_id' => $la?->id,
                'ledger_label' => $la ? $la->number . ' – ' . $la->name : (string) ($r['label'] ?? ''),
                'ledger_search' => '',
                'tax_rate' => (string) ($r['tax_rate'] ?? '19'),
                'amount' => '',
            ];
        }

        $this->splits = $rows;
        $this->showSplit = true;

        Notification::make()->title('Vorlage „' . $template->name . '" geladen')
            ->body('Jetzt nur noch die Beträge je Zeile eintragen.')->success()->send();
    }

    /** Aktuelle Aufteilung (nur Konten + USt) als wiederverwendbare Vorlage speichern. */
    public function saveSplitAsTemplate(): void
    {
        $name = trim($this->newTemplateName);
        if ($name === '') {
            Notification::make()->title('Bitte einen Namen für die Vorlage angeben')->warning()->send();

            return;
        }

        $rows = [];
        foreach ($this->splits as $r) {
            if (empty($r['ledger_account_id'] ?? null)) {
                continue;
            }
            $la = LedgerAccount::find($r['ledger_account_id']);
            $rows[] = [
                'ledger_number' => (string) ($la?->number ?? ''),
                'tax_rate' => (string) ($r['tax_rate'] ?? ''),
                'label' => (string) ($la?->name ?? ''),
            ];
        }

        if (empty($rows)) {
            Notification::make()->title('Keine Konten zum Speichern')->warning()->send();

            return;
        }

        \App\Models\SplitTemplate::updateOrCreate(['name' => $name], ['rows' => $rows]);
        $this->newTemplateName = '';

        Notification::make()->title('Vorlage „' . $name . '" gespeichert (' . count($rows) . ' Konten)')->success()->send();
    }

    /**
     * Aufteilung aus der USt-Tabelle der Belege erzeugen: liest den
     * Steuer-Summenblock JEDES zugeordneten Belegs (nicht nur eines) und zählt
     * die Bruttobeträge je Steuersatz ÜBER ALLE Belege zusammen. So ergibt eine
     * Zahlung über mehrere Rechnungen (z. B. drei SB-Union-Rechnungen) genau
     * zwei Zeilen: Summe 19 % und Summe 7 %. Belege ohne erkennbaren
     * Summenblock, aber mit einem einzelnen Satz + Bruttobetrag, fließen mit
     * diesem Betrag in den passenden Satz ein. Das Sachkonto bleibt offen.
     */
    public function fillSplitFromReceiptTax(): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }

        $parser = new \App\Services\Ocr\ReceiptParser();
        $byRate = [];        // Steuersatz => aufsummierter Bruttobetrag
        $usedReceipts = 0;

        foreach ($transaction->receipts as $r) {
            $rates = filled($r->ocr_text) ? $parser->taxBreakdown((string) $r->ocr_text) : [];

            if (! empty($rates)) {
                foreach ($rates as $row) {
                    $byRate[$row['rate']] = round(($byRate[$row['rate']] ?? 0) + $row['gross'], 2);
                }
                $usedReceipts++;

                continue;
            }

            // Kein Summenblock: Beleg mit genau einem Satz + Bruttobetrag
            // trotzdem berücksichtigen (z. B. reine 19 %- oder 7 %-Rechnung).
            $gross = (float) $r->gross_amount;
            $rate = $r->tax_rate !== null ? (int) round((float) $r->tax_rate) : null;
            if ($gross != 0.0 && in_array($rate, [19, 7], true)) {
                $byRate[$rate] = round(($byRate[$rate] ?? 0) + $gross, 2);
                $usedReceipts++;
            }
        }

        if (empty($byRate)) {
            Notification::make()->title('Keine USt-Aufteilung in den Belegen gefunden')
                ->body('Kein zugeordneter Beleg weist einen Steuer-Summenblock (19 %/7 %) aus.')
                ->warning()->send();

            return;
        }

        krsort($byRate); // 19 % vor 7 %

        $this->splits = [];
        foreach ($byRate as $rate => $gross) {
            $this->splits[] = [
                'ledger_account_id' => null,
                'ledger_label' => '',
                'ledger_search' => '',
                'tax_rate' => (string) $rate,
                'amount' => number_format($gross, 2, ',', ''),
            ];
        }
        $this->splitMode = 'brutto';
        $this->showSplit = true;

        Notification::make()->title(count($byRate) . ' Steuersätze aus ' . $usedReceipts . ' Beleg(en) übernommen')
            ->body('Bruttobeträge je Satz aufsummiert – jetzt nur noch das Sachkonto (Warengruppe) wählen.')
            ->success()->send();
    }

    /** Neue, leere Position – Betrag mit dem Restbetrag vorbelegen. */
    public function addSplit(): void
    {
        $rest = $this->splitRemaining;
        $this->splits[] = [
            'ledger_account_id' => null,
            'ledger_label' => '',
            'ledger_search' => '',
            'tax_rate' => '19',
            'amount' => $rest > 0.005 ? number_format($rest, 2, ',', '') : '',
        ];
    }

    public function removeSplit(int $index): void
    {
        unset($this->splits[$index]);
        $this->splits = array_values($this->splits);
        $this->autoSaveSplits();
    }

    /** Bruttobetrag einer Zeile (im Netto-Modus inkl. USt hochgerechnet). */
    private function splitGross(array $row): float
    {
        $amount = $this->parseAmount($row['amount'] ?? '');
        if ($this->splitMode === 'netto') {
            $rate = $this->parseAmount($row['tax_rate'] ?? '0');

            return round($amount * (1 + $rate / 100), 2);
        }

        return $amount;
    }

    /** Summe der Split-Positionen als Brutto (vergleichbar mit dem Umsatzbetrag). */
    public function getSplitTotalProperty(): float
    {
        return array_reduce($this->splits, fn ($c, $row) => $c + $this->splitGross($row), 0.0);
    }

    /** Noch zu verteilender (Brutto-)Restbetrag bezogen auf den Umsatzbetrag. */
    public function getSplitRemainingProperty(): float
    {
        $total = abs((float) ($this->selectedTransaction?->amount ?? 0));

        return round($total - $this->splitTotal, 2);
    }

    /**
     * Schnelle Summen je USt-Satz über alle Split-Positionen: Netto-, USt- und
     * Brutto-Summe. Für den Abgleich mit der Rechnung (z. B. 19 % / 7 %).
     *
     * @return array<string, array{rate: float, net: float, tax: float, gross: float}>
     */
    public function getSplitTaxSummaryProperty(): array
    {
        $sum = [];
        foreach ($this->splits as $row) {
            $gross = $this->splitGross($row);
            if (abs($gross) < 0.005) {
                continue;
            }
            $rate = $this->parseAmount($row['tax_rate'] ?? '0');
            $net = $rate != 0.0 ? $gross / (1 + $rate / 100) : $gross;
            // Schlüssel ohne Nachkommastelle, z. B. "19", "7", "0".
            $key = rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',');

            $sum[$key]['rate'] = $rate;
            $sum[$key]['net'] = ($sum[$key]['net'] ?? 0) + $net;
            $sum[$key]['tax'] = ($sum[$key]['tax'] ?? 0) + ($gross - $net);
            $sum[$key]['gross'] = ($sum[$key]['gross'] ?? 0) + $gross;
        }
        uasort($sum, fn ($a, $b) => $b['rate'] <=> $a['rate']);

        return $sum;
    }

    /** Sachkonto-Treffer für die Suche einer Split-Zeile. */
    public function splitLedgerResults(string $search): Collection
    {
        $s = trim($search);
        if (mb_strlen($s) < 2) {
            return collect();
        }

        return LedgerAccount::query()
            ->whereIn('chart', ['edtas', 'kfz', 'gastro'])
            ->where(fn ($q) => $q->where('number', 'like', $s . '%')->orWhere('name', 'like', '%' . $s . '%'))
            ->orderBy('number')
            ->limit(15)
            ->get();
    }

    public function setSplitLedger(int $index, int $ledgerId): void
    {
        $la = LedgerAccount::find($ledgerId);
        if (! $la || ! isset($this->splits[$index])) {
            return;
        }
        $this->splits[$index]['ledger_account_id'] = $la->id;
        $this->splits[$index]['ledger_label'] = $la->number . ' – ' . $la->name;
        $this->splits[$index]['ledger_search'] = '';

        // Steuersatz des Kontos übernehmen, sofern hinterlegt (19/7/0).
        if ($la->tax_rate !== null) {
            $rate = (float) $la->tax_rate;
            $this->splits[$index]['tax_rate'] = rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',');
        }

        $this->autoSaveSplits();
    }

    public function clearSplitLedger(int $index): void
    {
        if (! isset($this->splits[$index])) {
            return;
        }
        $this->splits[$index]['ledger_account_id'] = null;
        $this->splits[$index]['ledger_label'] = '';
        $this->splits[$index]['ledger_search'] = '';
    }

    /**
     * Speichert die Aufteilung (ersetzt die bisherigen Positionen des
     * Umsatzes) – ohne Meldung. Gemeinsamer Kern für den manuellen
     * Speichern-Button und das Autospeichern.
     *
     * @param  bool  $reloadEditor  Editor-Felder ($this->splits) aus der Datenbank neu
     *                              befüllen (Beträge/USt normalisieren). Beim Autospeichern
     *                              MUSS das false sein, sonst wird der gerade getippte Wert
     *                              (z. B. ein noch unfertiger negativer Betrag) mitten in der
     *                              Eingabe überschrieben.
     * @return array{count: int, diff: float, ohneKonto: int, removed: bool}|null null, wenn kein Umsatz gewählt ist
     */
    private function persistSplits(bool $reloadEditor = true): ?array
    {
        $t = $this->selectedTransaction;
        if (! $t) {
            return null;
        }

        $rows = array_values(array_filter($this->splits, function ($row) {
            return ! empty($row['ledger_account_id'] ?? null)
                || $this->parseAmount($row['amount'] ?? '') != 0.0;
        }));

        if (empty($rows)) {
            $t->accountAssignments()->delete();
            // $t ist dasselbe Objekt, das die gecachte Livewire-Computed-Property
            // "selectedTransaction" liefert – die accountAssignments-Relation muss
            // hier hart neu geladen werden, sonst liest loadSplits() den alten Stand.
            $t->load('accountAssignments.ledgerAccount');
            if ($reloadEditor) {
                $this->loadSplits();
            }

            return ['count' => 0, 'diff' => 0.0, 'ohneKonto' => 0, 'removed' => true];
        }

        $chart = config('pendelordner.kontierung.standard_kontenrahmen', 'edtas');

        // Alte Positionen ersetzen. Kategorie/Kostenstelle vom Umsatz übernehmen,
        // Betrag immer als Brutto speichern (Summe = Umsatzbetrag).
        $t->accountAssignments()->delete();
        foreach ($rows as $row) {
            $t->accountAssignments()->create([
                'chart_of_accounts' => $chart,
                'category_id' => $t->category_id,
                'cost_center_id' => $t->cost_center_id,
                'ledger_account_id' => ($row['ledger_account_id'] ?? null) ?: null,
                'tax_rate' => ($row['tax_rate'] ?? '') !== '' ? $this->parseAmount($row['tax_rate']) : null,
                'amount' => $this->splitGross($row),
                'booking_date' => $t->booking_date,
            ]);
        }

        $t->load('accountAssignments.ledgerAccount');

        // Ist der Umsatz jetzt vollständig aufgeteilt (Rest 0), den Merker
        // "Aufteilung offen" automatisch entfernen.
        if ($t->split_open && abs($this->splitRemaining) < 0.005) {
            $t->update(['split_open' => false]);
            $this->splitOpen = false;
            unset($this->selectedTransaction);
        }

        if ($reloadEditor) {
            $this->loadSplits();
        }

        return [
            'count' => count($rows),
            'diff' => $this->splitRemaining,
            'ohneKonto' => count(array_filter($rows, fn ($row) => empty($row['ledger_account_id'] ?? null))),
            'removed' => false,
        ];
    }

    /** Aufteilung speichern (Button) – mit ausführlicher Erfolg-/Warn-Meldung. */
    public function saveSplits(): void
    {
        $result = $this->persistSplits();
        if ($result === null) {
            return;
        }

        if ($result['removed']) {
            $this->showSplit = false; // nichts mehr aufzuteilen -> Karte zuklappen
            Notification::make()->title('Aufteilung entfernt')->success()->send();

            return;
        }

        $sauber = abs($result['diff']) < 0.005 && $result['ohneKonto'] === 0;

        $hints = [];
        $hints[] = abs($result['diff']) < 0.005
            ? 'Betrag vollständig auf Sachkonten aufgeteilt.'
            : 'Achtung: Restbetrag ' . number_format($result['diff'], 2, ',', '.') . ' € nicht zugeordnet.';
        if ($result['ohneKonto'] > 0) {
            $hints[] = 'Achtung: ' . $result['ohneKonto'] . ' Position(en) OHNE Sachkonto – bitte je Zeile ein Konto wählen.';
        }

        // Bei sauberer Aufteilung (Rest 0, alle Konten gesetzt) die Karte wieder
        // zuklappen. Bei einer Warnung bleibt sie offen, damit der Fehler
        // (Restbetrag / fehlendes Konto) sichtbar bleibt und korrigiert wird.
        if ($sauber) {
            $this->showSplit = false;
        }

        Notification::make()
            ->title('Aufteilung gespeichert (' . $result['count'] . ' Positionen)')
            ->body(implode(' ', $hints))
            ->{$sauber ? 'success' : 'warning'}()->send();
    }

    /**
     * Speichert die Aufteilung automatisch und STILL im Hintergrund (nach
     * Betrags-/USt-Änderung, Kontoauswahl, Zeile hinzufügen/entfernen).
     *
     * Bewusst OHNE Meldung und OHNE Editor-Reload:
     *  - Kein loadSplits(): sonst würde der gerade getippte Wert (etwa ein noch
     *    unfertiger negativer Betrag) mitten in der Eingabe aus der Datenbank
     *    überschrieben und z. B. auf 0 zurückgesetzt.
     *  - Keine Toast-Meldung: der offene Restbetrag und Positionen ohne Konto
     *    sind bereits dauerhaft unten im Editor sichtbar; ein Toast bei jedem
     *    Tastendruck wäre nur störend. Die ausführliche Rückmeldung gibt der
     *    manuelle Button "Aufteilung speichern".
     */
    private function autoSaveSplits(): void
    {
        if (! $this->showSplit) {
            return;
        }

        $this->persistSplits(reloadEditor: false);
    }

    /**
     * Livewire-Hook: reagiert auf Änderungen einzelner Split-Felder
     * (Betrag/USt-Satz kommen client-seitig bereits entprellt an) und speichert
     * automatisch. Die Konto-Suchtext-Eingabe (ledger_search) löst kein
     * Autospeichern aus – sie ist noch keine gültige Zuordnung.
     */
    public function updated(string $name): void
    {
        if (preg_match('/^splits\.\d+\.(amount|tax_rate)$/', $name)) {
            $this->autoSaveSplits();
        }
    }

    /** Netto/Brutto-Modus umschalten – Beträge bedeuten dann etwas anderes, daher direkt neu speichern. */
    public function setSplitMode(string $mode): void
    {
        $this->splitMode = $mode;
        $this->autoSaveSplits();
    }

    private function parseAmount(mixed $value): float
    {
        // Deutsches Format: Tausenderpunkt entfernen, Komma -> Dezimalpunkt.
        $s = str_replace([' ', '.'], '', (string) $value);
        $s = str_replace(',', '.', $s);

        // Summen-Eingabe: mehrere Beträge mit + / - werden addiert, z. B.
        // "565,08+655,77+774,91" -> 1995,76. Ein einzelner Wert bleibt wie bisher.
        if (preg_match_all('/[-+]?\d+(?:\.\d+)?/', $s, $m) && count($m[0]) > 1) {
            return round(array_sum(array_map('floatval', $m[0])), 2);
        }

        return (float) $s;
    }

    /**
     * Formatierte Brutto-Summe einer Split-Zeile – für die Anzeige „= X €"
     * neben dem Betragsfeld, wenn dort eine Summe (mit +) eingegeben wurde.
     */
    public function splitRowSum(int $index): string
    {
        $row = $this->splits[$index] ?? null;

        return $row ? number_format($this->splitGross($row), 2, ',', '.') : '0,00';
    }

    public function toggleNote(): void
    {
        $this->showNote = ! $this->showNote;
    }

    /**
     * Merker "Aufteilung noch offen" umschalten. Der Umsatz kann so als
     * geprüft/bezahlt in den Bericht, bleibt aber im Dashboard-Widget
     * "Offene Aufteilungen" sichtbar, bis die Aufteilung ergänzt ist.
     */
    public function toggleSplitOpen(): void
    {
        if (! $this->selectedTransactionId) {
            return;
        }

        $this->splitOpen = ! $this->splitOpen;
        BankTransaction::whereKey($this->selectedTransactionId)->update(['split_open' => $this->splitOpen]);
        unset($this->selectedTransaction);

        Notification::make()
            ->title($this->splitOpen ? 'Als „Aufteilung offen" gemerkt' : 'Merker entfernt')
            ->body($this->splitOpen ? 'Erscheint im Dashboard unter „Offene Aufteilungen".' : null)
            ->success()->send();
    }

    /** Mitteilung an den Steuerberater am aktuellen Umsatz speichern. */
    public function saveNote(): void
    {
        if (! $this->selectedTransactionId) {
            return;
        }

        $note = trim($this->accountantNote);
        // Ohne Hinweistext gibt es auch keinen offenen Hinweis.
        $open = $note !== '' && $this->noteOpen;
        BankTransaction::whereKey($this->selectedTransactionId)
            ->update(['accountant_note' => $note !== '' ? $note : null, 'note_open' => $open]);

        // Glocke synchron halten (Meldung anlegen bzw. entfernen).
        if ($t = BankTransaction::find($this->selectedTransactionId)) {
            \App\Support\OffeneHinweisGlocke::sync($t);
        }

        $this->accountantNote = $note;
        $this->noteOpen = $open;
        $this->showNote = $note !== '';

        Notification::make()
            ->title($note !== '' ? 'Mitteilung gespeichert' : 'Mitteilung entfernt')
            ->body($open ? 'Als offener Hinweis im Dashboard vorgemerkt.' : null)
            ->success()->send();
    }

    /** Speichert ein einzelnes Zuordnungsfeld am aktuellen Umsatz. */
    private function saveAssign(string $field, $value): void
    {
        if (! $this->selectedTransactionId) {
            return;
        }
        BankTransaction::whereKey($this->selectedTransactionId)->update([$field => $value ?: null]);

        Notification::make()->title('Zuordnung gespeichert')->success()->send();
    }

    public function updatedAssignCategoryId($value): void
    {
        $this->saveAssign('category_id', $value);
    }

    /**
     * Treffer der durchsuchbaren Kategorie-Auswahl. Findet per Kategoriename
     * sowie per eDTAS-Konto (Nummer oder Bezeichnung). Ohne Suchbegriff werden
     * alle aktiven Kategorien angezeigt (wie ein filterbares Aufklappmenü).
     *
     * @return Collection<int, Category>
     */
    public function getCategoryResultsProperty(): Collection
    {
        $s = trim($this->categorySearch);

        $query = Category::where('active', true);

        if ($s !== '') {
            // eDTAS-Konten, deren Nummer/Bezeichnung passt – deren Nummern dienen
            // als zusätzliches Suchkriterium für die Kategorie.
            $edtasNumbers = LedgerAccount::whereIn('chart', ['edtas', 'kfz', 'gastro'])
                ->where(fn ($q) => $q->where('number', 'like', $s . '%')->orWhere('name', 'like', '%' . $s . '%'))
                ->pluck('number')->all();

            $query->where(function ($q) use ($s, $edtasNumbers) {
                $q->where('name', 'like', '%' . $s . '%')
                    ->orWhere('edtas_account', 'like', $s . '%');
                if (! empty($edtasNumbers)) {
                    $q->orWhereIn('edtas_account', $edtasNumbers);
                }
            });
        }

        return $query->orderBy('name')->limit(25)->get();
    }

    /** Die aktuell gewählte Kategorie (für die Badge-Anzeige). */
    public function getCurrentCategoryProperty(): ?Category
    {
        return $this->assignCategoryId ? Category::find($this->assignCategoryId) : null;
    }

    /**
     * Zusätzliche Vorschläge aus dem eDTAS-Kontenrahmen (Nummer oder Text),
     * für die es noch keine Kategorie gibt – direkt findbar und übernehmbar.
     *
     * @return Collection<int, LedgerAccount>
     */
    public function getEdtasResultsProperty(): Collection
    {
        $s = trim($this->categorySearch);
        if (mb_strlen($s) < 2) {
            return collect();
        }

        // eDTAS-Nummern, die bereits einer Kategorie zugeordnet sind, ausblenden.
        $existing = Category::whereNotNull('edtas_account')->pluck('edtas_account')->all();

        return LedgerAccount::whereIn('chart', ['edtas', 'kfz', 'gastro'])
            ->when($existing, fn ($q) => $q->whereNotIn('number', $existing))
            ->where(fn ($q) => $q->where('number', 'like', $s . '%')->orWhere('name', 'like', '%' . $s . '%'))
            ->orderBy('number')
            ->limit(10)
            ->get();
    }

    public function setCategory(int $id): void
    {
        $this->assignCategoryId = $id;
        $this->categorySearch = '';
        $this->editingCategory = false;
        $this->saveAssign('category_id', $id);
    }

    /**
     * Übernimmt ein eDTAS-Konto als Kategorie: legt (oder findet) eine Kategorie
     * mit dieser eDTAS-Zuordnung an und weist sie dem Umsatz zu.
     */
    public function setCategoryFromEdtas(int $ledgerId): void
    {
        $la = LedgerAccount::whereIn('chart', ['edtas', 'kfz', 'gastro'])->find($ledgerId);
        if (! $la) {
            return;
        }

        $category = Category::firstOrCreate(
            ['edtas_account' => $la->number],
            ['name' => $la->name, 'active' => true],
        );

        $this->setCategory($category->id);

        Notification::make()->title('Kategorie „' . $category->name . '" (eDTAS ' . $la->number . ') zugeordnet')->success()->send();
    }

    public function editCategory(): void
    {
        $this->editingCategory = true;
        $this->categorySearch = '';
    }

    public function clearCategory(): void
    {
        $this->assignCategoryId = null;
        $this->categorySearch = '';
        $this->editingCategory = false;
        $this->saveAssign('category_id', null);
    }

    /**
     * eDTAS-Konto der gewählten Kategorie (für die Steuerberater-Auswertung).
     *
     * @return ?array{number: string, name: ?string}
     */
    public function getCategoryLedgerProperty(): ?array
    {
        $category = $this->assignCategoryId ? Category::find($this->assignCategoryId) : null;
        if (! $category || ! $category->edtas_account) {
            return null;
        }

        $la = LedgerAccount::whereIn('chart', ['edtas', 'kfz', 'gastro'])
            ->where('number', $category->edtas_account)->first();

        return ['number' => (string) $category->edtas_account, 'name' => $la?->name];
    }

    public function updatedAssignCostCenterId($value): void
    {
        $this->saveAssign('cost_center_id', $value);
    }

    public function toggleNewCategory(): void
    {
        $this->showNewCategory = ! $this->showNewCategory;
        $this->newCategory = '';
    }

    /** Neue Kategorie anlegen, dem Umsatz zuordnen und auswählen. */
    public function createCategory(): void
    {
        $name = trim($this->newCategory);
        if ($name === '') {
            return;
        }

        $category = Category::firstOrCreate(['name' => $name], ['active' => true]);

        $this->assignCategoryId = $category->id;
        $this->saveAssign('category_id', $category->id);

        $this->newCategory = '';
        $this->showNewCategory = false;
        $this->categorySearch = '';
        $this->editingCategory = false;

        Notification::make()->title('Kategorie „' . $category->name . '" angelegt')->success()->send();
    }

    public function toggleRuleForm(): void
    {
        $this->showRuleForm = ! $this->showRuleForm;

        if ($this->showRuleForm) {
            // Muster sinnvoll vorbelegen: Empfänger, sonst IBAN.
            $t = $this->selectedTransaction;
            $this->rulePattern = trim((string) ($t?->counterparty ?: $t?->counterparty_iban ?: ''));
            $this->rulePatternType = $t?->counterparty ? 'counterparty' : ($t?->counterparty_iban ? 'iban' : 'counterparty');
            // Zweites Kriterium bleibt leer (optional); Standardfeld Verwendungszweck.
            $this->rulePattern2 = '';
            $this->rulePatternType2 = 'purpose';
            $this->ruleApplyExisting = true;
        }
    }

    /**
     * Erstellt aus dem aktuellen Umsatz eine Zuordnungsregel für
     * wiederkehrende Buchungen und wendet sie optional auf vorhandene
     * (ungeprüfte) Umsätze an.
     */
    public function createRule(): void
    {
        $t = $this->selectedTransaction;
        if (! $t) {
            return;
        }

        $pattern = trim($this->rulePattern);
        if ($pattern === '') {
            Notification::make()->title('Bitte ein Muster angeben')->warning()->send();

            return;
        }

        if (! $t->category_id && ! $t->cost_center_id && ! $t->ledger_account_id) {
            Notification::make()
                ->title('Keine Zuordnung vorhanden')
                ->body('Bitte zuerst Kategorie, Kostenstelle oder Konto am Umsatz setzen.')
                ->warning()->send();

            return;
        }

        $type = in_array($this->rulePatternType, ['counterparty', 'purpose', 'iban', 'any', 'amount'], true)
            ? $this->rulePatternType : 'counterparty';

        // Optionales zweites Kriterium (UND). Nur speichern, wenn ausgefüllt.
        $pattern2 = trim($this->rulePattern2);
        $type2 = in_array($this->rulePatternType2, ['counterparty', 'purpose', 'iban', 'any', 'amount'], true)
            ? $this->rulePatternType2 : 'purpose';

        $rule = MatchingRule::create([
            'pattern' => $pattern,
            'pattern_type' => $type,
            'pattern2' => $pattern2 !== '' ? $pattern2 : null,
            'pattern_type2' => $pattern2 !== '' ? $type2 : null,
            'category_id' => $t->category_id,
            'cost_center_id' => $t->cost_center_id,
            'ledger_account_id' => $t->ledger_account_id,
            'business_id' => $t->business_id,
            'priority' => 10,
            'active' => true,
        ]);

        $applied = 0;
        if ($this->ruleApplyExisting) {
            $applied = (new MatchingEngine())->applyRuleToExisting($rule, onlyUnreviewed: true);
        }

        $this->showRuleForm = false;

        Notification::make()
            ->title('Regel erstellt')
            ->body($this->ruleApplyExisting
                ? 'Auf ' . $applied . ' weitere(n) Umsatz/Umsätze angewendet.'
                : 'Regel gespeichert.')
            ->success()->send();
    }

    /** @return Collection<int, Category> */
    public function getCategoriesProperty(): Collection
    {
        return Category::where('active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, CostCenter> */
    public function getCostCentersProperty(): Collection
    {
        return CostCenter::where('active', true)->orderBy('name')->get();
    }

    /** Alle Sachkonten (Kontenrahmen) für die Auswahl je Split-Position. */
    public function getLedgerAccountsProperty(): Collection
    {
        return LedgerAccount::orderBy('number')->get(['id', 'number', 'name']);
    }

    /**
     * Treffer für die operative Sachkonto-Suche (Nummer oder Bezeichnung).
     * Nur die operativen eDTAS-Konten (die Kategorie-
     * Zuordnung (Steuerberater), nicht der operativen Buchung (edtas/gastro/kfz).
     */
    public function getLedgerResultsProperty(): Collection
    {
        $s = trim($this->ledgerSearch);
        if (mb_strlen($s) < 2) {
            return collect();
        }

        return LedgerAccount::query()
            ->whereIn('chart', ['edtas', 'kfz', 'gastro'])
            ->where(fn ($q) => $q->where('number', 'like', $s . '%')->orWhere('name', 'like', '%' . $s . '%'))
            ->orderBy('number')
            ->limit(15)
            ->get();
    }

    public function getCurrentLedgerProperty(): ?LedgerAccount
    {
        return $this->assignLedgerAccountId ? LedgerAccount::find($this->assignLedgerAccountId) : null;
    }

    public function setLedger(int $id): void
    {
        $this->assignLedgerAccountId = $id;
        $this->ledgerSearch = '';
        $this->saveAssign('ledger_account_id', $id);
    }

    public function clearLedger(): void
    {
        $this->assignLedgerAccountId = null;
        $this->saveAssign('ledger_account_id', null);
    }

    /** Basisquery der Navigation – gefiltert (aus der Liste) oder Standard (offene Ausgaben). */
    private function navigationQuery()
    {
        // Auswahl-Menge hat Vorrang: nur die ausgewählten Umsätze durchblättern.
        if (! empty($this->navIds)) {
            return BankTransaction::query()
                ->whereIn('id', $this->navIds)
                ->orderBy('booking_date')->orderBy('id');
        }

        if ($this->hasFilterContext()) {
            return BankTransaction::query()
                ->when($this->filterAccountId, fn ($q) => $q->where('bank_account_id', $this->filterAccountId))
                ->when($this->filterBusinessId, fn ($q) => $q->where('business_id', $this->filterBusinessId))
                ->when($this->filterCategoryId, fn ($q) => $q->where('category_id', $this->filterCategoryId))
                ->when($this->filterCostCenterId, fn ($q) => $q->where('cost_center_id', $this->filterCostCenterId))
                ->when($this->filterFrom, fn ($q) => $q->whereDate('booking_date', '>=', $this->filterFrom))
                ->when($this->filterTo, fn ($q) => $q->whereDate('booking_date', '<=', $this->filterTo))
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                // Standardmäßig nur ungeprüfte Umsätze; nur wenn aus der Liste
                // explizit nach "geprüft" gefiltert wurde, diesen Filter nutzen.
                ->when($this->filterReviewed !== null,
                    fn ($q) => $q->where('reviewed', $this->filterReviewed === '1'),
                    fn ($q) => $q->where('reviewed', false))
                ->when($this->filterWithoutReceipt !== null, fn ($q) => $this->filterWithoutReceipt === '1'
                    ? $q->whereDoesntHave('receipts')
                    : $q->whereHas('receipts'))
                // id als stabiles zweites Sortierkriterium -> feste Reihenfolge.
                ->orderBy('booking_date')->orderBy('id');
        }

        // In der Bearbeitung werden vorrangig ungeprüfte Umsätze angezeigt.
        $offene = BankTransaction::query()->where('reviewed', false)
            ->orderBy('booking_date')->orderBy('id');

        // Sind alle Umsätze bereits geprüft, wäre die Seite leer – dann zur
        // besseren Übersicht alle Umsätze (neueste zuerst) anzeigen.
        if (! (clone $offene)->exists()) {
            return BankTransaction::query()->orderByDesc('booking_date')->orderByDesc('id');
        }

        return $offene;
    }

    private function hasFilterContext(): bool
    {
        return $this->filterAccountId || $this->filterBusinessId || $this->filterCategoryId
            || $this->filterCostCenterId || $this->filterFrom || $this->filterTo
            || $this->filterStatus || $this->filterReviewed !== null || $this->filterWithoutReceipt !== null;
    }

    /** Seite über die volle Panelbreite anzeigen. */
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    // --- Navigation (Umsatz X von Y, vor/zurück) ----------------------------

    /**
     * Grundlage des Zählers „Kontosatz X von Y": standardmäßig nur die noch
     * NICHT geprüften Umsätze. Nur wenn aus der Liste explizit nach
     * „geprüft" gefiltert wurde, zählt die ganze (geprüfte) Liste.
     *
     * @return list<int>
     */
    private function counterIds(): array
    {
        // Bei einer Auswahl-Menge zählen alle ausgewählten Umsätze (unabhängig
        // vom Prüf-Status – sie sollen ja bewusst nacheinander bearbeitet werden).
        if (! empty($this->navIds)) {
            return $this->openTransactions->pluck('id')->values()->all();
        }

        $list = $this->openTransactions;
        if ($this->filterReviewed !== '1') {
            $list = $list->where('reviewed', false);
        }

        return $list->pluck('id')->values()->all();
    }

    public function getPositionProperty(): int
    {
        $i = array_search($this->selectedTransactionId, $this->counterIds(), true);

        return $i === false ? 0 : $i + 1;
    }

    public function getTotalProperty(): int
    {
        return count($this->counterIds());
    }

    public function goTo(string $where): void
    {
        $ids = $this->openTransactions->pluck('id')->all();
        if (empty($ids)) {
            return;
        }
        $i = array_search($this->selectedTransactionId, $ids, true);
        $i = $i === false ? 0 : $i;

        $target = match ($where) {
            'first' => $ids[0],
            'last' => $ids[count($ids) - 1],
            'prev' => $ids[max(0, $i - 1)],
            'next' => $ids[min(count($ids) - 1, $i + 1)],
            default => $ids[$i],
        };

        $this->selectTransaction($target);
    }

    // --- Daten ---------------------------------------------------------------

    public function getOpenTransactionsProperty(): Collection
    {
        $list = $this->navigationQuery()
            ->with(['receipts'])
            ->limit(500)
            ->get();

        // Den aktuell gewählten Umsatz sicher enthalten (z. B. wenn er nach
        // "geprüft" aus der Standardliste fiele), damit die Position stimmt.
        if ($this->selectedTransactionId && ! $list->contains('id', $this->selectedTransactionId)) {
            if ($sel = BankTransaction::with('receipts')->find($this->selectedTransactionId)) {
                // Stabil nach Datum + id einsortieren (gleiche Ordnung wie die Query).
                $list = $list->push($sel)
                    ->sortBy(fn (BankTransaction $t) => sprintf('%s-%012d', $t->booking_date?->format('Y-m-d'), $t->id))
                    ->values();
            }
        }

        return $list;
    }

    public function getSelectedTransactionProperty(): ?BankTransaction
    {
        if (! $this->selectedTransactionId) {
            return null;
        }

        return BankTransaction::with(['receipts', 'category', 'costCenter', 'ledgerAccount', 'supplier', 'bankAccount', 'accountAssignments.ledgerAccount'])
            ->find($this->selectedTransactionId);
    }

    public function getSelectedReceiptProperty(): ?Receipt
    {
        if ($this->selectedReceiptId) {
            if ($r = Receipt::find($this->selectedReceiptId)) {
                return $r;
            }
        }

        // Fällt automatisch auf den ersten zugeordneten Beleg zurück, damit die
        // Vorschau auch ohne Klick erscheint.
        if ($first = $this->selectedTransaction?->receipts->first()) {
            return $first;
        }

        // Auf dem Vorschläge-Tab ohne zugeordneten Beleg den besten Vorschlag
        // direkt in der Vorschau zeigen.
        if ($this->activeTab === 'suggestions') {
            return $this->suggestions->first()['receipt'] ?? null;
        }

        return null;
    }

    public function getSuggestionsProperty(): Collection
    {
        $transaction = $this->selectedTransaction;

        return $transaction ? (new MatchingEngine())->suggestReceipts($transaction, 20) : collect();
    }

    /**
     * Sammel-/Avis-Vorschlag: mehrere Einzelrechnungen, deren Nummern auf einem
     * Zahlungsavis stehen und deren Summe dem Umsatzbetrag entspricht.
     *
     * @return array{advice: Receipt, invoices: Collection<int, Receipt>, sum: float}|null
     */
    public function getAdviceSuggestionProperty(): ?array
    {
        $transaction = $this->selectedTransaction;

        return $transaction ? (new MatchingEngine())->suggestFromAdvice($transaction) : null;
    }

    /** Alle vom Avis referenzierten Einzelrechnungen auf einmal zuordnen. */
    public function attachAdviceInvoices(): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }

        $suggestion = (new MatchingEngine())->suggestFromAdvice($transaction);
        if (! $suggestion) {
            Notification::make()->title('Kein Zahlungsavis erkannt')->warning()->send();

            return;
        }

        $amounts = $suggestion['amounts'] ?? [];
        foreach ($suggestion['invoices'] as $receipt) {
            // Maßgeblich ist der aus der Avis-Zeile gelesene Betrag (mit
            // Vorzeichen); nur ersatzweise der Belegbetrag.
            $applied = $amounts[$receipt->id] ?? round((float) $receipt->gross_amount, 2);
            $transaction->receipts()->syncWithoutDetaching([
                $receipt->id => [
                    'amount' => round((float) $applied, 2),
                    'match_type' => 'advice',
                    'sort_order' => $transaction->receipts()->count(),
                ],
            ]);
            (new \App\Services\Matching\SupplierDefaults())->applyToTransaction($transaction, $receipt);
        }

        $transaction->recalculateStatus();
        $this->fillAssign();
        $this->activeTab = 'assigned';
        $this->refreshSelected();

        Notification::make()
            ->title($suggestion['invoices']->count() . ' Rechnungen aus Zahlungsavis zugeordnet')
            ->body('Summe ' . number_format($suggestion['sum'], 2, ',', '.') . ' €')
            ->success()->send();
    }

    /** Ergebnisse der manuellen Belegsuche. */
    public function getSearchResultsProperty(): Collection
    {
        return Receipt::query()
            ->with('supplier')
            ->notDuplicate()
            ->when($this->searchAssigned === 'unassigned', fn ($q) => $q->whereDoesntHave('bankTransactions'))
            ->when($this->searchAssigned === 'assigned', fn ($q) => $q->whereHas('bankTransactions'))
            ->when($this->searchPaid === 'paid', fn ($q) => $q->where('paid', true))
            ->when($this->searchPaid === 'unpaid', fn ($q) => $q->where('paid', false))
            ->when($this->searchType !== 'all', fn ($q) => $q->where('type', $this->searchType))
            ->when($this->searchQuery !== '', function ($q) {
                $s = '%' . $this->searchQuery . '%';
                $q->where(function ($q) use ($s) {
                    $q->where('invoice_number', 'like', $s)
                        ->orWhere('ocr_text', 'like', $s)
                        ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', $s));
                });
            })
            ->orderByDesc('invoice_date')
            ->limit(50)
            ->get();
    }

    // --- Aktionen ------------------------------------------------------------

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function selectTransaction(int $id): void
    {
        // Offene Eingaben des bisherigen Umsatzes sichern, bevor gewechselt wird.
        $this->persistPending();

        $this->selectedTransactionId = $id;
        $this->selectedReceiptId = $this->selectedTransaction?->receipts->first()?->id;
        $this->activeTab = 'assigned';
        $this->fillAssign();
    }

    public function selectReceipt(int $id): void
    {
        $this->selectedReceiptId = $id;
    }

    public function attachReceipt(int $receiptId): void
    {
        $transaction = $this->selectedTransaction;
        $receipt = Receipt::find($receiptId);
        if (! $transaction || ! $receipt) {
            return;
        }

        // Eigenen (offenen) Belegbetrag MIT Vorzeichen übernehmen – nicht auf den
        // Umsatz-Restbetrag kappen. Bei Sammelzahlungen/Avisen kann eine einzelne
        // Zeile den Netto-Umsatz deutlich übersteigen (Gutschrift gegen Soll), und
        // ein negativer Belegbetrag darf nicht auf den positiven Netto gedreht
        // werden. Nur wenn der Beleg gar keinen Betrag hat, den Restbetrag setzen.
        $open = round((float) $receipt->open_amount, 2);
        $amount = abs($open) >= 0.005 ? $open : round((float) $receipt->gross_amount, 2);
        if (abs($amount) < 0.005) {
            $amount = round(abs((float) $transaction->amount), 2);
        }

        $transaction->receipts()->syncWithoutDetaching([
            $receipt->id => ['amount' => round($amount, 2), 'match_type' => 'confirmed', 'sort_order' => $transaction->receipts()->count()],
        ]);
        // Lieferanten-Defaults (je Tankstelle/Kundennummer) auf leere Felder anwenden.
        (new \App\Services\Matching\SupplierDefaults())->applyToTransaction($transaction, $receipt);
        $transaction->recalculateStatus();
        $this->fillAssign();

        $this->selectedReceiptId = $receipt->id;
        $this->activeTab = 'assigned';
        $this->refreshSelected();

        Notification::make()->title('Beleg zugeordnet')->success()->send();
    }

    /** Neue Reihenfolge der zugeordneten Belege per Drag & Drop speichern. */
    public function reorderReceipts(array $orderedIds): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }

        $valid = $transaction->receipts->pluck('id')->all();
        $pos = 0;
        foreach ($orderedIds as $rid) {
            $rid = (int) $rid;
            if (in_array($rid, $valid, true)) {
                $transaction->receipts()->updateExistingPivot($rid, ['sort_order' => $pos++]);
            }
        }

        $this->refreshSelected();
    }

    /** Verschiebt einen zugeordneten Beleg in der Reihenfolge (hoch/runter). */
    public function moveReceipt(int $receiptId, string $direction): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }

        // Aktuelle Reihenfolge (bereits nach sort_order, dann id) als Id-Liste.
        $ids = $transaction->receipts->pluck('id')->all();
        $i = array_search($receiptId, $ids, true);
        if ($i === false) {
            return;
        }
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($j < 0 || $j >= count($ids)) {
            return;
        }

        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];

        // Reihenfolge neu durchnummerieren.
        foreach ($ids as $pos => $rid) {
            $transaction->receipts()->updateExistingPivot($rid, ['sort_order' => $pos]);
        }

        $this->refreshSelected();
    }

    /** Zugeordneten (Teil-)Betrag eines Belegs ändern. */
    public function updateAllocation(int $receiptId, $amount): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }

        $value = round((float) str_replace(',', '.', (string) $amount), 2);

        $transaction->receipts()->updateExistingPivot($receiptId, ['amount' => $value]);
        $transaction->recalculateStatus();
        $this->refreshSelected();

        Notification::make()->title('Betrag aktualisiert')->success()->send();
    }

    /** Steuert, ob die Belegdatei im Steuerberater-Bericht angehängt wird. */
    public function toggleReceiptInReport(int $receiptId): void
    {
        $receipt = Receipt::find($receiptId);
        if (! $receipt) {
            return;
        }

        $receipt->include_in_report = ! $receipt->include_in_report;
        $receipt->saveQuietly();
        $this->refreshSelected();

        Notification::make()
            ->title($receipt->include_in_report
                ? 'Beleg wird im Bericht angehängt'
                : 'Beleg wird nicht im Bericht angehängt')
            ->success()->send();
    }

    public function detachReceipt(int $receiptId): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }

        $transaction->receipts()->detach($receiptId);
        $transaction->recalculateStatus();
        $this->fillAssign();
        $this->refreshSelected();

        Notification::make()->title('Zuordnung gelöst')->success()->send();
    }

    /**
     * Prüf-Status umschalten: nicht geprüft -> geprüft und wieder zurück.
     * Beim Zurücknehmen berechnet recalculateStatus() den Status neu (offen /
     * teilweise / vollständig zugeordnet – je nach Belegen).
     */
    public function markReviewed(): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }

        // Mitteilung direkt am Modell sichern (recalculateStatus speichert mit).
        $transaction->accountant_note = trim($this->accountantNote) ?: null;
        $transaction->reviewed = ! $transaction->reviewed;
        $transaction->recalculateStatus();
        $this->refreshSelected();

        Notification::make()
            ->title($transaction->reviewed ? 'Umsatz als geprüft markiert' : 'Prüfung zurückgenommen – Status wieder offen')
            ->success()->send();
    }

    public function togglePaid(): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }
        // Mitteilung mitsichern, falls sie noch nicht gespeichert wurde.
        $transaction->accountant_note = trim($this->accountantNote) ?: null;
        $transaction->fully_paid = ! $transaction->fully_paid;
        $transaction->saveQuietly();
        $this->refreshSelected();
    }

    /** Beleg hochladen (Upload online), OCR ausführen und dem Umsatz zuordnen. */
    public function uploadReceipt(): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            Notification::make()->title('Kein Umsatz gewählt')->warning()->send();

            return;
        }

        $this->validate([
            'uploadFiles' => 'required|array',
            'uploadFiles.*' => 'file|max:20480', // max. 20 MB je Datei
        ], [], ['uploadFiles' => 'Dateien']);

        $diskName = config('pendelordner.belege_disk', 'belege');
        $count = 0;
        $lastId = null;

        foreach ($this->uploadFiles as $file) {
            try {
                // Dublettenprüfung: existiert der Beleg schon, den vorhandenen
                // verwenden (statt eine zweite Kopie anzulegen).
                $hash = hash('sha256', $file->get());
                $receipt = Receipt::where('file_hash', $hash)->first();

                if (! $receipt) {
                    $path = $file->store(date('Y/m'), $diskName);

                    $receipt = Receipt::create([
                        'type' => $this->uploadType,
                        'business_id' => $transaction->business_id,
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'file_hash' => $hash,
                        'status' => 'new',
                    ]);

                    // OCR ausführen (füllt Rechnungsnummer, Datum, Beträge …)
                    (new OcrService())->process($receipt->refresh());
                }
                $receipt->refresh();

                // Restbetrag aktuell halten (mehrere Belege je Umsatz).
                $transaction->load('receipts');

                $amount = $receipt->gross_amount > 0
                    ? min((float) $receipt->gross_amount, abs((float) $transaction->amount))
                    : ($transaction->difference > 0 ? $transaction->difference : abs((float) $transaction->amount));

                $transaction->receipts()->syncWithoutDetaching([
                    $receipt->id => ['amount' => round($amount, 2), 'match_type' => 'manual', 'sort_order' => $transaction->receipts()->count()],
                ]);
                // Lieferanten-Defaults (je Tankstelle/Kundennummer) anwenden.
                (new \App\Services\Matching\SupplierDefaults())->applyToTransaction($transaction, $receipt);

                $lastId = $receipt->id;
                $count++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $transaction->recalculateStatus();
        $this->fillAssign();

        $this->reset('uploadFiles');
        if ($lastId) {
            $this->selectedReceiptId = $lastId;
        }
        $this->activeTab = 'assigned';
        $this->refreshSelected();

        Notification::make()->title($count . ' Beleg(e) hochgeladen & zugeordnet')->success()->send();
    }
}
