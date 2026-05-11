<?php

namespace App\Service;

use Symfony\Component\Panther\Client;
use Facebook\WebDriver\WebDriverDimension;

class RenderService
{
    public function render(string $url, int $width, int $height, ?string $basicAuth = null): ?string
    {
        try {
            $client = Client::createChromeClient($this->getDriverPath(), [
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--headless=new',
                '--ignore-certificate-errors',
                '--disable-gpu'
            ]);
            if ($basicAuth) {
                $url = $this->addBasicAuthToUrl($url, $basicAuth);
            }

            $client->request('GET', $url );

            $client->manage()->window()->setSize(
                new WebDriverDimension($width, $height)
            );

            $client->waitFor('body', 5);

            return $client->getPageSource();

        } catch (\Throwable $e) {
            echo "\nRENDER ERROR: " . $e->getMessage() . "\n";
            return null;
        }
    }



    public function screenshot(string $url, int $width, int $height, string $path, array $ignoreSelectors = [] ,?string $scrollTo = null , ?string $basicAuth = null): bool
    {
        try {

            $client = Client::createChromeClient( $this->getDriverPath(), [
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--headless=new',
                '--ignore-certificate-errors'
            ]);

            if ($basicAuth) {
                $url = $this->addBasicAuthToUrl($url, $basicAuth);
            }

            $client->request('GET', $url);

            // Entferne Usercentrics-Skripte aus dem DOM
            $client->executeScript("
    var uc = document.getElementById('usercentrics-cmp');
    if (uc) { uc.remove();  }

    var ucBlock = document.querySelector('script[src*=\"uc-block.bundle.js\"]');
    if (ucBlock) { ucBlock.remove()}

    var tagManagerScripts = document.querySelectorAll('script[type=\"text/plain\"][data-usercentrics]');
    tagManagerScripts.forEach(function(el) { el.remove(); });
            ");


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

    public function addBasicAuthToUrl(string $url, ?string $basicAuth): string
    {
        if (!$basicAuth) {
            return $url;
        }
        $parts = parse_url($url);
        if (!isset($parts['host'])) {
            return $url;
        }
        [$user, $pass] = explode(':', $basicAuth, 2) + [null, null];
        $auth = $user . ($pass !== null ? ':' . $pass : '');
        $parts['user'] = $user;
        $parts['pass'] = $pass;
        // Neu zusammensetzen
        return (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
            . $auth . '@'
            . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . (isset($parts['path']) ? $parts['path'] : '')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
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
