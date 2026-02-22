<?php

namespace Bluebranch\BilderAlt\Controller;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Contao\Config;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @Route("%contao.backend.route_prefix%/bilder-alt/seiten-batch", name=self::class, defaults={"_scope" = "backend", "_token_check" = true})
 */
class SeitenAiBatchController extends AbstractBackendController
{
    private BilderAlt $bilderAlt;
    private ContaoFramework $framework;

    public function __construct(HttpClientInterface $httpClient, ContaoFramework $framework)
    {
        $this->bilderAlt = new BilderAlt($httpClient);
        $this->framework = $framework;
    }

    public function __invoke(Request $request): Response
    {
        $this->framework->initialize();

        $pages = $this->loadPages();

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
        $GLOBALS['TL_CSS'][] = '/bundles/bilderalt/css/seiten_batch.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/bilderalt/js/seiten_batch.js';

        return $this->render('@BilderAlt/Backend/seiten_ai_batch_index.html.twig', [
            'title' => 'KI SEO-Texte Generator',
            'headline' => 'KI SEO-Texte Generator',
            'pages' => $pages,
            'credits' => $creditsInfo['credits'],
        ]);
    }

    private function loadPages(): array
    {
        $pages = [];
        $allPages = PageModel::findBy('type', 'regular', ['order' => 'sorting']);

        if (!$allPages) {
            return $pages;
        }

        while ($allPages->next()) {
            $page = $allPages->current();
            $page->loadDetails();

            $pages[] = [
                'id' => $page->id,
                'title' => $page->title,
                'pageTitle' => $page->pageTitle,
                'description' => $page->description,
                'language' => $page->rootLanguage,
                'rootTitle' => $page->rootTitle,
            ];
        }

        return $pages;
    }
}
