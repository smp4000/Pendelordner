<?php

namespace Database\Seeders;

use App\Models\SplitTemplate;
use Illuminate\Database\Seeder;

/**
 * Standard-Aufteilungsvorlagen. Aktuell: das Aral/OIL-Avis mit seinen festen
 * Belegarten auf die passenden Verrechnungskonten (Beträge bleiben leer).
 */
class SplitTemplateSeeder extends Seeder
{
    public function run(): void
    {
        SplitTemplate::updateOrCreate(
            ['name' => 'Aral/OIL-Avis'],
            ['rows' => [
                ['ledger_number' => '1616', 'tax_rate' => '0', 'label' => 'Kreditkartenabrechnung'],
                ['ledger_number' => '1324', 'tax_rate' => '0', 'label' => 'Supercardabrechnung'],
                ['ledger_number' => '1540', 'tax_rate' => '0', 'label' => 'Stationskarten-Stundung'],
                ['ledger_number' => '1616', 'tax_rate' => '0', 'label' => 'OK/DK-Tankstellen-Abrechnung'],
                ['ledger_number' => '4500', 'tax_rate' => '19', 'label' => 'POLA Werbung'],
            ]]
        );
    }
}
