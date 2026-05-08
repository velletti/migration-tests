<?php

$reportDir = __DIR__ . '/dashboard';

// alle HTML Reports holen
$files = glob($reportDir . '/report_*.html');

// nach Datum sortieren (neueste zuerst)
usort($files, function ($a, $b) {
    return filemtime($b) <=> filemtime($a);
});

// Hilfsfunktion für Datum
function formatDateTime($file)
{
    $time = filemtime($file);

    return [
        'date' => date('d.m.', $time),
        'time' => date('H:i', $time)
    ];
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reports Dashboard</title>

    <style>
        body {
            font-family: Arial;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        h1 {
            margin-bottom: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 15px;
        }

        .card {
            display: block;
            text-decoration: none;
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            color: #333;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        }

        .date {
            font-size: 28px;
            font-weight: bold;
        }
        .tests {
            font-size: 14px;
            color: #888;
            margin-top: 20px;
        }

        .time {
            font-size: 14px;
            color: #666;
        }

        .empty {
            color: #888;
        }
        .score {
            font-size: 16px;
            margin: 8px 0 4px 0;
            color: #222;
        }
        .progressbar-bg {
            width: 100%;
            height: 16px;
            background: #eee;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 4px;
        }
        .progressbar-fill {
            height: 100%;
            transition: width 0.4s;
        }
    </style>
</head>

<body>

<h1>Visual Compare Reports</h1>

<div class="grid">

    <?php if (empty($files)): ?>
        <div class="empty">No reports found</div>
    <?php else: ?>

        <?php
// ... (wie gehabt)

        foreach ($files as $file):
            $info = formatDateTime($file);
            $url = '/dashboard/' . basename($file);

            // Meta-Daten auslesen
            $content = file_get_contents($file);
            $meta = [
                'totalScore' => null,
                'maxTotalScore' => null,
                'percentage' => null,
                'tests' => 0
            ];
            if (preg_match('/<meta\s+totalScore="(\d+)"\s+maxTotalScore="(\d+)"\s+percentage="([\d.]+)"\s+tests="(\d+)"/i', $content, $matches)) {
                $meta['totalScore'] = (int)$matches[1];
                $meta['maxTotalScore'] = (int)$matches[2];
                $meta['percentage'] = (float)$matches[3];
                $meta['tests'] = (int)$matches[4];
            }

            // Progressbar-Farbe bestimmen
            $barColor = '#e74c3c'; // rot
            if ($meta['percentage'] >= 75) {
                $barColor = '#27ae60'; // grün
            } elseif ($meta['percentage'] >= 50) {
                $barColor = '#f1c40f'; // gelb
            }
            $barWidth = $meta['percentage'] !== null ? $meta['percentage'] : 0;
            ?>

            <a class="card" href="<?= $url ?>">
                <div class="date"><?= $info['date'] ?></div>
                <div class="time"><?= $info['time'] ?></div>
                <div class="tests"><?= $meta['tests'] . " Tests" ?></div>
                <?php if ($meta['percentage'] !== null): ?>
                    <div class="score">
                        <?= $meta['totalScore'] ?> / <?= $meta['maxTotalScore'] ?> (<?= $meta['percentage'] ?>%)
                    </div>
                    <div class="progressbar-bg">
                        <div class="progressbar-fill" style="width: <?= $barWidth ?>%; background: <?= $barColor ?>;"></div>
                    </div>
                <?php endif; ?>
            </a>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>
