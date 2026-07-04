<?php

use App\Models\LedgerAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hinterlegt je Sachkonto einen USt-Satz. Wird beim Aufteilen (Split)
 * automatisch als Steuersatz der Zeile übernommen, sobald das Konto gewählt
 * wird. Für vorhandene Konten wird der Satz – soweit im Namen vermerkt
 * ("USt voll"/"USt erm."/"USt frei") – direkt befüllt; alle übrigen bleiben
 * leer und können in der Sachkonten-Verwaltung gepflegt werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->nullable()->after('name')
                ->comment('USt-Satz des Kontos (%) – wird beim Aufteilen übernommen');
        });

        // Vorhandene Konten aus dem Namen befüllen (nicht destruktiv).
        LedgerAccount::query()->whereNull('tax_rate')->select(['id', 'name'])
            ->chunkById(500, function ($accounts) {
                foreach ($accounts as $account) {
                    $rate = LedgerAccount::deriveTaxRateFromName($account->name);
                    if ($rate !== null) {
                        $account->newQuery()->whereKey($account->id)->update(['tax_rate' => $rate]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
