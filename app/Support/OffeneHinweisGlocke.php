<?php

namespace App\Support;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Models\BankTransaction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Hält die Kopfleisten-Glocke (Datenbank-Benachrichtigungen) mit den offenen
 * Hinweisen synchron: legt bei einem offenen Hinweis eine Meldung an und
 * entfernt sie wieder, sobald der Hinweis erledigt oder gelöscht ist. Jede
 * Meldung trägt eine feste ID (offener-hinweis-<umsatz-id>), damit sie
 * eindeutig wiederfindbar ist.
 */
class OffeneHinweisGlocke
{
    /**
     * Glocken-Meldung zum Umsatz auf den aktuellen Stand bringen: vorhandene
     * entfernen und – wenn der Hinweis offen ist – neu für alle Nutzer anlegen.
     */
    public static function sync(BankTransaction $transaction): void
    {
        self::clear($transaction->id);

        if (! $transaction->note_open || blank($transaction->accountant_note)) {
            return;
        }

        // Die Umsatz-ID über viewData mitspeichern – Filament verwirft die
        // Notification-ID beim Speichern (getDatabaseMessage), viewData bleibt
        // aber erhalten und dient als Wiederfindungs-Schlüssel.
        $notification = Notification::make()
            ->warning()
            ->icon('heroicon-o-exclamation-triangle')
            ->title('Offener Hinweis')
            ->body(($transaction->counterparty ? $transaction->counterparty . ': ' : '') . $transaction->accountant_note)
            ->viewData(['transaction_id' => $transaction->id])
            ->actions([
                Action::make('oeffnen')
                    ->label('Öffnen')
                    ->url(Kontoumsatzdetails::getUrl() . '?tx=' . $transaction->id),
            ]);

        foreach (User::all() as $user) {
            $notification->sendToDatabase($user, isEventDispatched: true);
        }
    }

    /** Alle Glocken-Meldungen zu diesem Umsatz entfernen (z. B. nach "Erledigt"). */
    public static function clear(int $transactionId): void
    {
        DatabaseNotification::query()
            ->where('data->viewData->transaction_id', $transactionId)
            ->delete();
    }
}
