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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("%contao.backend.route_prefix%/bilder-alt/batch", name=self::class, defaults={"_scope" = "backend", "_token_check" = true})
 */
class BilderAltBatchController extends AbstractBackendController
{
    private BilderAlt $bilderAlt;
    private ContaoFramework $framework;

    public function __construct(HttpClientInterface $httpClient, ContaoFramework $framework)
    {
        $this->bilderAlt = new BilderAlt($httpClient);
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

    public function __invoke(Request $request): Response
    {
        $this->framework->initialize();

        $selectedFiles = $request->getSession()->get('CURRENT')['IDS'] ?? [];

        $imageModels = $this->getImagesFromFiles($selectedFiles, Constants::ALLOWED_EXTENSIONS);

        $images = array_map(function ($model) {
            $meta = [];
            if (!empty($model->meta)) {
                $meta = StringUtil::deserialize($model->meta);
            }

            $hasAlt = count($meta) > 0;
            foreach ($meta as $value) {
                if (empty($value['alt'])) {
                    $hasAlt = false;
                }
            }

            return [
                'model' => $model,
                'meta' => is_array($meta) ? $meta : [],
                'hasAlt' => $hasAlt
            ];
        }, $imageModels);

        $apiKey = Config::get('bilderAltApiKey');
        $creditsInfo = ['credits' => 0];

        if (!empty($apiKey)) {
            try {
                $credits = $this->bilderAlt->getCreditsBalance($apiKey);
                if (!empty($credits['credits'])) {
                    $creditsInfo['credits'] = $credits['credits'];
                }
            } catch (\Throwable $e) {
                $creditsInfo['credits'] = 0;
            }
        }

        $GLOBALS['TL_CSS'][] = '/bundles/bilderalt/css/batch.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/bilderalt/js/batch.js';

        return $this->render('@BilderAlt/Backend/bilder_alt_batch_index.html.twig', [
            'title' => 'SEO Alt Text Generator',
            'headline' => 'SEO Alt Text Generator',
            'imageFiles' => $images,
            'credits' => $creditsInfo['credits'],
            'back_link' => '/contao/tl_files',
        ]);
    }
}
