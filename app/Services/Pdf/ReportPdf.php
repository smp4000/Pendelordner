<?php

namespace App\Services\Pdf;

use setasign\Fpdi\Fpdi;

/**
 * FPDI-Erweiterung für den Steuerberater-Bericht. Ergänzt ein abgerundetes
 * Rechteck, um den Beleg-Stempel modern (Pillen-Form) zeichnen zu können.
 * FPDF/FPDI bringen von Haus aus nur eckige Rechtecke mit.
 */
class ReportPdf extends Fpdi
{
    /**
     * Zeichnet ein Rechteck mit abgerundeten Ecken.
     *
     * @param  string  $style  'F' = gefüllt, 'D' = Rand, 'FD' = beides
     */
    public function roundedRect(float $x, float $y, float $w, float $h, float $r, string $style = 'F'): void
    {
        $k = $this->k;
        $hp = $this->h;

        $op = match ($style) {
            'F' => 'f',
            'FD', 'DF' => 'B',
            default => 'S',
        };

        $arc = $r * (4 / 3) * (sqrt(2) - 1);

        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->arcTo($xc + $arc, $yc - $r, $xc + $r, $yc - $arc, $xc + $r, $yc);

        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->arcTo($xc + $r, $yc + $arc, $xc + $arc, $yc + $r, $xc, $yc + $r);

        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->arcTo($xc - $arc, $yc + $r, $xc - $r, $yc + $arc, $xc - $r, $yc);

        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->arcTo($xc - $r, $yc - $arc, $xc - $arc, $yc - $r, $xc, $yc - $r);

        $this->_out($op);
    }

    /** Bézier-Hilfslinie für die abgerundeten Ecken. */
    private function arcTo(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $k = $this->k;
        $h = $this->h;

        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $k, ($h - $y1) * $k,
            $x2 * $k, ($h - $y2) * $k,
            $x3 * $k, ($h - $y3) * $k
        ));
    }
}
