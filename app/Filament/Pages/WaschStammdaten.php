<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Models\WashArticle;
use App\Models\WashFreePlate;
use App\Models\WashPaymentState;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Pflege der Wasch-Stammdaten: Kassen-Artikel je Station (EAN, Preis),
 * Freiwäsche-Kennzeichen (eigen/mitarbeiter/test) und Bedeutung der State-Codes.
 */
class WaschStammdaten extends Page
{
    protected string $view = 'filament.pages.wasch-stammdaten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Waschanlage';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Wasch-Stammdaten';

    protected static ?string $navigationLabel = 'Stammdaten';

    // Neuer Artikel.
    public ?int $naBusiness = null;

    public string $naProgram = '';

    public string $naName = '';

    public string $naType = 'einzel';

    public string $naEan = '';

    public string $naVk = '';

    // Neues Kennzeichen.
    public string $newPlate = '';

    public string $newPlateCategory = 'eigen';

    // Neuer State-Code.
    public ?int $newStateCode = null;

    public string $newStateLabel = '';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getBusinessesProperty(): Collection
    {
        return Business::orderBy('sort_order')->get();
    }

    /** Artikel gruppiert je Betrieb. */
    public function getArticlesByBusinessProperty(): Collection
    {
        return WashArticle::orderBy('sort_order')->orderBy('name')->get()->groupBy('business_id');
    }

    public function getPlatesProperty(): Collection
    {
        return WashFreePlate::orderBy('category')->orderBy('plate')->get();
    }

    public function getStatesProperty(): Collection
    {
        return WashPaymentState::orderBy('code')->get();
    }

    // ---- Artikel -----------------------------------------------------------

    public function updateArticle(int $id, string $field, mixed $value): void
    {
        if (! in_array($field, ['name', 'ean', 'price', 'type', 'article_number', 'active', 'ledger_account'], true)) {
            return;
        }
        if ($field === 'price') {
            $value = $this->toFloat($value);
        } elseif ($field === 'active') {
            $value = (bool) $value;
        } else {
            $value = trim((string) $value) ?: null;
        }

        WashArticle::whereKey($id)->update([$field => $value]);
    }

    public function addArticle(): void
    {
        $program = trim($this->naProgram);
        if (! $this->naBusiness || $program === '') {
            Notification::make()->title('Bitte Station und Programm angeben')->warning()->send();

            return;
        }
        if (WashArticle::where('business_id', $this->naBusiness)->where('program', $program)->exists()) {
            Notification::make()->title('Programm für diese Station existiert schon')->warning()->send();

            return;
        }

        WashArticle::create([
            'business_id' => $this->naBusiness,
            'program' => $program,
            'name' => trim($this->naName) ?: $program,
            'type' => $this->naType === 'flatrate' ? 'flatrate' : 'einzel',
            'ean' => trim($this->naEan) ?: null,
            'price' => $this->toFloat($this->naVk),
            'ledger_account' => '6621',
            'active' => true,
            'sort_order' => (int) WashArticle::max('sort_order') + 1,
        ]);

        $this->reset(['naProgram', 'naName', 'naEan', 'naVk']);
        Notification::make()->title('Artikel angelegt')->success()->send();
    }

    public function deleteArticle(int $id): void
    {
        WashArticle::whereKey($id)->delete();
        Notification::make()->title('Artikel gelöscht')->success()->send();
    }

    // ---- Kennzeichen -------------------------------------------------------

    public function addPlate(): void
    {
        $plate = trim($this->newPlate);
        if ($plate === '') {
            return;
        }
        $normalized = WashFreePlate::normalize($plate);
        WashFreePlate::updateOrCreate(
            ['normalized' => $normalized],
            ['plate' => $plate, 'category' => $this->newPlateCategory, 'active' => true]
        );
        $this->reset(['newPlate']);
        Notification::make()->title('Kennzeichen gespeichert')->success()->send();
    }

    public function updatePlate(int $id, string $field, mixed $value): void
    {
        if (! in_array($field, ['plate', 'category', 'note', 'active'], true)) {
            return;
        }
        $data = [];
        if ($field === 'plate') {
            $data['plate'] = trim((string) $value);
            $data['normalized'] = WashFreePlate::normalize((string) $value);
        } elseif ($field === 'active') {
            $data['active'] = (bool) $value;
        } else {
            $data[$field] = trim((string) $value) ?: null;
        }
        WashFreePlate::whereKey($id)->update($data);
    }

    public function deletePlate(int $id): void
    {
        WashFreePlate::whereKey($id)->delete();
    }

    // ---- State-Codes -------------------------------------------------------

    public function updateState(int $id, string $field, mixed $value): void
    {
        if (! in_array($field, ['label', 'counts_as_revenue'], true)) {
            return;
        }
        if ($field === 'counts_as_revenue') {
            $value = (bool) $value;
        } else {
            $value = trim((string) $value);
        }
        WashPaymentState::whereKey($id)->update([$field => $value]);
    }

    public function addState(): void
    {
        if ($this->newStateCode === null) {
            return;
        }
        WashPaymentState::updateOrCreate(
            ['code' => (int) $this->newStateCode],
            ['label' => trim($this->newStateLabel) ?: ('Status ' . $this->newStateCode), 'counts_as_revenue' => true]
        );
        $this->reset(['newStateCode', 'newStateLabel']);
        Notification::make()->title('State-Code gespeichert')->success()->send();
    }

    /** "16,95" oder "16.95" -> float. */
    private function toFloat(mixed $v): ?float
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^-?\d+,\d{1,2}$/', $s)) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return (float) $s;
    }
}
