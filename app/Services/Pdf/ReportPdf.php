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
    /** Aktueller Drehwinkel des Koordinatensystems (für gestempelte Status-Marken). */
    protected $angle = 0;

    /**
     * Dreht das Koordinatensystem um den Punkt ($x,$y) (Standard-FPDF-Technik
     * über eine Transformationsmatrix). Mit Rotate(0) wird wieder zurückgesetzt;
     * eine offene Drehung schließt _endpage() automatisch am Seitenende.
     */
    public function Rotate(float $angle, float $x = -1.0, float $y = -1.0): void
    {
        if ($x < 0) {
            $x = $this->x;
        }
        if ($y < 0) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf(
                'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy
            ));
        }
    }

    /** Schließt eine evtl. offene Drehung am Seitenende. */
    protected function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

    /**
     * Zeichnet eine gedrehte Status-Marke in Stempel-Optik mittig um ($cx,$cy):
     * farbiger, abgerundeter Rahmen + Text in derselben Farbe (z. B. „BEZAHLT"
     * orange oder „GEBUCHT" petrol). Wird nur in den Bericht gezeichnet.
     *
     * @param  array{0:int,1:int,2:int}  $rgb
     */
    public function statusStamp(string $label, float $cx, float $cy, array $rgb, float $angle = -10.0): void
    {
        $this->SetFont('Helvetica', 'B', 12);
        $padX = 3.2;
        $w = $this->GetStringWidth($label) + 2 * $padX;
        $h = 6.6;
        $x = $cx - $w / 2;
        $y = $cy - $h / 2;

        $this->Rotate($angle, $cx, $cy);
        [$r, $g, $b] = $rgb;
        $this->SetDrawColor($r, $g, $b);
        $this->SetTextColor($r, $g, $b);
        $this->SetLineWidth(0.6);
        $this->roundedRect($x, $y, $w, $h, 1.8, 'D');
        $this->SetXY($x, $y);
        $this->Cell($w, $h, $label, 0, 0, 'C');
        $this->Rotate(0);

        // Zustand zurücksetzen, damit folgende Inhalte unbeeinflusst bleiben.
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
    }

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
