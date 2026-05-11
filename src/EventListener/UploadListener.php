<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Bluebranch\BilderAlt\config\Constants;
use Bluebranch\BilderAlt\Security\BilderAltPermissions;
use Contao\Config;
use Contao\System;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\RequestStack;

class UploadListener
{
    private BilderAlt $bilderAlt;
    private RequestStack $requestStack;

    public function __construct(BilderAlt $bilderAlt, RequestStack $requestStack)
    {
        System::loadLanguageFile('tl_settings');
        $this->bilderAlt = $bilderAlt;
        $this->requestStack = $requestStack;
    }

    public function __invoke(array $files): void
    {
        if (!Config::get('bilderAltAutoGenerate') || !BilderAltPermissions::canCreateAutoUpload()) {
            return;
        }

        $apiKey = Config::get('bilderAltApiKey');
        if (empty($apiKey)) {
            $this->printError('[Bilder Alt] Fehlender API Key');
            return;
        }

        $contextUrl = $this->requestStack->getCurrentRequest()?->getHost() ?? '';
        $languages = $this->bilderAlt->getAvailableLanguages();

        foreach ($files as $rawPath) {
            $filePath = urldecode($rawPath);

            if (!$this->isValidImage($filePath)) {
                continue;
            }

            $absolutePath = $this->bilderAlt->getAbsolutePathFromRelative($filePath);
            if (!$absolutePath || !file_exists($absolutePath)) {
                continue;
            }

            $keywords = $this->bilderAlt->getKeywords($filePath);
            $errorResponses = [];

            foreach ($languages as $isoCode => $language) {
                $response = $this->bilderAlt->sendToExternalApi(
                    $filePath,
                    $apiKey,
                    $language,
                    implode(',', $keywords),
                    $contextUrl,
                    $isoCode
                );

                if ($this->isFailedResponse($response)) {
                    $errorResponses[] = $response;
                }

                // Break early on 402 – no further attempts
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
            return str_starts_with($file->getMimeType(), 'image/');
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
