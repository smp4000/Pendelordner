<?php

namespace App\Filament\Resources\FintsConnections\Pages;

use App\Filament\Resources\FintsConnections\FintsConnectionResource;
use App\Services\Bank\FinTsErrorTranslator;
use App\Services\Bank\FinTsService;
use App\Services\Bank\FinTsTanRequiredException;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;
use Throwable;

class EditFintsConnection extends EditRecord
{
    protected static string $resource = FintsConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->testAction(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Testet den Zugang echt gegen die Bank: zeigt zuerst, was gesendet wird
     * (u. a. die 25-stellige Produktbezeichnung), und versucht dann den Login.
     * So sieht man sofort, ob die Produktregistrierung akzeptiert wird (kein
     * 9078 mehr) – die eigentliche Handy-Freigabe passiert weiterhin unter
     * „FinTS-Konten abrufen".
     */
    private function testAction(): Action
    {
        return Action::make('test')
            ->label('Verbindung testen')
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->action(function () {
                $c = $this->record;

                // 1. Diagnose: was geht an die Bank?
                $productName = $c->product_id ?: (config('pendelordner.fints.product_id') ?: 'PENDELORDNER');
                $len = mb_strlen($productName);
                $diag = 'Produktbezeichnung: <strong>' . e($productName) . '</strong> (Länge ' . $len . ')'
                    . ($len === 25 ? ' ✓' : ' ⚠ sollte 25 sein!') . '<br>';

                // 2. Echter Login-Test (nutzt die gespeicherte PIN).
                try {
                    $accounts = (new FinTsService())->discoverAccounts($c);
                    $body = $diag . '<strong>✓ Login erfolgreich</strong> – ' . count($accounts) . ' Konto/Konten gefunden (ohne TAN).';
                    $type = 'success';
                } catch (FinTsTanRequiredException $e) {
                    // Bis hierher OK -> Produktregistrierung akzeptiert, jetzt Freigabe nötig.
                    $body = $diag . '<strong>✓ Produktregistrierung akzeptiert!</strong> Die Bank verlangt jetzt die Handy-Freigabe. '
                        . 'Gehe zu „FinTS-Konten abrufen" und bestätige in SecureGo plus.';
                    $type = 'success';
                } catch (Throwable $e) {
                    $body = $diag . '<strong>Fehler:</strong> ' . e(FinTsErrorTranslator::translate($e));
                    $type = 'danger';
                }

                Notification::make()
                    ->title('Verbindungstest: ' . $c->label)
                    ->body(new HtmlString($body))
                    ->{$type}()
                    ->persistent()
                    ->send();
            });
    }
}
