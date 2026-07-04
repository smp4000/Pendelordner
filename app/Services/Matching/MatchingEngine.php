<?php

namespace App\Services\Matching;

use App\Models\BankTransaction;
use App\Models\MatchingRule;
use App\Models\Receipt;
use Illuminate\Support\Collection;

/**
 * Matching-Engine (Modul 4).
 *
 * Zwei Aufgaben:
 *  1. applyRules()      – lernfähige Vorkontierung eines Bankumsatzes anhand der
 *     Zuordnungsregeln (HBW=Blumen, Pappert=Backwaren, …).
 *  2. suggestReceipts() – Vorschläge, welche Belege zu einem Umsatz passen,
 *     bewertet nach Betrag, Lieferant, Datum und IBAN.
 *
 * Schwellwerte/Gewichtung stammen aus config/pendelordner.php.
 */
class MatchingEngine
{
    /**
     * Wendet die erste passende Zuordnungsregel auf den Umsatz an und setzt
     * Lieferant/Kategorie/Kostenstelle/Betrieb (nur leere Felder). Erhöht den
     * Lernzähler der Regel. Gibt die angewandte Regel zurück (oder null).
     */
    public function applyRules(BankTransaction $transaction, bool $persist = true): ?MatchingRule
    {
        $rules = MatchingRule::query()->active()
            ->orderByDesc('priority')
            ->orderByDesc('hit_count')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->ruleMatches($rule, $transaction)) {
                continue;
            }

            $transaction->supplier_id ??= $rule->supplier_id;
            $transaction->category_id ??= $rule->category_id;
            $transaction->cost_center_id ??= $rule->cost_center_id;
            $transaction->ledger_account_id ??= $rule->ledger_account_id;
            $transaction->business_id ??= $rule->business_id;

            if ($persist) {
                $transaction->saveQuietly();
                $rule->registerHit();
            }

            return $rule;
        }

        return null;
    }

    /**
     * Bewertet alle noch nicht (vollständig) zugeordneten Belege gegen den
     * Umsatz und gibt die besten Vorschläge zurück.
     *
     * @return Collection<int, array{receipt: Receipt, score: float}>
     */
    public function suggestReceipts(BankTransaction $transaction, ?int $limit = 5): Collection
    {
        $threshold = (float) config('pendelordner.matching.vorschlag_ab', 60);

        $candidates = Receipt::query()
            ->unallocated()
            ->notDuplicate()
            ->when($transaction->business_id, fn ($q) => $q->where(function ($q) use ($transaction) {
                $q->whereNull('business_id')->orWhere('business_id', $transaction->business_id);
            }))
            ->get();

        return $candidates
            ->map(fn (Receipt $r) => ['receipt' => $r, 'score' => $this->scoreReceipt($transaction, $r)])
            ->filter(fn (array $row) => $row['score'] >= $threshold)
            ->sortByDesc('score')
            ->when($limit, fn (Collection $c) => $c->take($limit))
            ->values();
    }

    /**
     * Umgekehrter Vorschlag: passende Bankumsätze zu einem Beleg.
     *
     * @return Collection<int, array{transaction: BankTransaction, score: float}>
     */
    public function suggestTransactions(Receipt $receipt, ?int $limit = 5): Collection
    {
        $threshold = (float) config('pendelordner.matching.vorschlag_ab', 60);
        $maxDays = (int) config('pendelordner.matching.datum_toleranz_tage', 14);
        $gross = (float) ($receipt->gross_amount ?? 0);

        $candidates = BankTransaction::query()
            ->open() // nur Umsätze, die noch (Teil-)Belege brauchen
            ->when($gross > 0, fn ($q) => $q->whereRaw('ABS(amount) BETWEEN ? AND ?', [$gross * 0.95, $gross * 1.05]))
            ->when($receipt->invoice_date, fn ($q) => $q->whereBetween('booking_date', [
                $receipt->invoice_date->copy()->subDays($maxDays)->toDateString(),
                $receipt->invoice_date->copy()->addDays($maxDays)->toDateString(),
            ]))
            ->when($receipt->business_id, fn ($q) => $q->where(function ($q) use ($receipt) {
                $q->whereNull('business_id')->orWhere('business_id', $receipt->business_id);
            }))
            ->limit(300)
            ->get();

        return $candidates
            ->map(fn (BankTransaction $t) => ['transaction' => $t, 'score' => $this->scoreReceipt($t, $receipt)])
            ->filter(fn (array $row) => $row['score'] >= $threshold)
            ->sortByDesc('score')
            ->when($limit, fn (Collection $c) => $c->take($limit))
            ->values();
    }

    /**
     * Trefferquote (0–100 %) zwischen Umsatz und Beleg anhand gewichteter
     * Einzelkriterien Betrag/Lieferant/Datum/IBAN.
     */
    public function scoreReceipt(BankTransaction $transaction, Receipt $receipt): float
    {
        $weights = config('pendelordner.matching.gewichtung', [
            'betrag' => 50, 'lieferant' => 30, 'datum' => 10, 'iban' => 10,
        ]);

        $score = 0.0;

        // Belegnummer im Verwendungszweck/in der Bankreferenz – sehr starkes
        // Signal (z. B. SEPA-Verwendungszweck enthält die Rechnungsnummer).
        $invoiceNo = preg_replace('/\s+/', '', (string) $receipt->invoice_number);
        if ($invoiceNo !== null && mb_strlen($invoiceNo) >= 4) {
            $haystack = mb_strtolower(preg_replace('/\s+/', '',
                (string) $transaction->purpose . (string) $transaction->bank_reference));
            if (str_contains($haystack, mb_strtolower($invoiceNo))) {
                $score += (float) ($weights['belegnummer'] ?? 50);
            }
        }

        // Betrag (absolut, da Umsätze negativ sein können)
        $amount = abs((float) $transaction->amount);
        $gross = (float) ($receipt->gross_amount ?? 0);
        $tolerance = (float) config('pendelordner.matching.betrag_toleranz', 0.01);
        if ($gross > 0 && abs($amount - $gross) <= $tolerance) {
            $score += $weights['betrag'];
        } elseif ($gross > 0 && $amount > 0) {
            // Teilpunkte bei knapper Abweichung (< 2 %)
            $deviation = abs($amount - $gross) / max($amount, $gross);
            if ($deviation < 0.02) {
                $score += $weights['betrag'] * (1 - $deviation / 0.02) * 0.5;
            }
        }

        // Lieferant
        if ($transaction->supplier_id && $transaction->supplier_id === $receipt->supplier_id) {
            $score += $weights['lieferant'];
        } elseif ($receipt->supplier && $transaction->counterparty) {
            if ($this->textContains($transaction->counterparty, $receipt->supplier->name)) {
                $score += $weights['lieferant'];
            }
        }

        // Datum
        $maxDays = (int) config('pendelordner.matching.datum_toleranz_tage', 14);
        if ($receipt->invoice_date && $transaction->booking_date) {
            $days = abs($receipt->invoice_date->diffInDays($transaction->booking_date));
            if ($days <= $maxDays) {
                $score += $weights['datum'] * (1 - $days / max($maxDays, 1));
            }
        }

        // IBAN
        if ($receipt->iban && $transaction->counterparty_iban
            && $this->normalizeIban($receipt->iban) === $this->normalizeIban($transaction->counterparty_iban)) {
            $score += $weights['iban'];
        }

        return round(min($score, 100), 1);
    }

    /**
     * Wendet eine einzelne Regel rückwirkend auf vorhandene Umsätze an
     * (z. B. direkt nach dem Erstellen einer Regel aus einem Umsatz). Es
     * werden nur leere Felder gefüllt; bereits gesetzte Zuordnungen bleiben.
     * Gibt die Anzahl der geänderten Umsätze zurück.
     */
    public function applyRuleToExisting(MatchingRule $rule, bool $onlyUnreviewed = true): int
    {
        $count = 0;

        BankTransaction::query()
            ->when($onlyUnreviewed, fn ($q) => $q->where('reviewed', false))
            ->chunkById(500, function ($transactions) use ($rule, &$count) {
                foreach ($transactions as $transaction) {
                    if (! $this->ruleMatches($rule, $transaction)) {
                        continue;
                    }

                    $before = [
                        $transaction->supplier_id, $transaction->category_id,
                        $transaction->cost_center_id, $transaction->ledger_account_id,
                    ];

                    $transaction->supplier_id ??= $rule->supplier_id;
                    $transaction->category_id ??= $rule->category_id;
                    $transaction->cost_center_id ??= $rule->cost_center_id;
                    $transaction->ledger_account_id ??= $rule->ledger_account_id;

                    $after = [
                        $transaction->supplier_id, $transaction->category_id,
                        $transaction->cost_center_id, $transaction->ledger_account_id,
                    ];

                    if ($before !== $after) {
                        $transaction->saveQuietly();
                        $rule->registerHit();
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function ruleMatches(MatchingRule $rule, BankTransaction $transaction): bool
    {
        // Erstes Kriterium muss passen …
        if (! $this->patternMatches($transaction, $rule->pattern, $rule->pattern_type)) {
            return false;
        }

        // … und, falls gesetzt, zusätzlich das zweite (UND-Verknüpfung). So lassen
        // sich mehrere Verträge derselben Gesellschaft (gleicher Empfänger)
        // über z. B. die Vertragsnummer im Verwendungszweck unterscheiden.
        if ($rule->pattern2 !== null && trim((string) $rule->pattern2) !== '') {
            return $this->patternMatches($transaction, $rule->pattern2, $rule->pattern_type2 ?: 'purpose');
        }

        return true;
    }

    /** "Enthält"-Prüfung eines Musters gegen das gewählte Umsatzfeld (LIKE, ohne Groß-/Kleinschreibung). */
    private function patternMatches(BankTransaction $transaction, ?string $needle, ?string $type): bool
    {
        if ($needle === null || trim($needle) === '') {
            return false;
        }

        // Betrag ist kein Text: exakter Vergleich (Vorzeichen egal, kleine Toleranz).
        if ($type === 'amount') {
            return $this->amountMatches($transaction, $needle);
        }

        $haystacks = match ($type) {
            'counterparty' => [$transaction->counterparty],
            'purpose' => [$transaction->purpose],
            'iban' => [$transaction->counterparty_iban],
            default => [$transaction->counterparty, $transaction->purpose, $transaction->counterparty_iban],
        };

        foreach ($haystacks as $haystack) {
            if ($haystack && $this->textContains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function textContains(string $haystack, string $needle): bool
    {
        return str_contains(mb_strtolower($haystack), mb_strtolower(trim($needle)));
    }

    /**
     * Exakter Betragsvergleich für das Feld "Betrag". Das Vorzeichen wird
     * ignoriert (der Nutzer gibt i. d. R. 136,08 ein, auch wenn der Umsatz
     * -136,08 ist); eine kleine Toleranz fängt Rundungen ab. Deutsches Format
     * (Tausenderpunkt, Komma) wird unterstützt.
     */
    private function amountMatches(BankTransaction $transaction, string $needle): bool
    {
        $value = (float) str_replace(',', '.', str_replace(['.', ' '], '', trim($needle)));
        if ($value == 0.0) {
            return false;
        }

        return abs(abs((float) $transaction->amount) - abs($value)) < 0.005;
    }

    private function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }
}
