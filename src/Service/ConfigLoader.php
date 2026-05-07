<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class ConfigLoader
{
    private array $config;

    public function __construct(string $configPath)
    {
        $this->config = Yaml::parseFile($configPath);
    }

    public function getProject(): array
    {
        return $this->config['project'] ?? [];
    }
}