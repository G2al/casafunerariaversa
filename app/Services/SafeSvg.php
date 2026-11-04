<?php

namespace App\Services;

use DOMDocument;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Validation\ValidationException;

class SafeSvg
{
    public function sanitize(string $bytes): array
    {
        if (strlen($bytes) > 10 * 1024 * 1024) {
            $this->invalid();
        }
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->minify(true);
        $clean = $sanitizer->sanitize($bytes);
        if (! $clean || stripos($clean, '<!DOCTYPE') !== false) {
            $this->invalid();
        }
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $valid = $doc->loadXML($clean, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $doc->documentElement;
        if (! $valid || ! $root || $root->localName !== 'svg' || $doc->getElementsByTagName('*')->length > 20000) {
            $this->invalid();
        }
        foreach (iterator_to_array($doc->getElementsByTagName('*')) as $node) {
            if (in_array(strtolower($node->localName), ['script', 'foreignobject', 'animate', 'animatetransform', 'animatemotion', 'set'], true)) {
                $node->parentNode?->removeChild($node);

                continue;
            }
            if ($node->localName === 'a') {
                while ($node->firstChild) {
                    $node->parentNode->insertBefore($node->firstChild, $node);
                } $node->parentNode?->removeChild($node);

                continue;
            }
            if ($node->localName === 'image' && ! $this->embeddedImage($node->getAttribute('href') ?: $node->getAttributeNS('http://www.w3.org/1999/xlink', 'href'))) {
                $node->parentNode?->removeChild($node);

                continue;
            }
            if ($node->localName === 'style' && ! $this->localCss($node->textContent)) {
                $node->parentNode?->removeChild($node);

                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attr) {
                $name = strtolower($attr->localName);
                $value = trim($attr->value);
                if (str_starts_with($name, 'on') || in_array($name, ['src', 'srcset', 'base'], true) || ($name === 'href' && ! preg_match('/^#[a-zA-Z_][\w:.-]*$/D', $value) && ! ($node->localName === 'image' && $this->embeddedImage($value))) || ! $this->localCss($value)) {
                    $node->removeAttributeNode($attr);
                }
            }
        }
        $box = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));
        $view = count($box) === 4 && count(array_filter($box, 'is_numeric')) === 4 ? array_map('floatval', $box) : null;
        if ($view && (! is_finite(array_sum($view)) || $view[2] <= 0 || $view[3] <= 0)) {
            $this->invalid();
        }
        $width = $this->length($root->getAttribute('width'));
        $height = $this->length($root->getAttribute('height'));
        $width ??= $height && $view ? $height * $view[2] / $view[3] : ($view[2] ?? 300);
        $height ??= $view ? $width * $view[3] / $view[2] : 150;
        if (! is_finite($width) || ! is_finite($height) || $width <= 0 || $height <= 0) {
            $this->invalid();
        }
        if (! $view) {
            $root->setAttribute('viewBox', '0 0 '.$width.' '.$height);
        }
        $scale = min(1, 3508 / max($width, $height));
        $width = max(1, round($width * $scale, 3));
        $height = max(1, round($height * $scale, 3));
        $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        $root->setAttribute('width', (string) $width);
        $root->setAttribute('height', (string) $height);

        return ['bytes' => $doc->saveXML($root), 'width' => $width, 'height' => $height];
    }

    private function localCss(string $value): bool
    {
        // Preserve local gradients/clip paths; disallow external resources, CSS escapes and directives.
        $value = preg_replace('/url\(\s*[\x22\x27]?(#[a-zA-Z_][\w:.-]*)[\x22\x27]?\s*\)/i', '', $value);

        return ! preg_match('/[\\\\@]|url\s*\(|(?:expression|image-set|image|src)\s*\(/i', $value);
    }

    private function embeddedImage(string $value): bool
    {
        if (! preg_match('~^data:image/(png|jpeg);base64,([A-Za-z0-9+/=\s]+)$~D', $value, $m)) {
            return false;
        }
        $bytes = base64_decode($m[2], true);
        if (! $bytes) {
            return false;
        }
        try {
            app(PrivateIdentityDocument::class)->validateBytes($bytes, $m[1]);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function length(string $value): ?float
    {
        if (! preg_match('/^([0-9]+(?:\.[0-9]+)?|\.[0-9]+)(px|pt|mm|cm|in)?$/i', trim($value), $m)) {
            return null;
        }

        return (float) $m[1] * match (strtolower($m[2] ?? 'px')) {
            'pt' => 96 / 72,'mm' => 96 / 25.4,'cm' => 96 / 2.54,'in' => 96,default => 1
        };
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['upload' => 'SVG non valido o troppo complesso. Usa un file SVG valido, massimo 10 MB.']);
    }
}
