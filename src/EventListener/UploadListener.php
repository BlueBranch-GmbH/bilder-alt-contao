<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Bluebranch\BilderAlt\config\Constants;
use Contao\Config;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UploadListener
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function __invoke(array $files)
    {
        if (!Config::get('bilderAltAutoGenerate')) {
            return;
        }

        $apiKey = Config::get('bilderAltApiKey');
        if (empty($apiKey)) {
            echo '<p class="tl_error">[Bilder Alt] Fehlender API Key</p>';
        }

        $keywords = Config::get('bilderAltKeywords');
        if (empty($keywords)) {
            $keywords = '';
        }

        $bilderAlt = new BilderAlt($this->httpClient);
        $languages = $bilderAlt->getAvailableLanguages();

        $contextUrl = '';
        if (isset($_SERVER['HTTP_HOST'])) {
            $contextUrl = $_SERVER['HTTP_HOST'];
        }

        foreach ($files as $filePath) {
            $filePath = urldecode($filePath);

            $file = new File($filePath);
            $mimeType = $file->getMimeType();

            if (!str_starts_with($mimeType, 'image/')) {
                continue;
            }

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $supportedExtensions = Constants::ALLOWED_EXTENSIONS;
            if (!in_array($extension, $supportedExtensions, true)) {
                continue;
            }

            $absolutePath = $bilderAlt->getAbsolutePathFromRelative($filePath);
            if (!$absolutePath || !file_exists($absolutePath)) {
                continue;
            }

            $errorResponses = [];

            foreach ($languages as $language) {
                $response = $bilderAlt->sendToExternalApi($filePath, $apiKey, $language, $keywords, $contextUrl);

                if (isset($response['success']) && $response['success'] === false) {
                    $errorResponses[] = $response;
                }
            }

            if (count($errorResponses) > 0) {
                echo '<p class="tl_error">[Bilder Alt] Fehler bei der Verarbeitung: ' . ($errorResponses[0]['message'] ?? 'Unbekannter Fehler') . '</p>';
            }
        }
    }
}
