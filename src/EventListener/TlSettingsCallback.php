<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\classes\BilderAlt;
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
        $apiKey = Config::get('bilderAltApiKey');

        if (empty($apiKey)) {
            return 'Bitte API-Key eintragen';
        }

        try {
            $bilderAlt = new BilderAlt($this->httpClient);
            $response = $bilderAlt->getCreditsBalance($apiKey);

            if ($response['success'] && isset($response['credits'])) {
                return $response['credits'];
            }

            return 'Fehler: ' . ($response['message'] ?? 'Unbekannter Fehler');
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }
}
