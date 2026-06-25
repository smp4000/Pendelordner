<?php

namespace App\Filament\Pages;

use App\Enums\ChartOfAccounts;
use App\Models\Business;
use App\Services\Accounting\DatevExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Erzeugt einen DATEV-EXTF-Buchungsstapel (Modul 14) für einen Zeitraum,
 * optional je Betrieb, und bietet ihn als CSV-Download an.
 */
class DatevExportSeite extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.datev-export-seite';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Buchhaltung';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'DATEV-Export';

    protected static ?string $navigationLabel = 'DATEV-Export';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from' => Carbon::now()->startOfMonth()->toDateString(),
            'to' => Carbon::now()->endOfMonth()->toDateString(),
            'chart' => config('pendelordner.kontierung.standard_kontenrahmen', 'skr03'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('from')->label('Von')->native(false)->displayFormat('d.m.Y')->required(),
                DatePicker::make('to')->label('Bis')->native(false)->displayFormat('d.m.Y')->required(),
                Select::make('business_id')->label('Betrieb')
                    ->placeholder('Alle Betriebe')
                    ->options(Business::orderBy('sort_order')->get()->pluck('display_label', 'id')),
                Select::make('chart')->label('Kontenrahmen')
                    ->options(ChartOfAccounts::class)->required(),
                TextInput::make('consultant')->label('Beraternummer'),
                TextInput::make('client')->label('Mandantennummer'),
            ])
            ->statePath('data');
    }

    public function generateAction(): Action
    {
        return Action::make('generate')
            ->label('DATEV-Datei erzeugen & herunterladen')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function () {
                $state = $this->form->getState();

                $export = (new DatevExportService())->generate(
                    Carbon::parse($state['from']),
                    Carbon::parse($state['to']),
                    $state['business_id'] ? Business::find($state['business_id']) : null,
                    ChartOfAccounts::from($state['chart']),
                    (string) ($state['consultant'] ?? ''),
                    (string) ($state['client'] ?? ''),
                );

                return Storage::disk('local')->download($export->file_path);
            });
    }
}
