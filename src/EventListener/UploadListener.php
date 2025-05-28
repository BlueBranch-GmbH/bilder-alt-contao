<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Contao\Config;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Bluebranch\BilderAlt\config\Constants;

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
                $imageContent = fopen($absolutePath, 'r');

                if ($imageContent === false) {
                    continue;
                }

                $response = $bilderAlt->sendToExternalApi($imageContent, $filePath, $apiKey, $language, '', $contextUrl);

                if (isset($response['success']) && $response['success'] === false) {
                    $errorResponses[] = $response;
                }

                fclose($imageContent);
            }

            if (count($errorResponses) > 0) {
                echo '<p class="tl_error">[Bilder Alt] Fehler bei der Verarbeitung: ' . ($errorResponses[0]['message'] ?? 'Unbekannter Fehler') . '</p>';
            }
        }
    }
}
