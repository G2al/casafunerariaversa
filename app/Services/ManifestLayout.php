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
        // Postazioni fisse: la foto rimane a sinistra, i testi nella zona libera della cornice.
        $blocks = [
            ['intro', $data['intro'], 0, .065, 15, false, 'DejaVu Serif'],
            ['name', mb_strtoupper($data['name']), .075, .17, 52, true, 'DejaVu Sans'],
            ['spouse', mb_strtoupper($data['spouse_line'] ?? ''), .245, .045, 13, true, 'DejaVu Sans'],
            ['age', $data['age'] !== null ? 'DI ANNI '.$data['age'] : '', .29, .045, 17, true, 'DejaVu Sans'],
            ['family', $data['family_text'], .345, .10, 17, false, 'DejaVu Serif'],
            ['viewing', $data['viewing'], .46, .14, 16, false, 'DejaVu Serif'],
            ['transfer', $data['transfer'], .61, .09, 15, false, 'DejaVu Serif'],
            ['ceremony', $data['ceremony'], .71, .11, 16, false, 'DejaVu Serif'],
            ['closing', $data['closing'], .83, .06, 14, true, 'DejaVu Serif'],
            ['date', $data['place_date'], .90, .045, 10, false, 'DejaVu Serif'],
        ];
        $lines = [];
        foreach ($blocks as [$key, $text, $slot, $slotHeight, $size, $isBold, $family]) {
            if (blank($text)) {
                continue;
            }
            $font = $metrics->getFont($family, $isBold ? 'bold' : 'normal');
            while (true) {
                $wrapped = $this->wrap($text, $font, $size, $area['w'], $metrics);
                if (count($wrapped) * $size * 1.5 <= $area['h'] * $slotHeight && ($key !== 'name' || count($wrapped) <= 2)) {
                    break;
                }
                $size -= .5;
                if ($size < 6) {
                    throw ValidationException::withMessages(['manifest' => 'Il testo supera il blocco '.$key.'. Accorcialo oppure usa l’editor live.']);
                }
            }
            foreach ($wrapped as $index => $line) {
                $lines[] = ['key' => $key, 'text' => $line, 'x' => $area['x'], 'y' => $area['y'] + $area['h'] * $slot + $index * $size * 1.5, 'width' => $area['w'], 'size' => $size, 'bold' => $isBold, 'fontFamily' => $family, 'color' => '#000000'];
            }
        }
        foreach (['Si ringraziano anticipatamente tutti coloro che scriveranno con affetto un', 'pensiero di ricordo su Facebook: necrologiagroaversano'] as $index => $text) {
            $lines[] = ['key' => 'facebook', 'text' => $text, 'x' => $area['x'], 'y' => 546 + $index * 13.5, 'width' => $area['w'], 'size' => 9, 'bold' => false, 'fontFamily' => 'DejaVu Sans', 'color' => '#245994'];
        }
        $portrait = $data['photo'] ? $this->imageSize($data['photo'], 160, 160) : null;
        $logo = $data['logo'] ? $this->imageSize($data['logo'], 140, 38) : null;

        return ['separator' => ['x' => $area['x'], 'y' => $area['y'] + $area['h'] * .45, 'width' => $area['w'], 'height' => 1], 'area' => $area, 'page' => $page, 'lines' => $lines, 'scale' => 1, 'content_y' => $area['y'], 'content_height' => $area['h'],
            'photo' => $portrait ? ['x' => 30 + (160 - $portrait['width']) / 2, 'y' => 38, 'width' => $portrait['width'], 'height' => $portrait['height']] : null,
            'logo' => $logo ? ['x' => 25, 'y' => 540, 'width' => $logo['width'], 'height' => $logo['height']] : null];
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
