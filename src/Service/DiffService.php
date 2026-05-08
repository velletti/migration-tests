<?php

namespace App\Service;

class DiffService
{
    public static function diffHtml(array|false|string $oldHtml, array|string $newHtml)
    {
        $old = self::normalizeHtml($oldHtml);
        $new = self::normalizeHtml($newHtml);
        $diff = array_diff(explode("<", $old), explode("<", $new));
        $result = [];
        foreach ($diff as $item) {
            if ( !$item || empty($item)) {
                continue;
            }
            if ( $item == "/div>" ) {
                continue;
            }

            if ( str_contains($item , "environment")) {
                continue;
            }
            if ( str_contains($item , "usercentrics")) {
                continue;
            }
            $result[] = "<" . $item;
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function normalizeHtml(string $html): string
    {
        // Entferne dynamische Unterschiede
        $html = preg_replace('/\s+/', ' ', $html); // whitespace normalisieren
        $html = preg_replace('/data-[^=]+="[^"]*"/', '', $html); // data attrs
        $html = preg_replace('/<!--.*?-->/', '', $html); // Kommentare
        // remove /_assets/9685e7d353db6d02460419a5f921e5a3/
        $html = preg_replace('/\/_assets\/[a-z0-9]+\/?/', '/_assets/placeholder/', $html);

        // remove timestamps from .css? or .js?   .. "<script src=\"/_assets/placeholder/Js/www.allplan.com/application.min.js?1774366905\">",

            $html = preg_replace('/(\.css|\.js)\?\d+/', '$1?timeStamp', $html);

        return trim($html);
    }

    public static function compareLength(string $oldHtml, string $newHtml): bool
    {
        $old = strlen(self::normalizeHtml($oldHtml));
        $new = strlen(self::normalizeHtml($newHtml));
        if ( abs($old - $new) < 20) {
            return true;
        }
        return md5($old) === md5($new);
    }
    public static function compare(string $oldHtml, string $newHtml): bool
    {
        $old = self::normalizeHtml($oldHtml);
        $new = self::normalizeHtml($newHtml);

        return md5($old) === md5($new);
    }

    public function diffScore(string $oldHtml, string $newHtml): float
    {
        similar_text($oldHtml, $newHtml, $percent);
        return $percent; // 0–100
    }
}