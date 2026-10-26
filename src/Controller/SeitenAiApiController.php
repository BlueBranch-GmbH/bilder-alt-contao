<?php

namespace Bluebranch\BilderAlt\Controller;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Bluebranch\BilderAlt\Security\BilderAltPermissions;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\ReadAction;
use Contao\CoreBundle\Security\DataContainer\UpdateAction;
use Contao\PageModel;
use Contao\Versions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/contao/bilder-alt/api/v1/page", defaults={"_scope" = "backend"})
 */
class SeitenAiApiController extends AbstractController
{
    private BilderAlt $bilderAlt;
    private ContaoFramework $framework;

    public function __construct(BilderAlt $bilderAlt, ContaoFramework $framework)
    {
        $this->bilderAlt = $bilderAlt;
        $this->framework = $framework;
    }

    /**
     * @Route("/generate-title", name="bilder_alt_page_generate_title", methods={"POST"})
     */
    public function generateTitle(Request $request): JsonResponse
    {
        $this->framework->initialize();

        if (!BilderAltPermissions::canCreatePageTitle()) {
            return $this->buildError('Keine Berechtigung zum Generieren von Seitentiteln', Response::HTTP_FORBIDDEN);
        }

        $context = $this->loadPage($request, 'pageTitle');
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $result = $this->bilderAlt->generatePageTitle($context['pageUrl'], $context['apiKey'], $context['language'], $context['keywords']);

        if ($result['success'] && $context['save']) {
            $error = $this->savePageField($context['page'], 'pageTitle', $result['title']);
            if (null !== $error) {
                return $error;
            }
        }

        return $this->json($result);
    }

    /**
     * @Route("/generate-description", name="bilder_alt_page_generate_description", methods={"POST"})
     */
    public function generateDescription(Request $request): JsonResponse
    {
        $this->framework->initialize();

        if (!BilderAltPermissions::canCreatePageDescription()) {
            return $this->buildError('Keine Berechtigung zum Generieren von Seitenbeschreibungen', Response::HTTP_FORBIDDEN);
        }

        $context = $this->loadPage($request, 'description');
        if ($context instanceof JsonResponse) {
            return $context;
        }

        $result = $this->bilderAlt->generatePageDescription($context['pageUrl'], $context['apiKey'], $context['language'], $context['keywords']);

        if ($result['success'] && $context['save']) {
            $error = $this->savePageField($context['page'], 'description', $result['description']);
            if (null !== $error) {
                return $error;
            }
        }

        return $this->json($result);
    }

    private function loadPage(Request $request, string $field): array|JsonResponse
    {
        $pageId = (int) $request->request->get('pageId');
        $save   = (bool) $request->request->get('save', false);

        if (!$pageId) {
            return $this->buildError('Keine Seiten-ID angegeben', Response::HTTP_BAD_REQUEST);
        }

        $apiKey = $this->bilderAlt->getApiKey();
        if (!$apiKey) {
            return $this->buildError('Kein API-Key konfiguriert', Response::HTTP_UNAUTHORIZED);
        }

        $page = PageModel::findByPk($pageId);
        if (!$page) {
            return $this->buildError('Seite nicht gefunden', Response::HTTP_NOT_FOUND);
        }

        if (!$this->isGranted(ContaoCorePermissions::DC_PREFIX . 'tl_page', new ReadAction('tl_page', $page->row()))) {
            return $this->buildError('Kein Zugriff auf diese Seite', Response::HTTP_FORBIDDEN);
        }

        // Prüfen, bevor Credits verbraucht werden (Platzhalter, damit die Feldprüfung eine Änderung erkennt)
        if ($save && !$this->canUpdateField($page, $field, '__bilder_alt__')) {
            return $this->buildError('Keine Berechtigung zum Bearbeiten dieser Seite oder des Feldes', Response::HTTP_FORBIDDEN);
        }

        if (!$page->published) {
            return $this->buildError(
                'Die Seite "' . $page->title . '" ist nicht veröffentlicht. Bitte veröffentliche die Seite zuerst, damit die KI den Inhalt abrufen kann.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $page->loadDetails();

        return [
            'page'     => $page,
            'save'     => $save,
            'apiKey'   => $apiKey,
            'pageUrl'  => $page->getAbsoluteUrl(),
            'language' => $this->bilderAlt->getLanguage($page->rootLanguage ?? 'de'),
            'keywords' => Config::get('bilderAltKeywords') ?? '',
        ];
    }

    private function canUpdateField(PageModel $page, string $field, string $value): bool
    {
        return $this->isGranted(
            ContaoCorePermissions::DC_PREFIX . 'tl_page',
            new UpdateAction('tl_page', $page->row(), [$field => $value])
        );
    }

    /**
     * Speichert das Feld über das Model und legt eine Version an.
     */
    private function savePageField(PageModel $page, string $field, string $value): ?JsonResponse
    {
        // Frisch laden: loadDetails() überschreibt Felder mit vererbten Werten, die nicht gespeichert werden dürfen
        $record = PageModel::findByPk((int) $page->id);
        if (null === $record) {
            return $this->buildError('Seite nicht gefunden', Response::HTTP_NOT_FOUND);
        }

        if (!$this->canUpdateField($record, $field, $value)) {
            return $this->buildError('Keine Berechtigung zum Bearbeiten dieser Seite oder des Feldes', Response::HTTP_FORBIDDEN);
        }

        $versions = new Versions('tl_page', (int) $record->id);
        $versions->initialize();

        $record->$field = $value;
        $record->tstamp = time();
        $record->save();

        $versions->create();

        return null;
    }

    private function buildError(string $message, int $status = Response::HTTP_INTERNAL_SERVER_ERROR): JsonResponse
    {
        return $this->json(['success' => false, 'message' => $message], $status);
    }
}
