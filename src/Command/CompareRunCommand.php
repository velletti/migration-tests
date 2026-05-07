<?php

namespace App\Command;

use App\Service\ConfigLoader;
use App\Service\DiffService;
use App\Service\RenderService;
use App\Service\ImageDiffService;
use App\Service\HtmlReportService;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CompareRunCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->setName('compare:run')
            ->setDescription('Compare URLs between old and new domain');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $diffService = new DiffService();
        $imageDiff = new ImageDiffService();

        $projectRoot = dirname(__DIR__, 2);

        // Config laden
        $configLoader = new ConfigLoader($projectRoot . '/config/project.yaml');
        $config = $configLoader->getProject();

        $ignoreSelectors = $config['ignore']['selectors'] ?? [];

        $oldDomain = rtrim($config['oldDomain'], '/');
        $newDomain = rtrim($config['newDomain'], '/');
        $uris = $config['uris'] ?? [];

        $reportDir = $projectRoot . '/' . ($config['output']['reportDir'] ?? 'public/reports');
        $dashBoardDir = ($config['output']['dashboardDir'] ?? 'public/dashboard');
        $dashBoardUri = "https://tests.ddev.site" . str_replace("public" , "" ,$dashBoardDir);
        $dashBoardDir = $projectRoot . '/' . $dashBoardDir ;
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0777, true);
        }
        if (!is_dir($dashBoardDir)) {
            mkdir($dashBoardDir, 0777, true);
        }

        $renderService = new RenderService();

        $viewports = $config['viewports'] ?? [];
        $renderBaseDir = $projectRoot . '/' . ($config['output']['renderedDir'] ?? 'public/rendered');

        $runDir = $renderBaseDir . '/' . date('Y-m-d_H-i-s');

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

        $testId = 1;
        $totalTests = count($uris);
        $totalScore = 0;
        $maxTotalScore = 0;

        $io->title('URL Compare Tool');

        $reportData = [];

        foreach ($uris as $entry) {

            if (is_string($entry)) {
                $uri = $entry;
                $scrollTo = null;
            } else {
                $uri = $entry['uri'] ?? '';
                $scrollTo = $entry['scrollTo'] ?? null;
            }

            $oldUrl = $oldDomain . $uri;
            $newUrl = $newDomain . $uri;

            $io->section("Test [$testId]: $uri");


            $maxScore = count($viewports) * 2 + 2; // 2 Checks + 2 Punkte pro Viewport
            $score = 0;

            $testData = [
                'id' => str_pad($testId, 3, '0', STR_PAD_LEFT),
                'uri' => $uri,
                'score' => $score,
                'max' => $maxScore,
                'viewports' => []
            ];
            try {
                $oldResponse = $client->get($oldUrl);
                $oldStatus = $oldResponse->getStatusCode();
                $oldHtml = (string)$oldResponse->getBody();
                $oldHtml = str_replace($newDomain , "" , $oldHtml);
                $oldHtml = str_replace($oldDomain , "" , $oldHtml);
            } catch (\Exception $e) {
                $oldStatus = 0;
                $oldHtml = '';
                $io->warning("Old URL failed: $oldUrl");
            }

            try {
                $newResponse = $client->get($newUrl);
                $newStatus = $newResponse->getStatusCode();
                $newHtml = (string)$newResponse->getBody();
                $newHtml = str_replace($oldDomain , "" , $newHtml);
                $newHtml = str_replace($newDomain , "" , $newHtml);
            } catch (\Exception $e) {
                $newStatus = 0;
                $newHtml = '';
                $io->warning("New URL failed: $newUrl");
            }

            // ✅ Check 1: Status Code
            if ($oldStatus === $newStatus) {
                $score++;
            }

            // ✅ Check 2: HTML Länge
            if (strlen($oldHtml) === strlen($newHtml)) {
                $score++;
            }





            foreach ($viewports as $viewport) {

                $width = $viewport['width'];
                $height = $viewport['height'];

                $io->text("  → Screenshot {$width}x{$height}");

                $testDir = $runDir . "/test_" . str_pad($testId, 3, '0', STR_PAD_LEFT);
                $viewportDir = $testDir . "/{$width}";
                $diffImgPath = $viewportDir . '/diff.png';

                if (!is_dir($viewportDir)) {
                    mkdir($viewportDir, 0777, true);
                }

                $oldImg = $viewportDir . '/old.png';
                $newImg = $viewportDir . '/new.png';

                $oldOk = $renderService->screenshot($oldUrl, $width, $height, $oldImg, $ignoreSelectors, $scrollTo);
                $newOk = $renderService->screenshot($newUrl, $width, $height, $newImg, $ignoreSelectors, $scrollTo);

                if ($oldOk && $newOk && file_exists($oldImg) && file_exists($newImg)) {

                    $percent = $imageDiff->compare($oldImg, $newImg);

                    if ($percent > 97) {
                        $score++;
                        $score++;
                        $io->text("    ✔ MATCH ({$percent}%)");
                    } elseif ($percent > 93) {
                        $score++;
                        $io->text("    ? DIFF ({$percent}%)");
                    } else  {
                        $io->text("    ✖ WRONG ({$percent}%)");

                        // ✅ NEU: Diff-Image erzeugen
                        $imageDiff->createDiffImage($oldImg, $newImg, $diffImgPath);

                    }

                } else {
                    $io->warning("    ⚠ Screenshot failed");
                }
                $totalScore += $score;
                $maxTotalScore += $maxScore;

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
                    'path' => $viewportDir
                ];
                $testData['score'] = $score;

            }
            $reportData[] = $testData;


            $io->text($line);
            $reportLines[] = $line;



            $testId++;
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
            $projectRoot . '/public',

            $oldDomain,
            $newDomain

        );

        $io->success("HTML Report: " . $dashBoardUri. "/report_$timestamp.html");


        return Command::SUCCESS;
    }
}
