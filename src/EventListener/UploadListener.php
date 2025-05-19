<?php

namespace Bluebranch\ImageAltAi\EventListener;

use Bluebranch\ImageAltAi\classes\ImageAltAi;
use Contao\Config;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\HttpClient\HttpClientInterface;

include __DIR__ . '/../config/constants.php';

class UploadListener
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function __invoke(array $files)
    {
        if (!Config::get('imageAltAiAutoGenerate')) {
            return;
        }

        $apiKey = Config::get('imageAltAiApiKey');
        if (empty($apiKey)) {
            echo '<p class="tl_error">[Image Alt AI] Fehlender API Key</p>';
        }

        $imageAltAi = new ImageAltAi($this->httpClient);
        $languages = $imageAltAi->getAvailableLanguages();

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
            $supportedExtensions = ALLOWED_EXTENSIONS;
            if (!in_array($extension, $supportedExtensions, true)) {
                continue;
            }

            $absolutePath = $imageAltAi->getAbsolutePathFromRelative($filePath);
            if (!$absolutePath || !file_exists($absolutePath)) {
                continue;
            }

            $errorResponses = [];

            foreach ($languages as $language) {
                $imageContent = fopen($absolutePath, 'r');

                if ($imageContent === false) {
                    continue;
                }

                $response = $imageAltAi->sendToExternalApi($imageContent, $filePath, $apiKey, $language, '', $contextUrl);

                if (isset($response['success']) && $response['success'] === false) {
                    $errorResponses[] = $response;
                }

                fclose($imageContent);
            }

            if (count($errorResponses) > 0) {
                echo '<p class="tl_error">[Image Alt AI] Fehler bei der Verarbeitung: ' . ($errorResponses[0]['message'] ?? 'Unbekannter Fehler') . '</p>';
            }
        }
    }
}
