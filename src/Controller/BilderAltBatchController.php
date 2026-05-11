<?php

namespace Bluebranch\BilderAlt\Controller;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Bluebranch\BilderAlt\config\Constants;
use Contao\Config;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("%contao.backend.route_prefix%/bilder-alt/batch", name=self::class, defaults={"_scope" = "backend", "_token_check" = true})
 */
class BilderAltBatchController extends AbstractBackendController
{
    private BilderAlt $bilderAlt;
    private ContaoFramework $framework;

    public function __construct(BilderAlt $bilderAlt, ContaoFramework $framework)
    {
        $this->bilderAlt = $bilderAlt;
        $this->framework = $framework;
    }

    private function findImagesRecursively(string $folderUuid, array &$imageFiles, array $imageExtensions): void
    {
        $files = FilesModel::findByPid($folderUuid, ['order' => 'name']);

        if (!$files) {
            return;
        }

        while ($files->next()) {
            if ($files->type === 'folder') {
                $this->findImagesRecursively($files->uuid, $imageFiles, $imageExtensions);
            } elseif (in_array(strtolower(pathinfo($files->path, PATHINFO_EXTENSION)), $imageExtensions, true)) {
                $imageFiles[] = $files->current();
            }
        }
    }

    protected function getImagesFromFiles(array $files, array $imageExtensions): array
    {
        $imageModels = [];

        foreach ($files as $path) {
            if (!$path) {
                continue;
            }

            $model = FilesModel::findByPath(urldecode($path));

            if (!$model) {
                continue;
            }

            if ($model->type === 'folder') {
                $this->findImagesRecursively($model->uuid, $imageModels, $imageExtensions);
            } else {
                $ext = strtolower(pathinfo($model->path, PATHINFO_EXTENSION));
                if (in_array($ext, $imageExtensions, true)) {
                    $imageModels[] = $model->current();
                }
            }
        }

        return $imageModels;
    }

    private static function getNotAvailableTexts(): array
    {
        return [
            'de' => 'nicht vorhanden', 'de-DE' => 'nicht vorhanden', 'de-AT' => 'nicht vorhanden',
            'de-CH' => 'nicht vorhanden', 'de_CH' => 'nicht vorhanden',
            'en' => 'not available', 'en-US' => 'not available', 'en-GB' => 'not available',
            'fr' => 'non disponible', 'fr-BE' => 'non disponible', 'fr-CH' => 'non disponible',
            'fr-CA' => 'non disponible',
            'es' => 'no disponible',
            'it' => 'non disponibile',
            'nl' => 'niet beschikbaar', 'nl-BE' => 'niet beschikbaar',
            'pl' => 'niedostępny',
            'pt' => 'não disponível', 'pt-BR' => 'não disponível',
            'ru' => 'недоступно',
            'zh' => '不可用', 'zh-CN' => '不可用', 'zh-TW' => '不可用',
            'ja' => '利用不可',
            'ko' => '사용 불가',
            'ar' => 'غير متاح',
            'hi' => 'उपलब्ध नहीं',
            'tr' => 'mevcut değil',
            'sv' => 'inte tillgänglig',
            'da' => 'ikke tilgængelig',
            'no' => 'ikke tilgjengelig', 'nb' => 'ikke tilgjengelig', 'nn' => 'ikkje tilgjengeleg',
            'fi' => 'ei saatavilla',
            'cs' => 'není k dispozici',
            'hu' => 'nem elérhető',
            'el' => 'μη διαθέσιμο',
            'bg' => 'не е налично',
            'ro' => 'indisponibil',
            'uk' => 'недоступно',
            'th' => 'ไม่มี',
            'vi' => 'không có sẵn',
            'id' => 'tidak tersedia',
        ];
    }

    private static function resolveNotAvailableText(array $map, string $isoCode): string
    {
        if (isset($map[$isoCode])) {
            return $map[$isoCode];
        }
        $base = explode('-', str_replace('_', '-', $isoCode))[0];
        return $map[$base] ?? 'nicht vorhanden';
    }

    public function __invoke(Request $request): Response
    {
        $this->framework->initialize();

        $selectedFiles = $request->getSession()->get('CURRENT')['IDS'] ?? [];
        $imageModels = $this->getImagesFromFiles($selectedFiles, Constants::ALLOWED_EXTENSIONS);
        $languages = $this->bilderAlt->getAvailableLanguages();
        $notAvailable = self::getNotAvailableTexts();
        $excludedLanguages = Config::get('bilderAltExcludeLanguages')
            ? StringUtil::deserialize(Config::get('bilderAltExcludeLanguages'), true)
            : [];

        $images = array_map(function ($model) use ($languages, $notAvailable, $excludedLanguages) {
            $meta = StringUtil::deserialize($model->meta, true);
            if (!is_array($meta)) {
                $meta = [];
            }

            $langStatus = [];
            $missingLangs = [];

            foreach ($languages as $isoCode => $apiName) {
                $alt = $meta[$isoCode]['alt'] ?? '';
                $langStatus[$isoCode] = [
                    'code' => $isoCode,
                    'alt' => $alt,
                    'notAvailableText' => self::resolveNotAvailableText($notAvailable, $isoCode),
                ];
                if (empty($alt)) {
                    $missingLangs[] = $isoCode;
                }
            }

            foreach ($meta as $isoCode => $entry) {
                if (!isset($langStatus[$isoCode]) && !empty($entry['alt']) && !in_array($isoCode, $excludedLanguages)) {
                    $langStatus[$isoCode] = [
                        'code' => $isoCode,
                        'alt' => $entry['alt'],
                        'notAvailableText' => self::resolveNotAvailableText($notAvailable, $isoCode),
                    ];
                }
            }

            return [
                'model' => $model,
                'meta' => $meta,
                'langStatus' => array_values($langStatus),
                'hasAlt' => empty($missingLangs) && !empty($langStatus),
                'missingLangs' => $missingLangs,
            ];
        }, $imageModels);

        $apiKey = Config::get('bilderAltApiKey');
        $credits = !empty($apiKey) ? $this->bilderAlt->getCreditsBalanceSafe($apiKey) : 0;

        $GLOBALS['TL_CSS'][] = '/bundles/bilderalt/css/batch.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/bilderalt/js/notifications.js';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/bilderalt/js/batch.js';

        return $this->render('@BilderAlt/Backend/bilder_alt_batch_index.html.twig', [
            'title' => 'SEO Alt Text Generator',
            'headline' => 'SEO Alt Text Generator',
            'imageFiles' => $images,
            'credits' => $credits,
            'back_link' => '/contao/tl_files',
        ]);
    }
}
