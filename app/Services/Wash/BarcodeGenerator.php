<?php

namespace App\Services\Wash;

/**
 * Erzeugt EAN-13-Barcodes als PNG-Data-URI (ohne externe Abhängigkeit, via GD).
 * Funktioniert im Browser und im DomPDF-Export. Zum Scannen direkt vom
 * Bildschirm oder Ausdruck in die Kasse.
 */
class BarcodeGenerator
{
    private const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];

    private const G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];

    private const R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];

    // Parität der ersten 6 Ziffern, je nach erster Ziffer (L/G).
    private const PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

    /** EAN-13 als PNG-Data-URI, oder null bei ungültiger/fehlender EAN. */
    public static function ean13DataUri(?string $ean, int $module = 2, int $height = 42): ?string
    {
        $bits = self::encode($ean);
        if ($bits === null || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $quietLeft = 10;
        $quietRight = 7;
        $width = ($quietLeft + strlen($bits) + $quietRight) * $module;

        $im = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, $width, $height, $white);

        $x = $quietLeft * $module;
        for ($i = 0, $n = strlen($bits); $i < $n; $i++) {
            if ($bits[$i] === '1') {
                imagefilledrectangle($im, $x, 0, $x + $module - 1, $height, $black);
            }
            $x += $module;
        }

        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,' . base64_encode((string) $png);
    }

    /** Bitfolge des EAN-13 (13 Ziffern; 12 werden um die Prüfziffer ergänzt). */
    private static function encode(?string $ean): ?string
    {
        $d = preg_replace('/\D/', '', (string) $ean) ?? '';
        if (strlen($d) === 12) {
            $d .= self::checkDigit($d);
        }
        if (strlen($d) !== 13) {
            return null;
        }

        $parity = self::PARITY[(int) $d[0]];
        $bits = '101'; // Start
        for ($i = 1; $i <= 6; $i++) {
            $digit = (int) $d[$i];
            $bits .= $parity[$i - 1] === 'L' ? self::L[$digit] : self::G[$digit];
        }
        $bits .= '01010'; // Mitte
        for ($i = 7; $i <= 12; $i++) {
            $bits .= self::R[(int) $d[$i]];
        }
        $bits .= '101'; // Ende

        return $bits;
    }

    private static function checkDigit(string $d12): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $d12[$i]) * ($i % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }
}
