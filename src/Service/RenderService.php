<?php

namespace App\Service;

use Symfony\Component\Panther\Client;
use Facebook\WebDriver\WebDriverDimension;

class RenderService
{
    public function render(string $url, int $width, int $height): ?string
    {
        try {
            $client = Client::createChromeClient($this->getDriverPath(), [
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--headless=new',
                '--ignore-certificate-errors',
                '--disable-gpu'
            ]);

            // Seite laden
            $client->request('GET', $url);

            // WICHTIG: erst NACH request Fenstergröße setzen
            $client->manage()->window()->setSize(
                new WebDriverDimension($width, $height)
            );

            // statt usleep → echte Wartebedingung
            $client->waitFor('body', 5);

            return $client->getPageSource();

        } catch (\Throwable $e) {
            // Debug direkt sichtbar machen
            echo "\nRENDER ERROR: " . $e->getMessage() . "\n";
            return null;
        }
    }



    public function screenshot(string $url, int $width, int $height, string $path, array $ignoreSelectors = [] ,?string $scrollTo = null): bool
    {
        try {

            $client = Client::createChromeClient( $this->getDriverPath(), [
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--headless=new',
                '--ignore-certificate-errors'
            ]);

            $client->request('GET', $url);

            $client->manage()->window()->setSize(
                new \Facebook\WebDriver\WebDriverDimension($width, $height)
            );

            $client->waitFor('body', 5);

            $this->applyIgnoreSelectors($client, $ignoreSelectors);

            $this->scrollToElement($client, $scrollTo);

            // optional: kleine Stabilisierung
            usleep(300000);

            $client->takeScreenshot($path);

            $client->quit();

            return true;

        } catch (\Throwable $e) {
            echo "\nSCREENSHOT ERROR: " . $e->getMessage() . "\n";
            return false;
        }
    }





    private function applyIgnoreSelectors($client, array $selectors): void
    {
        if (empty($selectors)) {
            return;
        }

        $js = '';

        foreach ($selectors as $selector) {
            $selectorEscaped = addslashes($selector);

            $js .= "
            document.querySelectorAll('{$selectorEscaped}').forEach(el => {
                el.style.visibility = 'hidden';
            });
        ";
        }

        $client->executeScript($js);
    }


    private function scrollToElement($client, ?string $selector): void
    {
        if (!$selector) {
            return;
        }

        $selectorEscaped = addslashes($selector);

        $js = "
        const el = document.querySelector('{$selectorEscaped}');
        if (el) {
            el.scrollIntoView({ behavior: 'instant', block: 'center' });
        }
    ";

        $client->executeScript($js);
    }

    private function getDriverPath(): ?string
    {
        $driverPath = __DIR__ . '/../../drivers/chromedriver';
        if (file_exists($driverPath )) {
            return $driverPath;
        }
        $driverPath = __DIR__ . '/../drivers/chromedriver';
        if (file_exists($driverPath )) {
            return $driverPath;
        }
        return __DIR__ . '/drivers/chromedriver';
    }


}
