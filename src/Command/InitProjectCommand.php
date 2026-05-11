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
        $oldDomain = $io->ask('Old domain (z.B. https://www.allplan.com)' , "https://www.allplan.com");
        // htaccess User/Passwort
        $oldDomainAuth = $io->ask('htaccess user:passwort für OLD domain (optional, user:pass)', "dev:dev");

        $newDomain = $io->ask('New domain (z.B. https://wwwv13.allplan.com.ddev.site)');
        $newDomainAuth = $io->ask('htaccess user:passwort für NEW domain (optional, user:pass)', null);

// Nach den Domain-Inputs:
        $publicDir = $io->ask('Output PublicDir', 'public');
        $basePath = $io->ask('BasePath', '/');

// Output-Subfolder-Definitionen
        $outputSubfolders = [
            'reportDir'    => 'report',
            'renderedDir'  => 'rendered',
            'dashboardDir' => 'dashboard',
            'publicDir'    => '',
            'baseDir'      => 'base'
        ];

// Output-Array dynamisch bauen
        $output = [];
        foreach ($outputSubfolders as $key => $sub) {
            $output[$key] = rtrim($publicDir, '/')
                . rtrim($basePath, '/')
                . ($sub !== '' ? '/' . $sub : '');
        }




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
                'newDomainBasicAuth' => $newDomainAuth,
                'oldDomain' => $oldDomain,
                'oldDomainBasicAuth' => $oldDomainAuth,
                'instance' => "https://migration-tests.ddev.site" ,

                'ignore' => [
                    'selectors' => $ignoreSelectors
                ],

                'viewports' => [
                    ['width' => 400, 'height' => 2000],
                    ['width' => 1900, 'height' => 2000],
                ],

                'output' => $output,

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

        // --- DDEV docker-compose.communicate.yaml prüfen/erstellen/ergänzen ---
        $dockerComposePath = $projectRoot . '/.ddev/docker-compose.communicate.yaml';
        $dockerComposeChanged = false;

        if (strpos($newDomain, '.ddev.site') !== false) {
            if (!file_exists($dockerComposePath)) {
                // Datei anlegen
                $content = [
                    'services' => [
                        'web' => [
                            'external_links' => [
                                "ddev-router:" . parse_url($newDomain, PHP_URL_HOST)
                            ]
                        ]
                    ]
                ];
                file_put_contents($dockerComposePath, Yaml::dump($content, 4, 2));
                $dockerComposeChanged = true;
            } else {
                // Datei laden und prüfen
                $yamlContent = Yaml::parseFile($dockerComposePath);
                $host = parse_url($newDomain, PHP_URL_HOST);
                if (!isset($yamlContent['services']['web']['external_links'])) {
                    $yamlContent['services']['web']['external_links'] = [];
                }
                $link = "ddev-router:" . $host;
                if (!in_array($link, $yamlContent['services']['web']['external_links'])) {
                    $yamlContent['services']['web']['external_links'][] = $link;
                    file_put_contents($dockerComposePath, Yaml::dump($yamlContent, 4, 2));
                    $dockerComposeChanged = true;
                }
            }
        }

        if ($dockerComposeChanged) {
            $io->warning('.ddev/docker-compose.communicate.yaml wurde erstellt oder geändert. Bitte führe `ddev restart` aus!');
        }

        $io->success("project.yaml created at: $file");

        return Command::SUCCESS;
    }
}