<?php

namespace Bluebranch\BilderAlt\classes;

use Contao\System;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BilderAlt
{
    const IMAGE_ALT_AI_API_ENDPOINT = 'https://app.bilder-alt.de';
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getCreditsBalance(string $apiKey): array
    {
        try {
            $url = self::IMAGE_ALT_AI_API_ENDPOINT . '/api/v1/credits/balance';

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'x-api-key' => $apiKey,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $jsonResponse = json_decode($content, true);

            if ($statusCode >= 200 && $statusCode < 300 && $jsonResponse && isset($jsonResponse['credits'])) {
                return [
                    'success' => true,
                    'credits' => $jsonResponse['credits'],
                    'statusCode' => $statusCode
                ];
            }

            return [
                'success' => false,
                'message' => $jsonResponse['message'] ?? 'Unbekannter Fehler bei der Abfrage der Credits',
                'statusCode' => $statusCode,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    public function getAvailableLanguages(): array
    {
        $availableLanguages = [];

        if (class_exists('\\Contao\\PageModel')) {
            $rootPages = \Contao\PageModel::findByType('root');

            if ($rootPages !== null) {
                while ($rootPages->next()) {
                    // Wenn die Sprache definiert ist
                    if ($rootPages->language) {
                        $availableLanguages[$rootPages->language] = $this->getLanguage($rootPages->language);
                    }
                }
            }
        }

        if (empty($availableLanguages)) {
            $user = \Contao\BackendUser::getInstance();
            $availableLanguages[$user->language] = $this->getLanguage($user->language);
        }

        return $availableLanguages;
    }

    public function getAbsolutePathFromRelative(string $path): ?string
    {
        $path = ltrim($path, '/');
        $container = System::getContainer();
        $rootDir = $container->getParameter('kernel.project_dir');
        return $rootDir . '/' . $path;
    }

    public function sendToExternalApi(
        string $imagePath,
        string $apiKey,
        string $language,
        string $keywords,
        string $contextUrl
    ): array
    {
        try {
            $formFields = [
                'language' => $language,
                'image' => DataPart::fromPath($imagePath),
            ];

            if (!empty($keywords)) {
                $formFields['keywords'] = $keywords;
            }

            if (!empty($contextUrl)) {
                $formFields['contextUrl'] = $contextUrl;
            }

            $formData = new FormDataPart($formFields);

            $url = self::IMAGE_ALT_AI_API_ENDPOINT . '/api/v1/openai/upload-image';

            $headers = $formData->getPreparedHeaders()->toArray();
            $headers['x-api-key'] = $apiKey ?? '';

            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'body' => $formData->bodyToIterable(),
            ]);

            $statusCode = $response->getStatusCode();

            $content = $response->getContent(false);

            $jsonResponse = json_decode($content, true);

            if ($statusCode >= 200 && $statusCode < 300 && $jsonResponse && !empty($jsonResponse['altTag'])) {
                $languageIsoCode = $this->getIsoCodeFromLanguage($language);
                $this->updateImageAltText($imagePath, $jsonResponse['altTag'], $languageIsoCode);
                return array_merge(['success' => true, 'statusCode' => $statusCode], $jsonResponse);
            }

            if ($jsonResponse) {
                return array_merge(['success' => false, 'statusCode' => $statusCode], $jsonResponse);
            }

            return [
                'success' => false,
                'message' => 'Error: ' . ($jsonResponse['message'] ?? 'Unbekannter Fehler'),
                'statusCode' => $statusCode,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    public function updateImageAltText(string $path, string $altText, ?string $language = null): void
    {
        if (empty($path)) {
            return;
        }

        // UUID prüfen
        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $path)) {
            $fileModel = \Contao\FilesModel::findByUuid($path);
        } else {
            // Für reguläre Pfade
            $fileModel = \Contao\FilesModel::findByPath($path);
        }

        if ($fileModel === null) {
            return;
        }

        $fileModel->meta = $this->updateMetaInformation($fileModel->meta, $altText, $language);
        $fileModel->save();
    }

    public function updateMetaInformation($metaData, string $altText, ?string $language = 'de'): string
    {
        $meta = \Contao\StringUtil::deserialize($metaData, true);

        if (empty($meta)) {
            $meta = [$language => ['title' => '', 'alt' => $altText]];
        } else {
            if (!isset($meta[$language])) {
                $meta[$language] = ['title' => '', 'alt' => $altText];
            } else {
                $meta[$language]['alt'] = $altText;
            }
        }

        return serialize($meta);
    }

    protected function getIsoCodeFromLanguage(string $language): string
    {
        $languageToIsoMapping = array_flip([
            'en' => 'english',
            'de' => 'german',
            'fr' => 'french',
            'es' => 'spanish',
            'it' => 'italian',
            'nl' => 'nederlands',
            'pt' => 'portuguese',
            'ru' => 'russian',
            'zh' => 'chinese',
            'ja' => 'japanese',
            'ko' => 'korean',
            'ar' => 'arabic',
            'hi' => 'hindi',
            'pl' => 'polish',
            'tr' => 'turkish',
            'sv' => 'swedish',
            'da' => 'danish',
            'no' => 'norwegian',
            'fi' => 'finnish',
            'cs' => 'czech',
            'hu' => 'hungarian',
            'el' => 'greek',
            'bg' => 'bulgarian',
            'ro' => 'romanian',
            'uk' => 'ukrainian',
            'th' => 'thai',
            'vi' => 'vietnamese',
            'id' => 'indonesian',
        ]);

        return $languageToIsoMapping[$language] ?? 'de';
    }

    protected function getLanguage(string $language): string
    {
        $languageMapping = [
            'en' => 'english',
            'en-US' => 'english',
            'en-GB' => 'english',
            'de' => 'german',
            'de-DE' => 'german',
            'fr' => 'french',
            'es' => 'spanish',
            'it' => 'italian',
            'nl' => 'nederlands',
            'pt' => 'portuguese',
            'pt-BR' => 'portuguese',
            'ru' => 'russian',
            'zh' => 'chinese',
            'zh-CN' => 'chinese',
            'zh-TW' => 'chinese',
            'ja' => 'japanese',
            'ko' => 'korean',
            'ar' => 'arabic',
            'hi' => 'hindi',
            'pl' => 'polish',
            'tr' => 'turkish',
            'sv' => 'swedish',
            'da' => 'danish',
            'no' => 'norwegian',
            'nb' => 'norwegian',
            'nn' => 'norwegian',
            'fi' => 'finnish',
            'cs' => 'czech',
            'hu' => 'hungarian',
            'el' => 'greek',
            'bg' => 'bulgarian',
            'ro' => 'romanian',
            'uk' => 'ukrainian',
            'th' => 'thai',
            'vi' => 'vietnamese',
            'id' => 'indonesian',
            'in' => 'bahasa indonesia',
            'ms' => 'malay',
            'ms-BN' => 'bahasa melayu',
            'ms-MY' => 'bahasa melayu',
        ];
        return $languageMapping[$language] ?? 'english';
    }
}