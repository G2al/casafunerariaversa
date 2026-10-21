<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\FontMetrics;
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
        $nameSize = 44;
        while ($nameSize > 22 && count($this->wrap($data['name'], $bold, $nameSize, $area['w'], $metrics)) > 2) {
            $nameSize -= 2;
        }
        // Un solo asse centrale per fotografia, intestazione, annuncio e firma agenzia.
        // Ogni blocco definisce dimensione, peso, respiro successivo e larghezza relativa.
        $blocks = [
            [$data['intro'], 15, false, 9, 1.0],
            [$data['name'], $nameSize, true, 2, 1.0],
            [$data['age'] !== null ? 'di anni '.$data['age'] : '', 17, true, 16, 1.0],
            [$data['family_text'], 16, false, 14, 0.94],
            [$data['viewing'], 14.5, false, 10, 0.94],
            [$data['transfer'], 14.5, false, 10, 0.94],
            [$data['ceremony'], 14.5, false, 18, 0.94],
            [$data['closing'], 12.5, true, 8, 0.94],
            [$data['place_date'], 10, false, 6, 1.0],
            [trim(($settings['trade_name'] ?? $settings['legal_name'] ?? '').' '.($settings['phone'] ?? '').' '.($settings['email'] ?? '')), 10, false, 8, 0.94],
        ];
        $portraitSize = $data['photo'] ? $this->imageSize($data['photo'], 66, 84) : null;
        $logoSize = $data['logo'] ? $this->imageSize($data['logo'], 80, 26) : null;
        for ($step = 0; $step <= 9; $step++) {
            $scale = 1 - $step * 0.04;
            $y = $portraitSize ? ($portraitSize['height'] + 14) * $scale : 0;
            $lines = [];
            $gap = 0;
            foreach ($blocks as [$text, $size, $isBold, $spaceAfter, $relativeWidth]) {
                if (trim($text) === '') {
                    continue;
                }
                $y += $gap;
                $size *= $scale;
                $font = $isBold ? $bold : $normal;
                $lineWidth = $area['w'] * $relativeWidth;
                foreach ($this->wrap($text, $font, $size, $lineWidth, $metrics) as $line) {
                    $lines[] = ['text' => $line, 'x' => $area['x'] + ($area['w'] - $lineWidth) / 2, 'y' => $y, 'width' => $lineWidth, 'size' => $size, 'bold' => $isBold];
                    $y += $size * 1.5;
                }
                $gap = $spaceAfter * $scale;
            }
            $logoY = $y + $gap;
            $contentHeight = $logoSize ? $logoY + $logoSize['height'] * $scale : $y;
            if ($contentHeight <= $area['h']) {
                // Equilibra lo spazio sopra e sotto l'intera composizione, senza buchi quando manca la foto.
                $offset = $area['y'] + ($area['h'] - $contentHeight) / 2;
                foreach ($lines as &$line) {
                    $line['y'] += $offset;
                }
                unset($line);

                return [
                    'area' => $area, 'page' => $page, 'lines' => $lines, 'scale' => $scale,
                    'content_y' => $offset, 'content_height' => $contentHeight,
                    'photo' => $portraitSize ? $this->centerImage($portraitSize, $area, $offset, $scale) : null,
                    'logo' => $logoSize ? $this->centerImage($logoSize, $area, $offset + $logoY, $scale) : null,
                ];
            }
        }
        throw ValidationException::withMessages(['manifest' => 'Il testo supera lo spazio disponibile. Accorcia l’annuncio o ometti una sezione facoltativa.']);
    }

    private function imageSize(string $uri, float $maxWidth, float $maxHeight): array
    {
        $bytes = base64_decode(explode(',', $uri, 2)[1] ?? '', true);
        $size = $bytes !== false ? @getimagesizefromstring($bytes) : false;
        if (! $size) {
            throw ValidationException::withMessages(['manifest' => 'Immagine del manifesto non valida.']);
        }
        $factor = min($maxWidth / $size[0], $maxHeight / $size[1]);

        return ['width' => $size[0] * $factor, 'height' => $size[1] * $factor];
    }

    private function centerImage(array $image, array $area, float $y, float $scale): array
    {
        return ['x' => $area['x'] + ($area['w'] - $image['width'] * $scale) / 2, 'y' => $y, 'width' => $image['width'] * $scale, 'height' => $image['height'] * $scale];
    }

    /** Bilancia le righe, mantenendo insieme date e orari per evitare un «12:00» isolato. */
    private function wrap(string $text, string $font, float $size, float $width, FontMetrics $metrics): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', trim($text)) as $paragraph) {
            preg_match_all('/(?:(?:alle(?:\h+ore)?|ore)\h+\d{1,2}:\d{2}|\d{1,2}\h+\p{L}+\h+\d{4})\p{P}*|\S+/u', $paragraph, $matches);
            $words = [];
            foreach ($matches[0] as $word) {
                if ($metrics->getTextWidth($word, $font, $size) <= $width) {
                    $words[] = $word;

                    continue;
                }
                $part = '';
                foreach (mb_str_split($word) as $char) {
                    if ($metrics->getTextWidth($part.$char, $font, $size) > $width && $part !== '') {
                        $words[] = $part;
                        $part = '';
                    }
                    $part .= $char;
                }
                if ($part !== '') {
                    $words[] = $part;
                }
            }
            $count = count($words);
            $costs = [$count => 0.0];
            $breaks = [];
            for ($start = $count - 1; $start >= 0; $start--) {
                $costs[$start] = INF;
                $candidate = '';
                for ($end = $start; $end < $count; $end++) {
                    $candidate .= ($candidate === '' ? '' : ' ').$words[$end];
                    $measured = $metrics->getTextWidth($candidate, $font, $size);
                    if ($measured > $width) {
                        break;
                    }
                    $cost = 1000 + (($width - $measured) / $width) ** 2 + $costs[$end + 1];
                    if ($cost < $costs[$start]) {
                        $costs[$start] = $cost;
                        $breaks[$start] = $end + 1;
                    }
                }
            }
            for ($start = 0; $start < $count; $start = $end) {
                $end = $breaks[$start];
                $lines[] = implode(' ', array_slice($words, $start, $end - $start));
            }
        }

        return $lines;
    }
}
