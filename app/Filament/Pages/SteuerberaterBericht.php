<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BankTransactions\Tables\BankTransactionsTable;
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
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

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
                    ->options(Business::orderBy('sort_order')->get()->pluck('display_label', 'id')),
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

                if (($state['period'] ?? null) === 'custom') {
                    $from = Carbon::parse($state['from']);
                    $to = Carbon::parse($state['until']);
                } else {
                    [$f, $t] = BankTransactionsTable::resolvePeriod($state['period'] ?? 'this_month');
                    $from = Carbon::parse($f);
                    $to = Carbon::parse($t);
                }

                $business = $state['business_id'] ? Business::find($state['business_id']) : null;

                $path = (new PdfReportService())->generate($from, $to, $business);

                return Storage::disk('local')->download($path);
            });
    }
}
