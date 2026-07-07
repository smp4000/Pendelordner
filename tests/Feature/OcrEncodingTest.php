<?php

namespace Tests\Feature;

use App\Services\Ocr\OcrService;
use Tests\TestCase;

class OcrEncodingTest extends TestCase
{
    public function test_latin1_text_wird_nach_utf8_gewandelt(): void
    {
        // "Großmarkt Straße Käse" in Windows-1252 (ß=\xDF, ä=\xE4).
        $latin1 = "Gro\xDFmarkt Stra\xDFe K\xE4se";

        $this->assertFalse(mb_check_encoding($latin1, 'UTF-8'), 'Ausgangstext ist absichtlich kein UTF-8.');

        $utf8 = OcrService::ensureUtf8($latin1);

        $this->assertTrue(mb_check_encoding($utf8, 'UTF-8'), 'Ergebnis muss gültiges UTF-8 sein.');
        $this->assertSame('Großmarkt Straße Käse', $utf8);
    }

    public function test_gueltiges_utf8_bleibt_unveraendert(): void
    {
        $utf8 = 'Großmarkt Straße Käse € é';
        $this->assertSame($utf8, OcrService::ensureUtf8($utf8));
    }
}
