<?php

namespace Tests\Feature;

use App\Services\Bank\FinTsService;
use Fhp\Model\StatementOfAccount\Transaction;
use ReflectionMethod;
use Tests\TestCase;

class FintsPurposeTest extends TestCase
{
    public function test_voller_verwendungszweck_mit_eref_mref_cred_iban_bic(): void
    {
        $t = new Transaction();
        $t->setStructuredDescription([
            'SVWZ' => '/INV/0227561766 6.6.2026/ / SHOP-ABRECHNUNG 3322629128 06.06.26 LL SE/0017032019',
            'EREF' => '0600003455',
            'MREF' => 'TP1DE01000000013428',
            'CRED' => 'DE79ZZZ00000465117',
        ]);
        $t->setAccountNumber('DE89512106004222555015');
        $t->setBankCode('BNPADEFFXXX');

        $m = new ReflectionMethod(FinTsService::class, 'buildPurpose');
        $m->setAccessible(true);
        $purpose = $m->invoke(new FinTsService(), $t);

        $this->assertSame(
            '/INV/0227561766 6.6.2026/ / SHOP-ABRECHNUNG 3322629128 06.06.26 LL SE/0017032019 '
            . 'EREF: 0600003455 MREF: TP1DE01000000013428 CRED: DE79ZZZ00000465117 '
            . 'IBAN: DE89512106004222555015 BIC: BNPADEFFXXX',
            $purpose
        );
    }

    public function test_nur_svwz_ohne_referenzen(): void
    {
        $t = new Transaction();
        $t->setStructuredDescription(['SVWZ' => 'Nur ein Text']);

        $m = new ReflectionMethod(FinTsService::class, 'buildPurpose');
        $m->setAccessible(true);

        $this->assertSame('Nur ein Text', $m->invoke(new FinTsService(), $t));
    }
}
