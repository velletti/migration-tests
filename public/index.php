<?php

$configDir = __DIR__ . '/../config';
$dashboardDir = '/dashboard';

// Alle YAML-Projektdateien finden
$projectFiles = glob($configDir . '/*.yaml');

// Hilfsfunktion: YAML laden (benötigt ext-yaml oder symfony/yaml)
function loadYaml($file)
{
    if (function_exists('yaml_parse_file')) {
        return yaml_parse_file($file);
    } elseif (class_exists('\Symfony\Component\Yaml\Yaml')) {
        return \Symfony\Component\Yaml\Yaml::parseFile($file);
    }
    return [];
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Projects Dashboard</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; margin: 0; padding: 20px; }
        h1 { margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 15px; }
        .card {
            display: block;
            text-decoration: none;
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: left;
            color: #333;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.25); }
        .project { font-size: 22px; font-weight: bold; margin-bottom: 8px; }
        .domains { margin-bottom: 10px; }
        .runs { color: #888; }
        .empty { color: #888; }
    </style>
</head>
<body>
<h1>Visual Compare Projects</h1>
<div class="grid">
    <?php
    if (empty($projectFiles)): ?>
        <div class="empty">Keine Projekte gefunden</div>
    <?php
    else:
        foreach ($projectFiles as $file):
            $projectName = str_replace( "project_" , "" , basename($file, '.yaml') );
            $data = loadYaml($file);
            // Domains auslesen (Passe ggf. die Keys an dein YAML-Schema an)
            $oldDomain = ($data['project']['oldDomain'] ?? "-" ) ;
            $newDomain = ($data['project']['newDomain'] ?? "-" )  ;

            // Testläufe zählen (Ordner wie dashboard/PROJECTNAME_*)
            $runs = glob( "../" . $data['project']['output']['dashboardDir'] . '/*');
            $runCount = $runs ? count($runs) : 0;

            // Link zum Dashboard (z.B. dashboard/PROJECTNAME_latest.html oder dashboard/PROJECTNAME_*)
            $dashboardLink = $projectName . "/index.php"  ;
            ?>
            <a class="card" href="<?= htmlspecialchars($dashboardLink) ?>">
                <div class="project"><?= htmlspecialchars($projectName) ?></div>
                <div class="domains">
                    <strong>Alt:</strong> <?= htmlspecialchars($oldDomain) ?><br>
                    <strong>Neu:</strong> <?= htmlspecialchars($newDomain) ?>
                </div>
                <div class="runs"><?= $runCount ?> Testläufe</div>
            </a>
        <?php
        endforeach;
    endif;
    ?>
</div>
</body>
</html>
