<?php

namespace App\Command;

use App\Service\ConfigLoader;
use App\Service\DiffService;
use App\Service\RenderService;
use App\Service\ImageDiffService;
use App\Service\HtmlReportService;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'compare:run',
    description: 'Compare URLs between old and new domain'
)]

class CompareRunCommand extends Command
{

    // 2. Option in configure() hinzufügen
    protected function configure(): void
    {
        $this->addArgument(
            'project',
            InputArgument::OPTIONAL,
            'Projektname, z.B. "foo" für project_foo.yaml'
        );
        $this->addOption(
            'tests',
            null,
            InputOption::VALUE_OPTIONAL,
            'Komma separierte Liste von Test IDs, z.B. 1,3,7 (default: alle Tests)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $diffService = new DiffService();
        $imageDiff = new ImageDiffService();

        $projectRoot = dirname(__DIR__, 2);

        $project = $input->getArgument('project');
        if ($project) {
            $configFile = $projectRoot . '/config/project_' . (string)trim($project) . '.yaml';
            if (!file_exists($configFile)) {
                $io->error('Konfigurationsdatei nicht gefunden: ' . $configFile);
                return Command::FAILURE;
            }
        } else {
            // Am Anfang von execute() einfügen, vor $configLoader:
            $configFiles = glob($projectRoot . '/config/project*.yaml');
            if (count($configFiles) > 1) {
                $choices = array_map(fn($f) => basename($f), $configFiles);
                $selected = $io->choice('Mehrere Projekt-Konfigurationen gefunden. Welche verwenden?', $choices, 0);
                $configFile = $projectRoot . '/config/' . $selected;
            } elseif (count($configFiles) === 1) {
                $configFile = $configFiles[0];
            } else {
                $io->error('Keine project*.yaml Datei im config-Ordner gefunden.');
                return Command::FAILURE;
            }
        }

// Dann $configLoader wie folgt anpassen:
        $configLoader = new ConfigLoader($configFile);


        $config = $configLoader->getProject();
        $config['projectRoot'] = $projectRoot;
        $config['projectPublic'] = $projectRoot . '/' . ($config['output']['publicDir'] ?? 'public');

        $ignoreSelectors = $config['ignore']['selectors'] ?? [];

        $oldDomain = rtrim($config['oldDomain'], '/');
        $newDomain = rtrim($config['newDomain'], '/');
        $uris = $config['uris'] ?? [];

        $reportDir = $projectRoot . '/' . ($config['output']['reportDir'] ?? 'public/reports');
        $dashBoardDir = ($config['output']['dashboardDir'] ?? 'public/dashboard');
        $dashBoardUri = trim($config['instance'] , "/") . "/dashboard" ;

        $dashBoardDir = $projectRoot . '/' . $dashBoardDir ;
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0777, true);
        }
        if (!is_dir($dashBoardDir)) {
            mkdir($dashBoardDir, 0777, true);
        }
        $baseDir = $projectRoot . '/' . ($config['output']['baseDir'] ?? 'public/base');
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        $renderService = new RenderService();

        $viewports = $config['viewports'] ?? [];
        $renderBaseDir = $projectRoot . '/' . ($config['output']['renderedDir'] ?? 'public/rendered');

        $runDir = $renderBaseDir . '/' . date('Y-m-d_H-i-s');
        $publicRunDir = str_replace([$projectRoot , $config['output']['publicDir'] ], ['' , ''], $runDir);
        $publicRunDir = $config['instance'] . trim( str_replace( "//" , "/" , $publicRunDir ) , "/" )  ;
        if (!is_dir($runDir)) {
            mkdir($runDir, 0777, true);
        }



        // Guzzle Client
        $client = new Client([
            'timeout' => 30,
            'verify' => false
        ]);

        // Report vorbereiten
        $timestamp = date('Y-m-d_H-i-s');
        $reportFile = $reportDir . "/report_$timestamp.txt";

        $reportLines = [];

        $testsOption = $input->getOption('tests');
        $testsToRun = null;
        $totalTests = count($uris);
        if (!empty($testsOption)) {
            $testsToRun = array_map('intval', explode(',', $testsOption));
            $totalTests = count($testsToRun);
            $io->text("Running only tests: " . implode(', ', $testsToRun));
        }



        $totalScore = 0;
        $maxTotalScore = 0;

        $io->title('URL Compare Tool - config: ' . $configFile  );

        $reportData = [];

        $oldDomainAuth = $config['oldDomainBasicAuth'] ?? null;
        $oldOptions = [];
        if ($oldDomainAuth) {
           $oldOptions['auth'] = explode(':', $oldDomainAuth, 2);
        }

        $newDomainAuth = $config['newDomainBasicAuth'] ?? null;
        $newOptions = [];
        if ($newDomainAuth) {
            $newOptions['auth'] = explode(':', $newDomainAuth, 2);
        }


        foreach ($uris as $entry) {

            $testId = $entry['key'] ?? null;
            if ($testId === null) {
                continue; // Überspringen, falls kein key vorhanden
            }

            // Prüfen, ob dieser Test ausgeführt werden soll
            if ($testsToRun !== null && !in_array($testId, $testsToRun, true)) {
                continue;
            }

            $baseTestDir = $baseDir . '/test_' . str_pad($testId, 3, '0', STR_PAD_LEFT);
            if (!is_dir($baseTestDir)) {
                mkdir($baseTestDir, 0777, true);
            }
            $baseOldImg = $baseTestDir . '/old.png';
            $baseOldHtml = $baseTestDir . '/old.html';

            if (is_string($entry)) {
                $uri = $entry;
                $scrollTo = null;
            } else {
                $uri = $entry['uri'] ?? '';
                $scrollTo = $entry['scrollTo'] ?? null;
            }

            $oldUrl = $renderService->addBasicAuthToUrl( $oldDomain . $uri , $oldDomainAuth);
            $newUrl = $renderService->addBasicAuthToUrl( $newDomain . $uri , $newDomainAuth);

            if ( $totalTests ) {
                $io->section("Testing [$testId/$totalTests]: $uri");
            } else {
                $io->section("Testing [$testId]:");
            }


            $maxScore = count($viewports) * 2 + 3; // 3 Checks + 2 Punkte pro Viewport
            $score = 0;

            $testData = [
                'id' => str_pad($testId, 3, '0', STR_PAD_LEFT),
                'uri' => $uri,
                'score' => $score,
                'max' => $maxScore,
                'viewports' => []
            ];
            if (!file_exists($baseOldImg) || !file_exists($baseOldHtml)) {
                // Nur dann Request an oldDomain
                try {

                    $oldResponse = $client->get($oldUrl , $oldOptions);
                    $oldStatus = $oldResponse->getStatusCode();
                    $oldHtml = (string)$oldResponse->getBody();

                    $oldHtml = str_replace($newDomain, "", $oldHtml);
                    $oldHtml = str_replace($oldDomain, "", $oldHtml);
                    if ($testId) {
                        $io->text("Testing  $oldUrl → Status: $oldStatus" . " length: " . strlen($oldHtml));
                    }
                    file_put_contents($baseOldHtml, $oldHtml);

                } catch (\Exception $e) {
                    $oldStatus = 500;
                    $oldHtml = '';
                    $io->warning("Old URL failed: $oldUrl" . " - Status: ($oldStatus) - " . $e->getMessage());
                }
            } else {
                $oldStatus = 404;
                $oldHtml = file_get_contents($baseOldHtml);
                if ($oldHtml) {
                    $oldStatus = 200;
                    $io->text("Using cached old Response for $oldUrl → Status: $oldStatus" . " length: " . strlen($oldHtml));
                }
            }

            try {

                $newResponse = $client->get($newUrl , $newOptions);
                $newStatus = $newResponse->getStatusCode();
                $newHtml = (string)$newResponse->getBody();
                if (str_contains($newHtml, '<title>Reports Dashboard</title>')) {
                    $io->error("New URL is not accessable and is only able to read the Dashboard instead of: $newUrl");
                    return Command::FAILURE;
                }
                echo "New HTML length before cleanup: " . strlen($newHtml) . "\n";
                $newHtml = str_replace($oldDomain , "" , $newHtml);
                $newHtml = str_replace($newDomain , "" , $newHtml);
                if ( $testId ) {
                    $io->text("Testing  $newUrl → Status: $newStatus" . " length: " . strlen($newHtml) );
                }
            } catch (\Exception $e) {
                $newStatus = 0;
                $newHtml = '';
                $io->warning("New URL failed: $newUrl");
            }

            // ✅ Check 1: Status Code
            if ($oldStatus === $newStatus) {
                $score++;
            }

            $testDir = $runDir . "/test_" . str_pad($testId, 3, '0', STR_PAD_LEFT);
            if (!is_dir($testDir)) {
                mkdir($testDir, 0777, true);
            }
            // ✅ Check 2: HTML Länge
            if ($diffService::compareLength($oldHtml, $newHtml)) {
                $score++;

            } else {
                $contentType = "HTML";
                if ( $oldHtml && str_starts_with($oldHtml, '{"id":')) {
                    $diff = $diffService->diffJson($oldHtml, $newHtml);
                    $contentType = "JSON";
                } else {
                    $diff = $diffService->diffHtml($oldHtml, $newHtml);

                }
                file_put_contents($testDir . "/diff.txt" , json_encode($diff, JSON_PRETTY_PRINT));
                $testData['diffUrl'] = $publicRunDir . "/test_" . str_pad($testId, 3, '0', STR_PAD_LEFT) .  "/diff.txt" ;
                if ( count( $diff) < 6  ) {
                    $score++;
                    $io->text("    ? $contentType differs (similarity: " . count($diff) . " differences, " . $diffService->diffScore($oldHtml, $newHtml) . "% similarity)");
                } else {
                    $io->text("    ✖ $contentType differs (similarity: " . $diffService->diffScore($oldHtml, $newHtml) . "%)");
                }


            }
            $diffNoTags= $diffService->diffScoreText($oldHtml, $newHtml);
            if ( $diffNoTags > 95 ) {
                $score++;
                $io->text("    ? $contentType differs but text (without html tags) content is very similar (similarity: " . $diffNoTags . "%)");
            } else {
                $io->text("    ✖ $contentType differs significantly in text content (similarity: " . $diffNoTags . "%)");
            }






            foreach ($viewports as $viewport) {

                $width = $viewport['width'];
                $height = $viewport['height'];

                $io->text("  → Screenshot {$width}x{$height}");


                $baseTestDir = $baseDir . "/test_" . str_pad($testId, 3, '0', STR_PAD_LEFT);

                $viewportDir = $testDir . "/{$width}";
                $baseViewportDir = $baseTestDir . "/{$width}";
                $diffImgPath = $viewportDir . '/diff.png';

                if (!is_dir($viewportDir)) {
                    mkdir($viewportDir, 0777, true);
                }
                if (!is_dir($baseViewportDir)) {
                    mkdir($baseViewportDir, 0777, true);
                }

                $oldImg = $baseViewportDir . '/old.png';
                $newImg = $viewportDir . '/new.png';

                if ( file_exists($oldImg) ) {
                    $io->text("    Using cached screenshots for viewport {$width}x{$height}");
                    $oldOk = true;
                } else {
                    $oldOk = $renderService->screenshot($oldUrl, $width, $height, $oldImg, $ignoreSelectors, $scrollTo, ($config['oldDomainBasicAuth'] ?? null));
                }
                $newOk = $renderService->screenshot($newUrl, $width, $height, $newImg, $ignoreSelectors, $scrollTo, ($config['newDomainBasicAuth'] ?? null));

                if ($oldOk && $newOk && file_exists($oldImg) && file_exists($newImg)) {

                    $percent = $imageDiff->compare($oldImg, $newImg);

                    if ($percent > 97) {
                        $score++;
                        $score++;
                        $io->text("    ✔ MATCH ({$percent}%)");
                    } elseif ($percent > 93) {
                        $score++;
                        $io->text("    ? DIFF ({$percent}%)");
                        $imageDiff->createDiffImage($oldImg, $newImg, $diffImgPath);
                    } else  {
                        $io->text("    ✖ WRONG ({$percent}%)");

                        $imageDiff->createDiffImage($oldImg, $newImg, $diffImgPath);

                    }

                } else {
                    $io->warning("    ⚠ Screenshot failed");
                }


                $line = sprintf(
                    "%03d | %s | passed %d/%d",
                    $testId,
                    $uri,
                    $score,
                    $maxScore
                );

                $testData['viewports'][] = [
                    'width' => $width,
                    'height' => $height,
                    'path' => $viewportDir,
                    'pathBase' => $baseViewportDir
                ];
                $testData['score'] = $score;


            }
            $totalScore += $score;
            $maxTotalScore += $maxScore;

            $reportData[] = $testData;


            $io->text($line);
            $reportLines[] = $line;

        }
        $io->section("Result");
        $line = sprintf(
            "%03d | %s | passed %d/%d",
            "-",
            "TOTAL",
            $totalScore,
            $maxTotalScore
        );
        $io->text($line);
        $reportLines[] = $line;

        // Report speichern
        file_put_contents(
            $reportFile,
            implode(PHP_EOL, $reportLines)
        );

        $io->success("Report written to: $reportFile");


        $htmlReport = new HtmlReportService();

        $htmlReportFile = $dashBoardDir . "/report_$timestamp.html";

        $htmlReport->generate(
            $htmlReportFile,
            $reportData,
            $config,

            $oldDomain,
            $newDomain,
            $totalScore,
            $maxTotalScore

        );

        $io->success("HTML Report: " . $dashBoardUri. "/report_$timestamp.html");
        $io->success("Dashboard: " . $config['instance'] . "index.php?" . time());


        return Command::SUCCESS;
    }
}
