<?php

namespace Database\Seeders;

use App\Models\BankPreset;
use Illuminate\Database\Seeder;

/**
 * Bank-Vorlagen für FinTS-Zugänge (Modul 1). Quelle: gängige HBCI/FinTS-
 * Zugangsdaten deutscher Banken. HBCI-Version durchgängig 3.0 („300").
 */
class BankPresetSeeder extends Seeder
{
    public function run(): void
    {
        // Reine Referenzdaten – vor dem Neuaufbau leeren (vermeidet veraltete Einträge).
        BankPreset::query()->delete();

        $presets = [
            [
                'name' => 'Commerzbank',
                'fints_url' => 'https://fints.commerzbank.de/fints',
                'login_hint' => 'Original-Teilnehmernummer (kein Alias-Anmeldename!)',
                'customer_id_hint' => '(leer)',
                'account_hint' => 'Kontonummer mit führenden Nullen',
                'note' => 'HBCI mit PIN/TAN muss einmalig von der Commerzbank für das Konto aktiviert werden.',
            ],
            [
                'name' => 'Deutsche Bank',
                'fints_url' => 'https://fints.deutsche-bank.de',
                'login_hint' => 'Filial-, Konto- und Unterkontonummer, z. B. 700123456700',
                'customer_id_hint' => '(leer)',
                'account_hint' => 'Kontonummer + Unterkontonummer, ggf. mit führenden Nullen auf 9 Stellen',
                'note' => 'HBCI mit PIN/TAN (HBCI+) muss einmalig im Internetbanking unter Service / Optionen / Weitere Dienste aktiviert werden.',
            ],
            [
                'name' => 'Volksbank / VR-Bank (Atruvia, früher Fiducia/GAD)',
                'fints_url' => 'https://fints1.atruvia.de/cgi-bin/hbciservlet',
                'login_hint' => 'VR-NetKey bzw. Kundennummer/Alias laut Bank',
                'customer_id_hint' => 'Bei manchen Banken VR-Kennung (beginnt mit „VR"/„VRK"); sonst leer',
                'account_hint' => null,
                'note' => 'Aktuelle FinTS-Adresse der Atruvia (ehem. Fiducia & GAD). Deine Bank kann eine eigene URL haben – im Zweifel bei der Bank erfragen.',
            ],
            [
                'name' => 'Volksbank (Fiducia, ALT)',
                'fints_url' => 'https://hbci11.fiducia.de/cgi-bin/hbciservlet',
                'login_hint' => 'VR-Net-Key',
                'customer_id_hint' => '(leer)',
                'account_hint' => null,
                'note' => 'Veraltete Adresse – i. d. R. nicht mehr erreichbar. Bitte „Atruvia" verwenden.',
            ],
            [
                'name' => 'HypoVereinsbank',
                'fints_url' => 'https://hbci-01.hypovereinsbank.de/bank/hbci',
                'login_hint' => '10-stellige Directbanking-Nummer',
                'customer_id_hint' => '(leer)',
                'account_hint' => 'Kontonummer 10-stellig, ggf. mit führenden Nullen',
                'note' => null,
            ],
            [
                'name' => 'Postbank',
                'fints_url' => 'https://hbci.postbank.de/banking/hbci.do',
                'login_hint' => 'Kontonummer (bei mehreren Konten die Hauptkontonummer)',
                'customer_id_hint' => '(leer)',
                'account_hint' => null,
                'note' => null,
            ],
            [
                'name' => 'comdirect',
                'fints_url' => 'https://hbci.comdirect.de/pintan/HbciPinTanHttpGate',
                'login_hint' => '8-stellige Zugangsnummer',
                'customer_id_hint' => '(leer)',
                'account_hint' => 'Kontonummer ergänzt um zwei Nullen am Ende (z. B. 456789 -> 45678900)',
                'note' => 'PIN: 6-stellig.',
            ],
            [
                'name' => 'Dresdner Bank',
                'fints_url' => 'https://hbci.dresdner-bank.de',
                'login_hint' => '8-stellige Multikanal-Banking-ID',
                'customer_id_hint' => '(leer)',
                'account_hint' => '10-stellige Kontonummer, ggf. mit führenden Nullen',
                'note' => null,
            ],
            [
                'name' => 'Cortal Consors',
                'fints_url' => 'https://brokerage-hbci.consors.de/hbci',
                'login_hint' => '9-stellige Verrechnungskontonummer + 3-stellige Berechtigtennummer, z. B. 900111333001',
                'customer_id_hint' => '(leer)',
                'account_hint' => 'Kontonummer ohne führende Null',
                'note' => 'Einrichtung über das Verrechnungskonto; Depot/Tagesgeld werden automatisch mit übertragen.',
            ],
        ];

        foreach ($presets as $i => $preset) {
            BankPreset::updateOrCreate(
                ['name' => $preset['name']],
                array_merge($preset, ['hbci_version' => '300', 'sort_order' => $i + 1])
            );
        }
    }
}
