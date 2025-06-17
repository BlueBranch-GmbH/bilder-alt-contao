<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Bluebranch\BilderAlt\config\Constants;
use Contao\Config;
use Contao\System;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UploadListener
{
    /** @var HttpClientInterface */
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        System::loadLanguageFile('tl_settings');
        $this->httpClient = $httpClient;
    }

    public function __invoke(array $files): void
    {
        if (!Config::get('bilderAltAutoGenerate')) {
            return;
        }

        $apiKey = Config::get('bilderAltApiKey');
        if (empty($apiKey)) {
            $this->printError('[Bilder Alt] Fehlender API Key');
            return;
        }

        $contextUrl = $_SERVER['HTTP_HOST'] ?? '';
        $bilderAlt = new BilderAlt($this->httpClient);
        $languages = $bilderAlt->getAvailableLanguages();

        foreach ($files as $rawPath) {
            $filePath = urldecode($rawPath);

            if (!$this->isValidImage($filePath)) {
                continue;
            }

            $absolutePath = $bilderAlt->getAbsolutePathFromRelative($filePath);
            if (!$absolutePath || !file_exists($absolutePath)) {
                continue;
            }

            $keywords = $bilderAlt->getKeywordsFromFile($filePath) ?? [];
            $errorResponses = [];

            foreach ($languages as $language) {
                $response = $bilderAlt->sendToExternalApi(
                    $filePath,
                    $apiKey,
                    $language,
                    implode(',', $keywords),
                    $contextUrl
                );

                if ($this->isFailedResponse($response)) {
                    $errorResponses[] = $response;
                }

                // Break early on 402 – keine weiteren Versuche
                if (!empty($response['statusCode']) && (int)$response['statusCode'] === 402) {
                    break;
                }
            }

            if (!empty($errorResponses)) {
                $msg = $this->getErrorMessage($errorResponses[0]);
                $this->printError('[Bilder Alt] Fehler bei der Verarbeitung: ' . $msg);
            }
        }
    }

    private function isValidImage(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, Constants::ALLOWED_EXTENSIONS, true)) {
            return false;
        }

        try {
            $file = new File($path);
            return strpos($file->getMimeType(), 'image/') === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function printError(string $message): void
    {
        echo '<p class="tl_error">' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    }

    private function isFailedResponse(array $response): bool
    {
        return !isset($response['success']) || $response['success'] === false;
    }

    private function getErrorMessage(array $response): string
    {
        return $response['statusCode'] === 402
            ? $GLOBALS['TL_LANG']['tl_settings']['bilderAltNoCredits']
            : $response['message']
            ?? 'Unbekannter Fehler';
    }
}
