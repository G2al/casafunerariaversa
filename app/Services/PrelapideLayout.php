<?php

namespace App\Services;

use Dompdf\Dompdf;

class PrelapideLayout
{
    public function scene(array $data): array
    {
        $base = ['fontSize' => 12, 'fontFamily' => 'DejaVu Sans', 'bold' => true, 'italic' => false, 'color' => '#000000', 'align' => 'center', 'lineHeight' => 1.2, 'locked' => false];
        $elements = [];
        foreach ([[18, 18, 805.89, 559.28], [22, 22, 797.89, 551.28]] as $i => $box) {
            $elements[] = array_replace($base, ['id' => 'border_'.$i, 'type' => 'rect', 'x' => $box[0], 'y' => $box[1], 'width' => $box[2], 'height' => $box[3], 'fill' => 'transparent', 'stroke' => '#000000', 'strokeWidth' => 1]);
        }
        $name = mb_strtoupper(trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')));
        $metrics = (new Dompdf)->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans', 'bold');
        $size = 58;
        while ($size > 6 && $metrics->getTextWidth($name, $font, $size) > 760) {
            $size -= .5;
        }
        $elements[] = array_replace($base, ['id' => 'name', 'type' => 'text', 'text' => $name, 'x' => 40, 'y' => 45, 'width' => 760, 'height' => 120, 'fontSize' => $size]);
        foreach (['birth_date' => 'N. ', 'death_date' => 'D. '] as $key => $label) {
            $elements[] = array_replace($base, ['id' => $key, 'type' => 'text', 'text' => $label.str_replace('/', ' - ', $data[$key] ?? '________________'), 'x' => 335, 'y' => $key === 'birth_date' ? 260 : 370, 'width' => 450, 'height' => 70, 'fontSize' => 40]);
        }
        foreach (['symbol' => [65, 220, 200, 260], 'logo' => [660, 510, 130, 46]] as $asset => [$x,$y,$w,$h]) {
            if ($data[$asset]) {
                $info = getimagesizefromstring(base64_decode(explode(',', $data[$asset], 2)[1]));
                $factor = min($w / $info[0], $h / $info[1]);
                $elements[] = $base + ['id' => $asset, 'type' => 'image', 'asset' => $asset, 'x' => $x + ($w - $info[0] * $factor) / 2, 'y' => $y, 'width' => $info[0] * $factor, 'height' => $info[1] * $factor];
            }
        }

        return ['version' => 1, 'elements' => $elements];
    }
}
