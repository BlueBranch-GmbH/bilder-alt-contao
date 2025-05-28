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
use Symfony\Component\Routing\Annotation\Route as Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @Route("/contao/bilder-alt/api/v1", defaults={"_scope" = "backend", "_token_check" = false})
 */
class BilderAltApiController extends AbstractController
{
    private BilderAlt $bilderAlt;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->bilderAlt = new BilderAlt($httpClient);
    }

    /**
     * Ruft den aktuellen Stand der Credits von der externen API ab.
     *
     * @Route("/credits", name="bilder_alt_credits", methods={"GET"})
     */
    public function getCredits(): JsonResponse
    {
        $apiKey = Config::get('bilderAltApiKey');

        if (empty($apiKey)) {
            return new JsonResponse(['success' => false, 'message' => 'Kein API-Key konfiguriert'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $response = $this->bilderAlt->getCreditsBalance($apiKey);

            return new JsonResponse($response);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Fehler: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verarbeitet ein Bild anhand seines Pfades und leitet es an die externe API weiter.
     *
     * @Route("/generate/path", name="bilder_alt_generate_by_path", methods={"POST"})
     */
    public function generateByPath(Request $request): JsonResponse
    {
        $filePath = urldecode($request->request->get('path', ''));
        $keywords = $request->request->get('keywords', '');
        $contextUrl = $request->request->get('contextUrl', '');
        $languages = $this->bilderAlt->getAvailableLanguages();

        if (empty($languages)) {
            return new JsonResponse(['success' => false, 'message' => 'Keine Sprachen gefunden'], Response::HTTP_NOT_FOUND);
        }

        $apiKey = Config::get('bilderAltApiKey');

        if (empty($filePath)) {
            return new JsonResponse(['success' => false, 'message' => 'Kein Bildpfad angegeben'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (!in_array($extension, Constants::ALLOWED_EXTENSIONS, true)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '[Bilder Alt] Nicht unterstütztes Bildformat. Nur JPG, JPEG, PNG, GIF und WEBP werden unterstützt.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $absolutePath = $this->bilderAlt->getAbsolutePathFromRelative($filePath);

            if (!$absolutePath || !file_exists($absolutePath)) {
                return new JsonResponse(['success' => false, 'message' => '[Bilder Alt] Datei nicht gefunden: ' . $filePath], Response::HTTP_NOT_FOUND);
            }

            $file = new File($absolutePath);
            $mimeType = $file->getMimeType();

            if (!str_starts_with($mimeType, 'image/')) {
                return new JsonResponse(['success' => false, 'message' => '[Bilder Alt] Die angegebene Datei ist kein Bild'], Response::HTTP_BAD_REQUEST);
            }

            $responses = [];

            foreach ($languages as $language) {
                $responses[] = $this->bilderAlt->sendToExternalApi($filePath, $apiKey, $language, $keywords, $contextUrl);
            }

            $errorResponses = array_filter($responses, function ($response) {
                return isset($response['success']) && $response['success'] === false;
            });

            $response = [
                'success' => !(count($errorResponses) > 0),
                'data' => $responses,
            ];

            return new JsonResponse($response);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => '[Bilder Alt] Fehler bei der Verarbeitung: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
