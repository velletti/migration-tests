<?php

namespace App\Service;

class DiffService
{
    public static function diffHtml(array|false|string $oldHtml, array|string $newHtml)
    {
        $old = self::normalizeHtml($oldHtml);
        $new = self::normalizeHtml($newHtml);
        $oldArray = explode("<", $old) ;
        $old = [];
        foreach ($oldArray as $oldItem) {
            $old[] =  trim($oldItem);
        }
        $newArray = explode("<", $new) ;
        $new = [] ;
        foreach ($newArray as $newItem) {
            $new[] = trim($newItem);
        }
        sort($old);
        // now remove duplicates from old and new
        $old = array_unique($old);
        sort($new);
        $new = array_unique($new);

        $diff = array_diff( $old, $new );
        $result = [];
        foreach ($diff as $item) {
            if ( !$item || empty($item)) {
                continue;
            }
            if ( $item == "/div>" || $item == "/h1>" || $item == "/h2>" || $item == "/ul>" || $item == "/title>"  ) {
                continue;
            }

            if ( str_contains($item , "environment")) {
                continue;
            }
            if ( str_contains($item , "apple-touch-icon")) {
                continue;
            }
            if ( str_contains($item , "/typo3temp/assets/js")) {
                continue;
            }
            if ( str_contains($item , "msapplication-")) {
                continue;
            }

            if ( str_contains($item , "/fileadmin/_processed_")) {
                continue;
            }
            if ( str_contains($item , "usercentrics")) {
                continue;
            }
            $result[] = "<" . trim($item);
        }

        return $result;
    }

    public static function normalizeHtml(string $html): string
    {

        // Entferne dynamische Unterschiede
        $html = preg_replace('/\s+/', ' ', $html); // whitespace normalisieren

        $html = preg_replace('/data-[^=]+="[^"]*"/', '', $html); // data attrs
        $html = preg_replace('/<!--.*?-->/', '', $html); // Kommentare
        $html = preg_replace('/html lang="[a-zA-Z-]"/', 'html', $html); // header


        // remove links with hardcoded domain like  a href=\"https://www.allplan.com/blog/\" starting with https://
        $html = preg_replace('/href="https?:\/\/[^"]+"/', 'href="external"', $html);

        // remove /_assets/9685e7d353db6d02460419a5f921e5a3/
        $html = preg_replace('/\/_assets\/[a-z0-9]+\/?/', '/_assets/placeholder/', $html);

        // remove  /fileadmin/_processed_/3/5/csm_Trend_Report_Website_Navi_440x218_874e961930.jpg or png or svg
        $html = preg_replace('/\/fileadmin\/_processed_\/[0-9]+\/[0-9]+\/csm_[^"]+\.(jpg|png|svg)/', '/fileadmin/placeholder.jpg', $html);


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


    public function diffScoreText(string $oldHtml, string $newHtml): float
    {
        $old = self::normalizeHtml($oldHtml);
        $new = self::normalizeHtml($newHtml);
        similar_text(strip_tags($old), strip_tags($new), $percent);
        return $percent; // 0–100
    }

    public function diffJson(array|string $oldHtml, array|string $newHtml)
    {
        $old = json_decode($oldHtml , true) ;
        $new = json_decode($newHtml , true) ;
        if ( !is_array($old) || !is_array($new)) {
            $error[] = "One of the inputs is not a valid JSON array.";
            $error[] = "Old: " . (is_array($old) ? "Array" : "Not an array");
            $error[] = "New: " . (is_array($new) ? "Array" : "Not an array");
            $error[] = __LINE__ ;
            $error[] = __FILE__ ;
            return $error;
        }
        if ( !is_array($old['content']) || !is_array($new['content'])) {
            $error[] = "One of the inputs has not a valid JSON array with content.";
            $error[] = "Old: " . (is_array($old['content']) ? "Array" : "Not an array");
            $error[] = "New: " . (is_array($new['content']) ? "Array" : "Not an array");
            $error[] = __LINE__ ;
            $error[] = __FILE__ ;
            return $error;
        }
        if ( $old === $new && count($new) > 4 ) {
            return [];
        }

        $this->removeLinkRecursive($new['content']);
        $this->removeLinkRecursive($old['content']);
        $diff = $this->arrayDiffRecursive($new, $old);
        return $diff['content'] ?? [];
    }


    function arrayDiffRecursive(array $new, array $old): array {
        $diff = [];

        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $old)) {
                $diff[$key] = $value;
            } else {
                if (is_array($value) && is_array($old[$key])) {
                    $nestedDiff = $this->arrayDiffRecursive($value, $old[$key]);
                    if (!empty($nestedDiff)) {
                        $diff[$key] = $nestedDiff;
                    }
                } elseif ( $value !==  $old[$key]) {
                    if ( is_string($value) && is_string($old[$key]) ) {
                        $value = $this->normalizeHtml($value) ;
                        $oldValue = $this->normalizeHtml($old[$key]) ;
                        if ( $value !== $oldValue ) {
                            $diff[$key] =   $value ;
                        }
                    } else {
                        $diff[$key] =   $value ;
                    }

                }
            }
        }

        return $diff;
    }



    function removeLinkRecursive(array &$array): void {
        foreach ($array as $key => &$value) {
            if ($key === 'link') {
                unset($array[$key]);
            } elseif (is_array($value)) {
                $this->removeLinkRecursive($value);
            }
        }
    }

}