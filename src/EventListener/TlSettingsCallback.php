<?php

namespace Bluebranch\ImageAltAi\EventListener;

use Bluebranch\ImageAltAi\classes\ImageAltAi;
use Contao\Config;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TlSettingsCallback
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function loadCredits($value)
    {
        $apiKey = Config::get('imageAltAiApiKey');

        if (empty($apiKey)) {
            return 'Bitte API-Key eintragen';
        }

        try {
            $imageAltAi = new ImageAltAi($this->httpClient);
            $response = $imageAltAi->getCreditsBalance($apiKey);

            if ($response['success'] && isset($response['credits'])) {
                return $response['credits'];
            }

            return 'Fehler: ' . ($response['message'] ?? 'Unbekannter Fehler');
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }
}
