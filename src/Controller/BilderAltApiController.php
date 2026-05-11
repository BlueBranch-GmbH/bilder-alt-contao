<?php

namespace Bluebranch\BilderAlt\Controller;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Bluebranch\BilderAlt\config\Constants;
use Contao\Config;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/contao/bilder-alt/api/v1", defaults={"_scope" = "backend", "_token_check" = false})
 */
class BilderAltApiController extends AbstractController
{
    private BilderAlt $bilderAlt;

    public function __construct(BilderAlt $bilderAlt)
    {
        $this->bilderAlt = $bilderAlt;
    }

    /**
     * @Route("/credits", name="bilder_alt_credits", methods={"GET"})
     */
    public function getCredits(): JsonResponse
    {
        $apiKey = $this->getApiKey();

        if (!$apiKey) {
            return $this->buildErrorResponse('Kein API-Key konfiguriert', Response::HTTP_BAD_REQUEST);
        }

        try {
            return $this->json($this->bilderAlt->getCreditsBalance($apiKey));
        } catch (\Exception $e) {
            return $this->buildErrorResponse('Fehler: ' . $e->getMessage());
        }
    }

    /**
     * @Route("/generate/path", name="bilder_alt_generate_by_path", methods={"POST"})
     */
    public function generateByPath(Request $request): JsonResponse
    {
        $filePath = urldecode($request->request->get('path', ''));
        $contextUrl = $request->request->get('contextUrl', '');

        if (empty($filePath)) {
            return $this->buildErrorResponse('[Bilder Alt] Kein Bildpfad angegeben');
        }

        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return $this->buildErrorResponse('[Bilder Alt] Fehlender API Key', Response::HTTP_UNAUTHORIZED);
        }

        $languages = $this->bilderAlt->getAvailableLanguages();
        if (empty($languages)) {
            return $this->buildErrorResponse('[Bilder Alt] Keine Sprachen gefunden', Response::HTTP_NOT_FOUND);
        }

        $requestedLangs = $request->request->get('languages', '');
        if (!empty($requestedLangs)) {
            $filter = array_filter(array_map('trim', explode(',', $requestedLangs)));
            $languages = array_filter($languages, function ($code) use ($filter) {
                return in_array($code, $filter, true);
            }, ARRAY_FILTER_USE_KEY);
        }

        if (!$this->isSupportedImage($filePath)) {
            return $this->buildErrorResponse('[Bilder Alt] Nicht unterstütztes Bildformat. Nur JPG, JPEG, PNG, GIF und WEBP werden unterstützt.');
        }

        $absolutePath = $this->bilderAlt->getAbsolutePathFromRelative($filePath);
        if (!$absolutePath || !file_exists($absolutePath)) {
            return $this->buildErrorResponse('[Bilder Alt] Datei nicht gefunden: ' . $filePath, Response::HTTP_NOT_FOUND);
        }

        $file = new File($absolutePath);
        if (!str_starts_with($file->getMimeType(), 'image/')) {
            return $this->buildErrorResponse('[Bilder Alt] Die angegebene Datei ist kein Bild');
        }

        try {
            $keywords = $this->bilderAlt->getKeywords($filePath);
            $responses = [];

            foreach ($languages as $isoCode => $language) {
                $response = $this->bilderAlt->sendToExternalApi(
                    $absolutePath,
                    $apiKey,
                    $language,
                    implode(',', $keywords),
                    $contextUrl,
                    $isoCode
                );
                $response['isoCode'] = $isoCode;
                $responses[] = $response;
            }

            $errors = array_filter($responses, function ($r) { return !($r['success'] ?? false); });

            return $this->json([
                'success' => count($errors) === 0,
                'data' => $responses,
            ]);
        } catch (\Exception $e) {
            return $this->buildErrorResponse('[Bilder Alt] Fehler bei der Verarbeitung: ' . $e->getMessage());
        }
    }

    private function getApiKey(): ?string
    {
        return Config::get('bilderAltApiKey') ?: null;
    }

    private function buildErrorResponse(string $message, int $status = Response::HTTP_INTERNAL_SERVER_ERROR): JsonResponse
    {
        return $this->json(['success' => false, 'message' => $message], $status);
    }

    private function isSupportedImage(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, Constants::ALLOWED_EXTENSIONS, true);
    }
}
