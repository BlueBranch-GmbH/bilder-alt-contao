<?php

namespace Bluebranch\BilderAlt\classes;

use Contao\Config;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\Image\DeferredImageInterface;
use Contao\Image\DeferredResizerInterface;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BilderAlt
{
    private const API_BASE_URL = 'https://app.bilder-alt.de';
    private const TIMEOUT_CREDITS = 10;
    private const TIMEOUT_GENERATE = 60;

    // Longest edge of the image sent to the API; larger images are scaled down first
    private const MAX_IMAGE_EDGE = 1024;
    private const RESIZABLE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private HttpClientInterface $httpClient;
    private ?array $availableLanguagesCache = null;
    private array $altAffixCache = [];
    private array $uploadImageCache = [];

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
                'timeout' => self::TIMEOUT_CREDITS,
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

    public function getCreditsBalanceSafe(string $apiKey): int
    {
        try {
            $response = $this->getCreditsBalance($apiKey);
            return (int) ($response['credits'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Die Umgebungsvariable BILDER_ALT_API_KEY hat Vorrang vor der Einstellung im Backend.
     */
    public function getApiKey(): ?string
    {
        $key = $_SERVER['BILDER_ALT_API_KEY'] ?? $_ENV['BILDER_ALT_API_KEY'] ?? getenv('BILDER_ALT_API_KEY');

        if (!\is_string($key) || '' === trim($key)) {
            $key = Config::get('bilderAltApiKey');
        }

        return \is_string($key) && '' !== trim($key) ? trim($key) : null;
    }

    public function isApiKeyFromEnvironment(): bool
    {
        $key = $_SERVER['BILDER_ALT_API_KEY'] ?? $_ENV['BILDER_ALT_API_KEY'] ?? getenv('BILDER_ALT_API_KEY');

        return \is_string($key) && '' !== trim($key);
    }

    public function getAvailableLanguages(): array
    {
        if ($this->availableLanguagesCache !== null) {
            return $this->availableLanguagesCache;
        }

        $languages = [];
        $excludedLanguages = Config::get('bilderAltExcludeLanguages')
            ? StringUtil::deserialize(Config::get('bilderAltExcludeLanguages'), true)
            : [];

        if (class_exists(PageModel::class)) {
            $t = PageModel::getTable();
            $roots = PageModel::findBy(
                ["$t.type=?", "$t.published=?"],
                ['root', '1'],
                ['order' => "$t.sorting ASC"]
            );
            if ($roots !== null) {
                while ($roots->next()) {
                    if (!empty($roots->language) && !in_array($roots->language, $excludedLanguages)) {
                        $languages[$roots->language] = $this->getLanguage($roots->language);
                    }
                }
            }
        }

        $this->availableLanguagesCache = $languages;
        return $languages;
    }

    public function getAbsolutePathFromRelative(string $path): ?string
    {
        $path = ltrim($path, '/');
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');
        return $rootDir . '/' . $path;
    }

    public function sendToExternalApi(string $imagePath, string $apiKey, string $language, string $keywords, string $contextUrl, ?string $storageKey = null): array
    {
        try {
            $fields = [
                'language' => $language,
                'image' => DataPart::fromPath($this->getUploadImagePath($imagePath)),
            ];

            if (!empty($keywords)) {
                $fields['keywords'] = $keywords;
            }

            if (!empty($contextUrl)) {
                $fields['contextUrl'] = $contextUrl;
            }

            $isoCode = $storageKey ?? $this->getIsoCodeFromLanguage($language);

            // Reserve room for prefix/suffix so the final alt text stays within the limit
            $maxLength = (int) (Config::get('bilderAltMaxLength') ?: 125);
            $fields['maxLength'] = (string) max(1, $maxLength - $this->getAltAffixLength($isoCode));

            $form = new FormDataPart($fields);
            $url = self::API_BASE_URL . '/api/v1/openai/upload-image';

            $headers = $form->getPreparedHeaders()->toArray();
            $headers['x-api-key'] = $apiKey;

            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'body' => $form->bodyToIterable(),
                'timeout' => self::TIMEOUT_GENERATE,
                'max_duration' => self::TIMEOUT_GENERATE * 2,
            ]);

            $statusCode = $response->getStatusCode();
            $json = json_decode($response->getContent(false), true);

            if ($statusCode >= 200 && $statusCode < 300 && !empty($json['altTag'])) {
                $rawAltTag = $json['altTag'];
                $altTag = $this->applyAltAffixes($rawAltTag, $isoCode);
                $this->updateImageAltText($imagePath, $altTag, $isoCode, $rawAltTag);
                return array_merge(['success' => true, 'statusCode' => $statusCode], $json, ['altTag' => $altTag]);
            }

            return array_merge(['success' => false, 'statusCode' => $statusCode], $json ?: [
                'message' => 'Unbekannter Fehler',
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    /**
     * Returns a downscaled copy (longest edge MAX_IMAGE_EDGE) of large raster images so the
     * upload stays small. Contao's image factory caches the result in assets/images, so
     * repeated calls (one per language) do not resize again. Falls back to the original
     * file whenever resizing is not possible.
     */
    private function getUploadImagePath(string $imagePath): string
    {
        if (isset($this->uploadImageCache[$imagePath])) {
            return $this->uploadImageCache[$imagePath];
        }

        $resized = $imagePath;

        try {
            $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $size = \in_array($extension, self::RESIZABLE_EXTENSIONS, true) ? @getimagesize($imagePath) : false;

            if ($size && max($size[0], $size[1]) > self::MAX_IMAGE_EDGE) {
                $container = System::getContainer();
                $image = $container->get('contao.image.factory')->create(
                    $imagePath,
                    [self::MAX_IMAGE_EDGE, self::MAX_IMAGE_EDGE, 'proportional']
                );

                // Contao 5 may only have announced the resized file (deferred resizing)
                if ($image instanceof DeferredImageInterface) {
                    $resizer = $container->get('contao.image.resizer');
                    if ($resizer instanceof DeferredResizerInterface) {
                        $image = $resizer->resizeDeferredImage($image) ?? $image;
                    }
                }

                $path = $image->getPath();
                if (is_file($path) && filesize($path) > 0 && filesize($path) < filesize($imagePath)) {
                    $resized = $path;
                }
            }
        } catch (\Throwable $e) {
            $resized = $imagePath;
        }

        return $this->uploadImageCache[$imagePath] = $resized;
    }

    private function applyAltAffixes(string $altTag, string $isoCode): string
    {
        ['prefix' => $prefix, 'suffix' => $suffix] = $this->getAltAffixesForLanguage($isoCode);

        if ($prefix !== '') {
            $prefix .= ' ';
        }

        if ($suffix !== '') {
            $suffix = ' ' . $suffix;
        }

        return $prefix . $altTag . $suffix;
    }

    /**
     * Number of characters applyAltAffixes() adds, including the separating spaces.
     */
    private function getAltAffixLength(string $isoCode): int
    {
        return mb_strlen($this->applyAltAffixes('', $isoCode));
    }

    /**
     * Resolves the prefix/suffix for a given root-page language code, falling
     * back to the global bilderAltAltPrefix/bilderAltAltSuffix settings when
     * the root page has no override (or none can be found for the language).
     */
    private function getAltAffixesForLanguage(string $isoCode): array
    {
        if (isset($this->altAffixCache[$isoCode])) {
            return $this->altAffixCache[$isoCode];
        }

        $prefix = (string) (Config::get('bilderAltAltPrefix') ?? '');
        $suffix = (string) (Config::get('bilderAltAltSuffix') ?? '');

        if (class_exists(PageModel::class)) {
            $t = PageModel::getTable();
            $root = PageModel::findOneBy(["$t.type=?", "$t.language=?"], ['root', $isoCode]);

            if ($root !== null) {
                $rootPrefix = trim((string) $root->bilderAltAltPrefix);
                $rootSuffix = trim((string) $root->bilderAltAltSuffix);

                if ($rootPrefix !== '') {
                    $prefix = $rootPrefix;
                }

                if ($rootSuffix !== '') {
                    $suffix = $rootSuffix;
                }
            }
        }

        return $this->altAffixCache[$isoCode] = ['prefix' => $prefix, 'suffix' => $suffix];
    }

    public function updateImageAltText(string $path, string $altText, ?string $language = null, ?string $rawAltText = null): void
    {
        if (empty($path)) {
            return;
        }

        $fileModel = $this->getFileModelFromPathOrUuid($path);
        if ($fileModel === null) {
            return;
        }

        $fileModel->meta = $this->updateMetaInformation($fileModel->meta, $altText, $language ?? 'de', $rawAltText);
        $fileModel->save();
    }

    private function getFileModelFromPathOrUuid(string $path): ?FilesModel
    {
        if (preg_match('/^[a-f0-9\-]{36}$/', $path)) {
            return FilesModel::findByUuid($path);
        }

        return FilesModel::findByPath($path);
    }

    public function updateMetaInformation($metaData, string $altText, ?string $language = 'de', ?string $rawAltText = null): string
    {
        $meta = StringUtil::deserialize($metaData, true);

        if (!isset($meta[$language])) {
            $meta[$language] = ['title' => '', 'alt' => $altText];
        } else {
            $meta[$language]['alt'] = $altText;
        }

        // Kept separately (without prefix/suffix) so a later regeneration never
        // feeds the affix back to the AI as "existing alt text" context.
        $meta[$language]['bilderAltRawAlt'] = $rawAltText ?? $altText;

        return serialize($meta);
    }

    protected function getIsoCodeFromLanguage(string $language): string
    {
        $mapping = array_flip($this->getBaseLanguageMap());
        return $mapping[$language] ?? $language;
    }

    public function generatePageTitle(string $pageUrl, string $apiKey, string $language, string $keywords = ''): array
    {
        return $this->makePageRequest('title', $pageUrl, $apiKey, $language, $keywords);
    }

    public function generatePageDescription(string $pageUrl, string $apiKey, string $language, string $keywords = ''): array
    {
        return $this->makePageRequest('description', $pageUrl, $apiKey, $language, $keywords);
    }

    private function makePageRequest(string $type, string $pageUrl, string $apiKey, string $language, string $keywords = ''): array
    {
        try {
            $body = ['url' => $pageUrl, 'language' => $language];
            if (!empty($keywords)) {
                $body['keywords'] = $keywords;
            }

            $response = $this->httpClient->request('POST', self::API_BASE_URL . '/api/v1/openai/generate-' . $type, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $apiKey,
                ],
                'json' => $body,
                'timeout' => self::TIMEOUT_GENERATE,
                'max_duration' => self::TIMEOUT_GENERATE * 2,
            ]);

            $statusCode = $response->getStatusCode();
            $json = json_decode($response->getContent(false), true);
            $data = $json['data'] ?? $json;
            $value = $data[$type] ?? null;

            if ($statusCode >= 200 && $statusCode < 300 && $value) {
                return ['success' => true, $type => $value, 'statusCode' => $statusCode];
            }

            return [
                'success' => false,
                'message' => $json['message'] ?? 'Fehler bei der Generierung',
                'statusCode' => $statusCode,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    public function getLanguage(string $language): string
    {
        $map = array_merge($this->getBaseLanguageMap(), [
            'en-US' => 'english', 'en-GB' => 'english',
            'de-DE' => 'german',
            'pt-BR' => 'portuguese',
            'zh-CN' => 'chinese', 'zh-TW' => 'chinese',
            'nb' => 'norwegian', 'nn' => 'norwegian',
            'in' => 'bahasa indonesia',
            'ms' => 'malay', 'ms-BN' => 'bahasa melayu', 'ms-MY' => 'bahasa melayu',
            'fr-BE' => 'french', 'fr-CH' => 'french', 'fr-CA' => 'french',
            'nl-BE' => 'nederlands',
        ]);

        // Contao allows underscores (e.g. de_CH), normalise to hyphens
        $normalized = str_replace('_', '-', $language);

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        $base = explode('-', $normalized)[0];
        return $map[$base] ?? 'english';
    }

    private function getBaseLanguageMap(): array
    {
        return [
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
        ];
    }

    public function getKeywords(string $filePath, string $isoCode): array
    {
        $keywords = explode(',', Config::get('bilderAltKeywords') ?? '');
        $fileModel = FilesModel::findByPath($filePath);

        $sendExistingAltText = Config::get('bilderAltAddExistingAltTag');
        if ($fileModel && $sendExistingAltText === true) {
            if ($alt = $this->getAltTextFromMeta($fileModel->meta, $isoCode)) {
                array_unshift($keywords, $alt);
            }
        }

        return array_filter(array_map('trim', $keywords));
    }

    private function getAltTextFromMeta($meta, string $isoCode): ?string
    {
        $metaArray = StringUtil::deserialize($meta, true);
        if (empty($metaArray)) {
            return null;
        }

        // Prefer the raw (un-affixed) text so a configured prefix/suffix is never
        // sent back to the AI as context, even if it was applied by an older
        // config value. Falls back to 'alt' for entries saved before this field existed.
        return $metaArray[$isoCode]['bilderAltRawAlt'] ?? $metaArray[$isoCode]['alt'] ?? null;
    }
}
