<?php

namespace Tests\Feature;

use App\Services\Wash\BarcodeGenerator;
use Tests\TestCase;

class WashBarcodeTest extends TestCase
{
    public function test_ean13_liefert_gueltiges_png(): void
    {
        $uri = BarcodeGenerator::ean13DataUri('4003116482070');
        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $png = base64_decode(substr($uri, strlen('data:image/png;base64,')));
        $info = getimagesizefromstring($png);
        $this->assertNotFalse($info);
        $this->assertSame('image/png', $info['mime']);
        $this->assertGreaterThan(100, $info[0]); // Breite plausibel
    }

    public function test_ungueltige_ean_liefert_null(): void
    {
        $this->assertNull(BarcodeGenerator::ean13DataUri('123'));
        $this->assertNull(BarcodeGenerator::ean13DataUri(null));
        $this->assertNull(BarcodeGenerator::ean13DataUri(''));
    }

    public function test_zwoelf_ziffern_werden_um_pruefziffer_ergaenzt(): void
    {
        // 400311640072 + Prüfziffer 2 = 4003116400722
        $this->assertNotNull(BarcodeGenerator::ean13DataUri('400311640072'));
    }
}
