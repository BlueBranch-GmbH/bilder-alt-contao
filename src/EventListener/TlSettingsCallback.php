<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Contao\Config;

class TlSettingsCallback
{
    private BilderAlt $bilderAlt;

    public function __construct(BilderAlt $bilderAlt)
    {
        $this->bilderAlt = $bilderAlt;
    }

    public function loadCredits($value)
    {
        $apiKey = Config::get('bilderAltApiKey');

        if (empty($apiKey)) {
            return 'Bitte API-Key eintragen';
        }

        try {
            $response = $this->bilderAlt->getCreditsBalance($apiKey);

            if ($response['success'] && isset($response['credits'])) {
                return $response['credits'];
            }

            return 'Fehler: ' . ($response['message'] ?? 'Unbekannter Fehler');
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }
}
