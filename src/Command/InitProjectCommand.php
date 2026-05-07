<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'project:init',
    description: 'Interactive setup for project.yaml'
)]

class InitProjectCommand extends Command
{

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Project Configuration Setup');

        // ✅ Domains
        $oldDomain = $io->ask('Old domain (z.B. https://www.allplan.com)');
        $newDomain = $io->ask('New domain (z.B. https://wwwv13.allplan.com.ddev.site)');

        // ✅ Ignore Selectors
        $io->section('Ignore Selectors (empty input to finish)');
        $ignoreSelectors = [];

        while (true) {
            $value = $io->ask('Selector');
            if (empty($value)) {
                break;
            }
            $ignoreSelectors[] = $value;
        }

        // ✅ URIs + scrollTo
        $io->section('URIs (empty URI to finish)');
        $uris = [];

        while (true) {
            $uri = $io->ask('URI');
            if (empty($uri)) {
                break;
            }

            $scrollTo = $io->ask('scrollTo (optional, enter to skip)', null);

            $entry = ['uri' => $uri];

            if (!empty($scrollTo)) {
                $entry['scrollTo'] = $scrollTo;
            }

            $uris[] = $entry;
        }

        // ✅ Defaults
        $config = [
            'project' => [
                'newDomain' => $newDomain,
                'oldDomain' => $oldDomain,
                'instance' => "https://migration-tests.ddev.site" ,

                'ignore' => [
                    'selectors' => $ignoreSelectors
                ],

                'viewports' => [
                    ['width' => 400, 'height' => 2000],
                    ['width' => 1900, 'height' => 2000],
                ],

                'output' => [
                    'reportDir' => 'public/reports',
                    'renderedDir' => 'public/rendered',
                    'dashboardDir' => 'public/dashboard'
                ],

                'uris' => $uris
            ]
        ];

        // ✅ YAML erzeugen
        $yaml = Yaml::dump($config, 3, 2  , Yaml::DUMP_FORCE_DOUBLE_QUOTES_ON_VALUES);

        $projectRoot = dirname(__DIR__, 2);
        $configDir = $projectRoot . '/config';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }

        $file = $configDir . '/project.yaml';
        if ( file_exists($file) ) {
            copy($file , $file . '.bak');
            $io->warning("project.yaml already exists: created $file.bak as backup");
        }
        file_put_contents($file, $yaml);

        $io->success("project.yaml created at: $file");

        return Command::SUCCESS;
    }
}