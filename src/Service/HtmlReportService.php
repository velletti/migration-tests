<?php

namespace App\Service;

class HtmlReportService
{
    public function generate(string $outputFile,
                             array $tests, array $config,
                             string $oldDomain, string $newDomain ,
                             int $totalScore, int $maxTotalScore = 1): void
    {
        $html = '<html><head><meta charset="UTF-8">';
        $html .= '<meta totalScore="' . $totalScore . '" maxTotalScore="' . $maxTotalScore . '" percentage="' . round(($totalScore / $maxTotalScore) * 100, 2) . '" tests="' . count($tests) . '">';
        $html .= '<link rel="stylesheet" href="../styles.css?' . filemtime( $config['projectPublic'] . "/styles.css") . '" media="all"></link>';


        // ✅ JavaScript
        $html .= '<script>
            function toggle(id) {
                var el = document.getElementById(id);
                el.style.display = (el.style.display === "none") ? "block" : "none";
            }

            function showAll() {
                document.querySelectorAll(".test").forEach(el => el.style.display = "block");
            }

            function showFailed() {
                document.querySelectorAll(".test").forEach(el => {
                    if (el.dataset.status === "fail") {
                        el.style.display = "block";
                    } else {
                        el.style.display = "none";
                    }
                });
            }
        </script>';

        $html .= '</head><body><div class="container">';

        $html .= '<h1><a href="/">back </a> URL Compare Report</h1>';

        // ✅ Filter Buttons
        $html .= '<div class="filters">
            <button onclick="showAll()">Show All</button>
            <button onclick="showFailed()">Show Failed</button>
        </div>';

        foreach ($tests as $index => $test) {

            $id = $test['id'] ?? 'N/A';
            $uri = $test['uri'] ?? 'N/A';
            $score = $test['score'] ?? 0;
            $max = $test['max'] ?? 0;

            $status = ($score +1 >= $max) ? 'pass' : 'fail';

            $containerId = "test_" . $index;

            $html .= "<div class='test' data-status='{$status}'>";

            // ✅ Klickbarer Header
            $oldUrl = rtrim($oldDomain, '/') . $uri;
            $newUrl = rtrim($newDomain, '/') . $uri;

            $html .= "<div class='header {$status}' onclick=\"toggle('{$containerId}')\">
                " . ($status === 'pass' ? "✅" : "❌") . " {$id} | {$uri} | passed {$score}/{$max} 
            
                <span class='links'>
                    <a href='{$oldUrl}' target='_blank' onclick='event.stopPropagation()'>
                        <button>OLD</button>
                    </a>
            
                    <a href='{$newUrl}' target='_blank' onclick='event.stopPropagation()'>
                        <button>NEW</button>
                    </a>
                </span>
            </div>";

            // ✅ Viewport Container (initial hidden)
            $html .= "<div class='viewport-container' id='{$containerId}'>";

            foreach ($test['viewports'] as $vp) {

                $relativePathBase = str_replace($config['projectPublic'], '..', $vp['pathBase']);
                $relativePathNew = str_replace($config['projectPublic'], '..', $vp['path']);

                $html .= "<div class='viewport'>";
                $html .= "<div><B>" . $vp['width'] . "x" . $vp['height'] . "</B></div>";

                $html .= "<div>
                    <div>Old</div>
                    <img src='{$relativePathBase}/old.png'>
                </div>";

                $html .= "<div>
                    <div>New</div>
                    <img src='{$relativePathNew}/new.png'>
                </div>";
                if ( file_exists($vp['path'] . '/diff.png')) {
                        $html .= "<div>
                        <div>Diff</div>
                        <img src='{$relativePathNew}/diff.png'>
                    </div>";
                } else {
                     $html .= "<div>
                        <div>Diff</div>
                        <div class='no-diff'> ✅ </div>
                    </div>";
                }

                $html .= "</div>";
            }

            $html .= "</div>"; // viewport-container
            $html .= "</div>"; // test
        }
        $html .= "</div>"; // container
        $html .= '</body></html>';

        file_put_contents($outputFile, $html);
    }
}