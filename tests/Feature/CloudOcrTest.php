<?php

namespace Tests\Feature;

use App\Services\Ocr\OcrService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CloudOcrTest extends TestCase
{
    public function test_cloud_ocr_deaktiviert_gibt_leer_zurueck(): void
    {
        config(['pendelordner.ocr.cloud.enabled' => false]);
        Storage::fake('belege');
        Storage::disk('belege')->put('x/scan.pdf', '%PDF nur bild');
        $path = Storage::disk('belege')->path('x/scan.pdf');

        $this->assertSame('', (new OcrService())->cloudOcr($path, 'application/pdf'));
    }

    public function test_cloud_ocr_liest_text_ueber_den_dienst(): void
    {
        config([
            'pendelordner.ocr.cloud.enabled' => true,
            'pendelordner.ocr.cloud.api_key' => 'test-key',
            'pendelordner.ocr.cloud.endpoint' => 'https://api.ocr.space/parse/image',
        ]);

        Http::fake([
            'api.ocr.space/*' => Http::response([
                'IsErroredOnProcessing' => false,
                'ParsedResults' => [
                    ['ParsedText' => "Rechnung RE1356294\nBrutto 286,33 EUR"],
                ],
            ], 200),
        ]);

        Storage::fake('belege');
        Storage::disk('belege')->put('x/scan.pdf', '%PDF nur bild');
        $path = Storage::disk('belege')->path('x/scan.pdf');

        $text = (new OcrService())->cloudOcr($path, 'application/pdf');

        $this->assertStringContainsString('RE1356294', $text);
        $this->assertStringContainsString('286,33', $text);
    }

    public function test_cloud_ocr_ueberspringt_zu_grosse_dateien(): void
    {
        config([
            'pendelordner.ocr.cloud.enabled' => true,
            'pendelordner.ocr.cloud.api_key' => 'test-key',
            'pendelordner.ocr.cloud.max_bytes' => 10,
        ]);
        Http::fake();

        Storage::fake('belege');
        Storage::disk('belege')->put('x/gross.pdf', str_repeat('A', 500));
        $path = Storage::disk('belege')->path('x/gross.pdf');

        $this->assertSame('', (new OcrService())->cloudOcr($path, 'application/pdf'));
        Http::assertNothingSent();
    }
}
