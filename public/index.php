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
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
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
            font-size: 20px;
            font-weight: bold;
        }

        .time {
            font-size: 14px;
            color: #666;
        }

        .empty {
            color: #888;
        }
    </style>
</head>

<body>

<h1>Visual Compare Reports</h1>

<div class="grid">

    <?php if (empty($files)): ?>
        <div class="empty">No reports found</div>
    <?php else: ?>

        <?php foreach ($files as $file):
            $info = formatDateTime($file);
            $url = '/dashboard/' . basename($file);
            ?>

            <a class="card" href="<?= $url ?>">
                <div class="date"><?= $info['date'] ?></div>
                <div class="time"><?= $info['time'] ?></div>
            </a>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>
