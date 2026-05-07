<?php

namespace App\Service;

class DiffService
{
    public function normalizeHtml(string $html): string
    {
        // Entferne dynamische Unterschiede
        $html = preg_replace('/\s+/', ' ', $html); // whitespace normalisieren
        $html = preg_replace('/data-[^=]+="[^"]*"/', '', $html); // data attrs
        $html = preg_replace('/<!--.*?-->/', '', $html); // Kommentare

        return trim($html);
    }

    public function compare(string $oldHtml, string $newHtml): bool
    {
        $old = $this->normalizeHtml($oldHtml);
        $new = $this->normalizeHtml($newHtml);

        return md5($old) === md5($new);
    }

    public function diffScore(string $oldHtml, string $newHtml): float
    {
        similar_text($oldHtml, $newHtml, $percent);
        return $percent; // 0–100
    }
}