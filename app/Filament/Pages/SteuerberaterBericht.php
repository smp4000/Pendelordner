<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BankTransactions\Tables\BankTransactionsTable;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Services\Pdf\PdfReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;
use ZipArchive;

/**
 * Erzeugt den Steuerberater-Pendelordner als PDF (Modul 12) für einen
 * gewählten Zeitraum (Presets oder individuell) und optional einen Betrieb.
 */
class SteuerberaterBericht extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.steuerberater-bericht';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Auswertungen';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Steuerberater-Bericht';

    protected static ?string $navigationLabel = 'Steuerberater-Bericht';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'period' => 'this_month',
            'business_id' => null,
            'bank_account_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('period')
                    ->label('Zeitraum')
                    ->options([
                        'last_7' => 'Letzte 7 Tage',
                        'last_30' => 'Letzte 30 Tage',
                        'last_3m' => 'Letzte 3 Monate',
                        'last_6m' => 'Letzte 6 Monate',
                        'last_12m' => 'Letzte 12 Monate',
                        'this_month' => 'Aktueller Monat',
                        'this_quarter' => 'Aktuelles Quartal',
                        'this_halfyear' => 'Aktuelles Halbjahr',
                        'this_year' => 'Aktuelles Jahr',
                        'last_month' => 'Vormonat',
                        'custom' => 'Individueller Zeitraum',
                    ])
                    ->default('this_month')
                    ->live()
                    ->required(),
                Select::make('business_id')
                    ->label('Betrieb')
                    ->placeholder('Alle Betriebe')
                    ->options(Business::orderBy('sort_order')->get()->pluck('display_label', 'id'))
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('bank_account_id', null)),
                Select::make('bank_account_id')
                    ->label('Bankkonto')
                    ->placeholder('Alle Konten')
                    ->options(fn (callable $get) => BankAccount::query()
                        ->when($get('business_id'), fn ($q, $b) => $q->where('business_id', $b))
                        ->orderBy('label')
                        ->get()
                        ->mapWithKeys(fn (BankAccount $a) => [$a->id => $a->display_name])),
                DatePicker::make('from')->label('Von')->native(false)->displayFormat('d.m.Y')
                    ->visible(fn (callable $get) => $get('period') === 'custom')
                    ->required(fn (callable $get) => $get('period') === 'custom'),
                DatePicker::make('until')->label('Bis')->native(false)->displayFormat('d.m.Y')
                    ->visible(fn (callable $get) => $get('period') === 'custom')
                    ->required(fn (callable $get) => $get('period') === 'custom'),
            ])
            ->statePath('data');
    }

    public function generateAction(): Action
    {
        return Action::make('generate')
            ->label('Bericht erzeugen & herunterladen')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function () {
                $state = $this->form->getState();
                [$from, $to] = $this->resolvePeriod($state);

                $business = $state['business_id'] ? Business::find($state['business_id']) : null;
                $account = $state['bank_account_id'] ? BankAccount::find($state['bank_account_id']) : null;

                $path = (new PdfReportService())->generate($from, $to, $business, $account);

                return Storage::disk('local')->download($path);
            });
    }

    /**
     * Erzeugt für jedes (zum Filter passende) Konto einen eigenen Bericht und
     * bündelt sie als ZIP – getrennte Pendelordner je Bankkonto.
     */
    public function generatePerAccountAction(): Action
    {
        return Action::make('generatePerAccount')
            ->label('Pro Konto je PDF (ZIP)')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->color('gray')
            ->action(function () {
                $state = $this->form->getState();
                [$from, $to] = $this->resolvePeriod($state);

                $business = $state['business_id'] ? Business::find($state['business_id']) : null;

                $accounts = BankAccount::query()
                    ->when($business, fn ($q) => $q->where('business_id', $business->id))
                    ->orderBy('label')
                    ->get();

                $service = new PdfReportService();
                $disk = Storage::disk('local');

                // Je Konto: Ordner mit Übersicht (ohne Belege), Komplett-PDF
                // (mit Belegen) und den Einzel-Belegen (Jahr-Monat-Nr.).
                $entries = [];   // Zielname im ZIP => absoluter Quellpfad
                foreach ($accounts as $account) {
                    $hasTx = BankTransaction::query()
                        ->where('bank_account_id', $account->id)
                        ->when($business, fn ($q) => $q->where('business_id', $business->id))
                        ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])
                        ->exists();

                    if (! $hasTx) {
                        continue; // Konten ohne Umsätze im Zeitraum überspringen
                    }

                    $folder = $this->zipFolderName($account);

                    $overview = $service->generate($from, $to, $business, $account, withReceipts: false);
                    $entries[$folder . '/Uebersicht.pdf'] = $disk->path($overview);

                    $full = $service->generate($from, $to, $business, $account);
                    $entries[$folder . '/Pendelordner_mit_Belegen.pdf'] = $disk->path($full);

                    foreach ($service->attachmentFiles($from, $to, $business, $account) as $file) {
                        $entries[$folder . '/Belege/' . $file['name']] = $file['absolute'];
                    }
                }

                if (empty($entries)) {
                    Notification::make()
                        ->title('Keine Umsätze')
                        ->body('Im gewählten Zeitraum/Betrieb gibt es auf keinem Konto Umsätze.')
                        ->warning()->send();

                    return null;
                }

                $zipRel = 'reports/Pendelordner_' . $from->format('Ymd') . '-' . $to->format('Ymd')
                    . ($business ? '_b' . $business->id : '') . '_konten.zip';
                $zipAbs = $disk->path($zipRel);

                $zip = new ZipArchive();
                if ($zip->open($zipAbs, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    Notification::make()->title('ZIP konnte nicht erstellt werden')->danger()->send();

                    return null;
                }
                foreach ($entries as $entryName => $absolute) {
                    $zip->addFile($absolute, $entryName);
                }
                $zip->close();

                return $disk->download($zipRel);
            });
    }

    /**
     * Löst den gewählten Zeitraum (Preset oder individuell) zu [von, bis] auf.
     *
     * @param  array<string, mixed>  $state
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(array $state): array
    {
        if (($state['period'] ?? null) === 'custom') {
            return [Carbon::parse($state['from']), Carbon::parse($state['until'])];
        }

        [$f, $t] = BankTransactionsTable::resolvePeriod($state['period'] ?? 'this_month');

        return [Carbon::parse($f), Carbon::parse($t)];
    }

    /** Sicherer, lesbarer Ordnername je Konto innerhalb des ZIP. */
    private function zipFolderName(BankAccount $account): string
    {
        $base = trim((string) $account->label) !== '' ? $account->label : ('Konto ' . $account->id);
        $safe = preg_replace('/[^\p{L}\p{N}\-_ ]+/u', '', $base);

        return trim((string) preg_replace('/\s+/', ' ', (string) $safe)) ?: ('Konto ' . $account->id);
    }
}
