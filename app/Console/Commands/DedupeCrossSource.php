<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Console\Command;

/**
 * Räumt quellenübergreifende Doubletten auf: denselben Umsatz, der einmal per
 * CSV (ohne Bankreferenz) und einmal per FinTS (mit Referenz) angelegt wurde.
 *
 * Gruppiert je Konto über den referenzlosen Hash (Datum + Betrag + Zweck +
 * Empfänger). In jeder Gruppe bleibt der Umsatz mit der meisten Pflege
 * (Kontierung, Beleg, geprüft …) erhalten; die übrigen werden – nach Umzug
 * evtl. angehängter Belege auf den Behalten-Umsatz – soft-gelöscht.
 *
 * Standard ist eine Vorschau. Erst mit --apply wird tatsächlich gelöscht:
 *   php artisan bank:dedupe-cross-source            (Vorschau)
 *   php artisan bank:dedupe-cross-source --apply    (löschen)
 *   php artisan bank:dedupe-cross-source --account=5 --apply
 */
class DedupeCrossSource extends Command
{
    protected $signature = 'bank:dedupe-cross-source {--account= : Nur dieses Bankkonto (ID)} {--apply : Doubletten tatsächlich soft-löschen}';

    protected $description = 'Findet und entfernt quellenübergreifende Umsatz-Doubletten (CSV vs. FinTS)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $accounts = BankAccount::query()
            ->when($this->option('account'), fn ($q) => $q->whereKey((int) $this->option('account')))
            ->get();

        if ($accounts->isEmpty()) {
            $this->warn('Keine Bankkonten gefunden.');

            return self::SUCCESS;
        }

        $this->info($apply ? 'Modus: LÖSCHEN (--apply)' : 'Modus: Vorschau (nichts wird gelöscht). Mit --apply ausführen.');

        $totalGroups = 0;
        $totalRemoved = 0;

        foreach ($accounts as $account) {
            $rows = BankTransaction::query()
                ->where('bank_account_id', $account->id)
                ->withCount('receipts')
                ->orderBy('id')
                ->get();

            // Nach Match-Schlüssel gruppieren (Konto + Datum + Betrag + EREF/Zweck).
            $groups = $rows->groupBy(fn (BankTransaction $t) => $t->matchKeyValue());

            $accountRemoved = 0;

            foreach ($groups as $group) {
                if ($group->count() < 2) {
                    continue;
                }

                $totalGroups++;

                // Behalten: höchster Pflege-Score, bei Gleichstand der ältere (kleinere id).
                $sorted = $group->sort(function (BankTransaction $a, BankTransaction $b) {
                    return [$this->score($b), -$b->id] <=> [$this->score($a), -$a->id];
                })->values();

                $keeper = $sorted->first();
                $dropList = $sorted->slice(1);

                $this->line(sprintf(
                    '  %s | %s | %s € | behalten #%d%s',
                    $account->label,
                    $keeper->booking_date?->format('d.m.Y'),
                    number_format((float) $keeper->amount, 2, ',', '.'),
                    $keeper->id,
                    ' | entfernen: ' . $dropList->pluck('id')->map(fn ($id) => '#' . $id)->implode(', '),
                ));

                if ($apply) {
                    foreach ($dropList as $drop) {
                        // Etwaige Belege des Doppelgängers auf den Behalten-Umsatz umhängen.
                        if ($drop->receipts_count > 0) {
                            foreach ($drop->receipts as $r) {
                                $keeper->receipts()->syncWithoutDetaching([
                                    $r->id => ['amount' => $r->pivot->amount, 'match_type' => $r->pivot->match_type],
                                ]);
                            }
                            $drop->receipts()->detach();
                        }
                        $drop->delete(); // Soft-Delete
                        $accountRemoved++;
                    }
                    $keeper->recalculateStatus();
                } else {
                    $accountRemoved += $dropList->count();
                }
            }

            if ($accountRemoved > 0) {
                $this->line(sprintf('  → %s: %d Doublette(n).', $account->label, $accountRemoved));
            }
            $totalRemoved += $accountRemoved;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d Doublette(n) in %d Gruppe(n)%s.',
            $apply ? 'Entfernt:' : 'Gefunden:',
            $totalRemoved,
            $totalGroups,
            $apply ? '' : ' – zum Löschen mit --apply erneut ausführen',
        ));

        return self::SUCCESS;
    }

    /** Pflege-Score: je mehr manuelle Bearbeitung, desto eher behalten. */
    private function score(BankTransaction $t): int
    {
        return ($t->receipts_count > 0 ? 100 : 0)
            + ($t->category_id ? 8 : 0)
            + ($t->ledger_account_id ? 8 : 0)
            + ($t->cost_center_id ? 4 : 0)
            + ($t->reviewed ? 4 : 0)
            + (trim((string) $t->accountant_note) !== '' ? 2 : 0)
            + ($t->fully_paid ? 1 : 0);
    }
}
