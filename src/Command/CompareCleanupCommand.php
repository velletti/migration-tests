<?php

namespace App\Command;

use App\Service\ConfigLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'compare:cleanup',
    description: 'Cleanup old reports and rendered data'
)]
class CompareCleanupCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('latest', null, InputOption::VALUE_OPTIONAL, 'Number of latest runs to keep', 3)
            ->addOption('oldest', null, InputOption::VALUE_OPTIONAL, 'Number of oldest runs to keep', 1)
            ->addOption('all', null, InputOption::VALUE_NONE, 'Delete everything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $projectRoot = dirname(__DIR__, 2);

        $configLoader = new ConfigLoader($projectRoot . '/config/project.yaml');
        $config = $configLoader->getProject();

        $renderedDir = $projectRoot . '/' . $config['output']['renderedDir'];
        $reportDir   = $projectRoot . '/' . $config['output']['reportDir'];
        $dashboardDir   = $projectRoot . '/' . $config['output']['dashboardDir'];
        $baseDir   = $projectRoot . '/' . $config['output']['baseDir'];

        $latest = (int)$input->getOption('latest');
        $oldest = (int)$input->getOption('oldest');
        $deleteAll = $input->getOption('all');

        $io->title('Cleanup Reports & Rendered Data');

        if (!$io->confirm('Proceed with cleanup?', false)) {
            return Command::SUCCESS;
        }


        if ($deleteAll) {
            $io->warning('Deleting ALL rendered and report files');

            $this->deleteAll($renderedDir);
            $this->deleteAll($reportDir);
            $this->deleteAll($dashboardDir);
        //    $this->deleteAll($baseDir);

            $io->success('All files deleted');
            return Command::SUCCESS;
        }
        if ($io->confirm('Clear also base directory?', false)) {
            $io->section("Processing: $baseDir ");
        //    $this->deleteAll($baseDir);
        }

        $this->cleanDirectory($io, $renderedDir, $latest, $oldest);
        $this->cleanDirectory($io, $reportDir, $latest, $oldest);
        $this->cleanDirectory($io, $dashboardDir, $latest, $oldest);

        $io->success('Cleanup finished');

        return Command::SUCCESS;
    }

    private function cleanDirectory(SymfonyStyle $io, string $dir, int $latest, int $oldest): void
    {
        if (!is_dir($dir)) {
            $io->warning("Directory not found: $dir");
            return;
        }

        $items = array_filter(glob($dir . '/*'), function ($item) {
            return basename($item)[0] !== '.';
        });

        usort($items, function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $total = count($items);

        $io->section("Processing: $dir ($total items)");

        if ($total <= ($latest + $oldest)) {
            $io->text('Nothing to clean');
            return;
        }

        $toDelete = array_slice($items, $latest, $total - ($latest + $oldest));

        foreach ($toDelete as $item) {
            $this->deleteItem($item);

            $io->text("Deleted: " . basename($item));
        }
    }

    private function deleteAll(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') as $item) {
            $this->deleteItem($item);
        }
    }

    private function deleteItem(string $path): void
    {
        if (is_dir($path)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
            }

            rmdir($path);
        } else {
            unlink($path);
        }
    }
}