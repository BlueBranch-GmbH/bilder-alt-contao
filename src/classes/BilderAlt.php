<?php

namespace Bluebranch\BilderAlt\classes;

use Contao\BackendUser;
use Contao\Config;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BilderAlt
{
    private const API_BASE_URL = 'https://app.bilder-alt.de';

    /** @var HttpClientInterface */
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getCreditsBalance(string $apiKey): array
    {
        $url = self::API_BASE_URL . '/api/v1/credits/balance';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'x-api-key' => $apiKey,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $json = json_decode($response->getContent(false), true);

            if ($statusCode >= 200 && $statusCode < 300 && isset($json['credits'])) {
                return ['success' => true, 'credits' => $json['credits'], 'statusCode' => $statusCode];
            }

            return [
                'success' => false,
                'message' => $json['message'] ?? 'Unbekannter Fehler bei der Abfrage der Credits',
                'statusCode' => $statusCode,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    public function getAvailableLanguages(): array
    {
        $languages = [];

        if (class_exists(PageModel::class)) {
            $roots = PageModel::findByType('root');
            if ($roots !== null) {
                while ($roots->next()) {
                    if (!empty($roots->language)) {
                        $languages[$roots->language] = $this->getLanguage($roots->language);
                    }
                }
            }
        }

        if (empty($languages)) {
            $user = BackendUser::getInstance();
            $languages[$user->language] = $this->getLanguage($user->language);
        }

        return $languages;
    }

    public function getAbsolutePathFromRelative(string $path): ?string
    {
        $path = ltrim($path, '/');
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');
        return $rootDir . '/' . $path;
    }

    public function sendToExternalApi(string $imagePath, string $apiKey, string $language, string $keywords, string $contextUrl): array
    {
        try {
            $fields = [
                'language' => $language,
                'image' => DataPart::fromPath($imagePath),
            ];

            if (!empty($keywords)) {
                $fields['keywords'] = $keywords;
            }

            if (!empty($contextUrl)) {
                $fields['contextUrl'] = $contextUrl;
            }

            $form = new FormDataPart($fields);
            $url = self::API_BASE_URL . '/api/v1/openai/upload-image';

            $headers = $form->getPreparedHeaders()->toArray();
            $headers['x-api-key'] = $apiKey;

            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'body' => $form->bodyToIterable(),
            ]);

            $statusCode = $response->getStatusCode();
            $json = json_decode($response->getContent(false), true);

            if ($statusCode >= 200 && $statusCode < 300 && !empty($json['altTag'])) {
                $isoCode = $this->getIsoCodeFromLanguage($language);
                $this->updateImageAltText($imagePath, $json['altTag'], $isoCode);
                return array_merge(['success' => true, 'statusCode' => $statusCode], $json);
            }

            return array_merge(['success' => false, 'statusCode' => $statusCode], $json ?: [
                'message' => 'Unbekannter Fehler',
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    public function updateImageAltText(string $path, string $altText, ?string $language = null): void
    {
        if (empty($path)) {
            return;
        }

        $fileModel = $this->getFileModelFromPathOrUuid($path);
        if ($fileModel === null) {
            return;
        }

        $fileModel->meta = $this->updateMetaInformation($fileModel->meta, $altText, $language ?? 'de');
        $fileModel->save();
    }

    private function getFileModelFromPathOrUuid(string $path): ?FilesModel
    {
        if (preg_match('/^[a-f0-9\-]{36}$/', $path)) {
            return FilesModel::findByUuid($path);
        }

        return FilesModel::findByPath($path);
    }

    public function updateMetaInformation($metaData, string $altText, ?string $language = 'de'): string
    {
        $meta = StringUtil::deserialize($metaData, true);

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
        $mapping = array_flip([
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

        return isset($mapping[$language]) ? $mapping[$language] : 'de';
    }

    protected function getLanguage(string $language): string
    {
        $map = [
            'en' => 'english', 'en-US' => 'english', 'en-GB' => 'english',
            'de' => 'german', 'de-DE' => 'german',
            'fr' => 'french', 'es' => 'spanish', 'it' => 'italian',
            'nl' => 'nederlands', 'pt' => 'portuguese', 'pt-BR' => 'portuguese',
            'ru' => 'russian', 'zh' => 'chinese', 'zh-CN' => 'chinese', 'zh-TW' => 'chinese',
            'ja' => 'japanese', 'ko' => 'korean', 'ar' => 'arabic', 'hi' => 'hindi',
            'pl' => 'polish', 'tr' => 'turkish', 'sv' => 'swedish', 'da' => 'danish',
            'no' => 'norwegian', 'nb' => 'norwegian', 'nn' => 'norwegian',
            'fi' => 'finnish', 'cs' => 'czech', 'hu' => 'hungarian', 'el' => 'greek',
            'bg' => 'bulgarian', 'ro' => 'romanian', 'uk' => 'ukrainian',
            'th' => 'thai', 'vi' => 'vietnamese', 'id' => 'indonesian',
            'in' => 'bahasa indonesia', 'ms' => 'malay', 'ms-BN' => 'bahasa melayu', 'ms-MY' => 'bahasa melayu',
        ];

        return isset($map[$language]) ? $map[$language] : 'english';
    }

    public function getKeywords(string $filePath): array
    {
        $keywords = explode(',', Config::get('bilderAltKeywords') ?? '');
        $fileModel = FilesModel::findByPath($filePath);

        if ($fileModel) {
            if ($alt = $this->getAltTextFromMeta($fileModel->meta)) {
                array_unshift($keywords, $alt);
            }
        }

        return array_filter(array_map('trim', $keywords));
    }

    private function getAltTextFromMeta($meta): ?string
    {
        $metaArray = StringUtil::deserialize($meta, true);
        if (empty($metaArray)) {
            return null;
        }

        $firstLang = array_key_first($metaArray);
        return $metaArray[$firstLang]['alt'] ?? null;
    }
}
