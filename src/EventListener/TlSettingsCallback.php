<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Contao\Config;

class TlSettingsCallback
{
    private const MASK = '••••••••';
    private const MASK_ENV = '•••••••• (aus Umgebungsvariable BILDER_ALT_API_KEY)';

    private BilderAlt $bilderAlt;

    public function __construct(BilderAlt $bilderAlt)
    {
        $this->bilderAlt = $bilderAlt;
    }

    /**
     * Zeigt den gespeicherten Key nie im Klartext an.
     */
    public function maskApiKey($value)
    {
        if ($this->bilderAlt->isApiKeyFromEnvironment()) {
            return self::MASK_ENV;
        }

        $value = (string) $value;

        return '' === $value ? '' : self::MASK . substr($value, -4);
    }

    /**
     * Ein unverändert abgeschickter (maskierter) Wert behält den bisherigen Key.
     */
    public function saveApiKey($value)
    {
        $value = trim((string) $value);

        if ($this->bilderAlt->isApiKeyFromEnvironment() || str_starts_with($value, self::MASK)) {
            return Config::get('bilderAltApiKey');
        }

        return $value;
    }

    public function loadCredits($value)
    {
        $apiKey = $this->bilderAlt->getApiKey();

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
