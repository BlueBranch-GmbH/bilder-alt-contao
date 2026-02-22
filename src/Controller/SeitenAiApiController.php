<?php

namespace Bluebranch\BilderAlt\Controller;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\PageModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @Route("/contao/bilder-alt/api/v1/page", defaults={"_scope" = "backend", "_token_check" = false})
 */
class SeitenAiApiController extends AbstractController
{
    private BilderAlt $bilderAlt;
    private ContaoFramework $framework;

    public function __construct(HttpClientInterface $httpClient, ContaoFramework $framework)
    {
        $this->bilderAlt = new BilderAlt($httpClient);
        $this->framework = $framework;
    }

    /**
     * @Route("/generate-title", name="bilder_alt_page_generate_title", methods={"POST"})
     */
    public function generateTitle(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $pageId = (int) $request->request->get('pageId');
        $save   = (bool) $request->request->get('save', false);

        if (!$pageId) {
            return $this->buildError('Keine Seiten-ID angegeben', Response::HTTP_BAD_REQUEST);
        }

        $apiKey = Config::get('bilderAltApiKey') ?: null;
        if (!$apiKey) {
            return $this->buildError('Kein API-Key konfiguriert', Response::HTTP_UNAUTHORIZED);
        }

        $page = PageModel::findByPk($pageId);
        if (!$page) {
            return $this->buildError('Seite nicht gefunden', Response::HTTP_NOT_FOUND);
        }

        if (!$page->published) {
            return $this->buildError(
                'Die Seite "' . $page->title . '" ist nicht veröffentlicht. Bitte veröffentliche die Seite zuerst, damit die KI den Inhalt abrufen kann.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $page->loadDetails();
        $pageUrl  = $page->getAbsoluteUrl();
        $language = $this->bilderAlt->getLanguage($page->rootLanguage ?? 'de');
        $keywords = Config::get('bilderAltKeywords') ?? '';

        $result = $this->bilderAlt->generatePageTitle($pageUrl, $apiKey, $language, $keywords);

        if ($result['success'] && $save) {
            Database::getInstance()
                ->prepare('UPDATE tl_page SET pageTitle=?, tstamp=? WHERE id=?')
                ->execute($result['title'], time(), $pageId);
        }

        return $this->json($result);
    }

    /**
     * @Route("/generate-description", name="bilder_alt_page_generate_description", methods={"POST"})
     */
    public function generateDescription(Request $request): JsonResponse
    {
        $this->framework->initialize();

        $pageId = (int) $request->request->get('pageId');
        $save   = (bool) $request->request->get('save', false);

        if (!$pageId) {
            return $this->buildError('Keine Seiten-ID angegeben', Response::HTTP_BAD_REQUEST);
        }

        $apiKey = Config::get('bilderAltApiKey') ?: null;
        if (!$apiKey) {
            return $this->buildError('Kein API-Key konfiguriert', Response::HTTP_UNAUTHORIZED);
        }

        $page = PageModel::findByPk($pageId);
        if (!$page) {
            return $this->buildError('Seite nicht gefunden', Response::HTTP_NOT_FOUND);
        }

        if (!$page->published) {
            return $this->buildError(
                'Die Seite "' . $page->title . '" ist nicht veröffentlicht. Bitte veröffentliche die Seite zuerst, damit die KI den Inhalt abrufen kann.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $page->loadDetails();
        $pageUrl  = $page->getAbsoluteUrl();
        $language = $this->bilderAlt->getLanguage($page->rootLanguage ?? 'de');
        $keywords = Config::get('bilderAltKeywords') ?? '';

        $result = $this->bilderAlt->generatePageDescription($pageUrl, $apiKey, $language, $keywords);

        if ($result['success'] && $save) {
            Database::getInstance()
                ->prepare('UPDATE tl_page SET description=?, tstamp=? WHERE id=?')
                ->execute($result['description'], time(), $pageId);
        }

        return $this->json($result);
    }

    private function buildError(string $message, int $status = Response::HTTP_INTERNAL_SERVER_ERROR): JsonResponse
    {
        return $this->json(['success' => false, 'message' => $message], $status);
    }
}
