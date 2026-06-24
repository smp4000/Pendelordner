<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Services\Pdf\PdfReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
 * gewählten Monat und optional einen Betrieb.
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
            'month' => Carbon::now()->format('Y-m'),
            'business_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('month')
                    ->label('Monat')
                    ->options($this->monthOptions())
                    ->required(),
                Select::make('business_id')
                    ->label('Betrieb')
                    ->placeholder('Alle Betriebe')
                    ->options(Business::orderBy('sort_order')->pluck('name', 'id')),
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

                $month = Carbon::createFromFormat('Y-m', $state['month'])->startOfMonth();
                $business = $state['business_id'] ? Business::find($state['business_id']) : null;

                $path = (new PdfReportService())->generateMonthlyReport($month, $business);

                return Storage::disk('local')->download($path);
            });
    }

    /**
     * @return array<string, string>
     */
    private function monthOptions(): array
    {
        $names = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
            'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        $options = [];
        for ($i = 0; $i < 24; $i++) {
            $month = Carbon::now()->startOfMonth()->subMonths($i);
            $options[$month->format('Y-m')] = $names[$month->month] . ' ' . $month->year;
        }

        return $options;
    }
}
