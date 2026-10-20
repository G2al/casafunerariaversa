<?php

namespace App\Services;

use Dompdf\Dompdf;
use Illuminate\Validation\ValidationException;

class ManifestLayout
{
    /** Coordinate in punti PDF; ogni riga è misurata con gli stessi font incorporati nel PDF. */
    public function fit(array $data): array
    {
        $settings = $data['company'];
        $left = (float) ($settings['safe_left'] ?? config('funeral.safe_area.left'));
        $top = (float) ($settings['safe_top'] ?? config('funeral.safe_area.top'));
        $width = (float) ($settings['safe_width'] ?? config('funeral.safe_area.width'));
        $height = (float) ($settings['safe_height'] ?? config('funeral.safe_area.height'));
        if ($left < 23 || $top < 2 || $width < 30 || $height < 30 || $left + $width > 98 || $top + $height > 98) {
            throw ValidationException::withMessages(['manifest' => 'Zona sicura non valida: regola le impostazioni dell’agenzia.']);
        }
        // Cornice e zona sicura sono relative al rettangolo stampabile, con 5 mm di margine.
        $page = ['margin' => 14.17, 'width' => 813.55, 'height' => 566.94];
        $area = ['x' => $page['margin'] + $page['width'] * $left / 100, 'y' => $page['margin'] + $page['height'] * $top / 100, 'w' => $page['width'] * $width / 100, 'h' => $page['height'] * $height / 100];
        $pdf = new Dompdf;
        $metrics = $pdf->getFontMetrics();
        $normal = $metrics->getFont('DejaVu Serif', 'normal');
        $bold = $metrics->getFont('DejaVu Serif', 'bold');
        $blocks = [[$data['intro'], 16, false], [$data['name'], 36, true], [$data['age'] !== null ? 'di anni '.$data['age'] : '', 17, true], [$data['family_text'], 17, false], [$data['viewing'], 15, false], [$data['transfer'], 15, false], [$data['ceremony'], 15, false], [$data['closing'], 13, true], [$data['place_date'], 11, false], [trim(($settings['trade_name'] ?? $settings['legal_name'] ?? '').' '.($settings['phone'] ?? '').' '.($settings['email'] ?? '')), 10, false]];
        for ($step = 0; $step <= 9; $step++) {
            $scale = 1 - $step * 0.04;
            $y = $area['y'];
            $lines = [];
            foreach ($blocks as $index => [$text, $size, $isBold]) {
                if (! $text) {
                    continue;
                }
                $size *= $scale;
                $font = $isBold ? $bold : $normal;
                $lineWidth = $area['w'] - (($data['photo'] && $index <= 2) ? 88 : 0);
                $wrapped = [];
                foreach (preg_split('/\R/u', $text) as $paragraph) {
                    $line = '';
                    foreach (preg_split('/\s+/u', trim($paragraph)) as $word) {
                        if ($line !== '' && $metrics->getTextWidth(rtrim($line).' '.$word, $font, $size) > $lineWidth) {
                            $wrapped[] = trim($line);
                            $line = '';
                        }
                        // Divide anche una parola o cognome senza spazi che supera la larghezza.
                        foreach (mb_str_split($word) as $char) {
                            if ($metrics->getTextWidth($line.$char, $font, $size) > $lineWidth) {
                                $wrapped[] = trim($line);
                                $line = '';
                            }
                            $line .= $char;
                        }
                        if ($metrics->getTextWidth($line.' ', $font, $size) <= $lineWidth) {
                            $line .= ' ';
                        }
                    }
                    if (trim($line) !== '') {
                        $wrapped[] = trim($line);
                    }
                }
                foreach ($wrapped as $line) {
                    $lines[] = ['text' => $line, 'x' => $area['x'] + (($data['photo'] && $index <= 2) ? 88 : 0), 'y' => $y, 'width' => $lineWidth, 'size' => $size, 'bold' => $isBold];
                    $y += $size * 1.5;
                }
                $y += 7 * $scale;
                if ($index === 2 && $data['photo']) {
                    $y = max($y, $area['y'] + 100);
                }
            }
            $logoHeight = $data['logo'] ? 30 : 0;
            if ($y + $logoHeight <= $area['y'] + $area['h']) {
                return ['area' => $area, 'page' => $page, 'lines' => $lines, 'scale' => $scale, 'logo_y' => $y];
            }
        }
        throw ValidationException::withMessages(['manifest' => 'Il testo supera lo spazio disponibile. Accorcia l’annuncio o ometti una sezione facoltativa.']);
    }
}
