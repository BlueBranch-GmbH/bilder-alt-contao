<?php

namespace Bluebranch\ImageAltAi\Controller;

use Bluebranch\ImageAltAi\classes\ImageAltAi;
use Contao\Config;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

include __DIR__ . '/../config/constants.php';

/**
 * @Route("/contao/image-alt-ai/api/v1", defaults={"_scope" = "backend", "_token_check" = false})
 */
class ImageAltAiApiController extends AbstractController
{
    private ImageAltAi $imageAltAi;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->imageAltAi = new ImageAltAi($httpClient);
    }

    /**
     * Ruft den aktuellen Stand der Credits von der externen API ab.
     *
     * @Route("/credits", name="image_alt_ai_credits", methods={"GET"})
     */
    public function getCredits(): JsonResponse
    {
        $apiKey = Config::get('imageAltAiApiKey');

        if (empty($apiKey)) {
            return new JsonResponse(['success' => false, 'message' => 'Kein API-Key konfiguriert'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $response = $this->imageAltAi->getCreditsBalance($apiKey);

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
     * @Route("/generate/path", name="image_alt_ai_generate_by_path", methods={"POST"})
     */
    public function generateByPath(Request $request): JsonResponse
    {
        $filePath = urldecode($request->request->get('path', ''));
        $keywords = $request->request->get('keywords', '');
        $contextUrl = $request->request->get('contextUrl', '');
        $languages = $this->imageAltAi->getAvailableLanguages();

        if (empty($languages)) {
            return new JsonResponse(['success' => false, 'message' => 'Keine Sprachen gefunden'], Response::HTTP_NOT_FOUND);
        }

        $apiKey = Config::get('imageAltAiApiKey');

        if (empty($filePath)) {
            return new JsonResponse(['success' => false, 'message' => 'Kein Bildpfad angegeben'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => '[Image Alt AI] Nicht unterstütztes Bildformat. Nur JPG, JPEG, PNG, GIF und WEBP werden unterstützt.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $absolutePath = $this->imageAltAi->getAbsolutePathFromRelative($filePath);

            if (!$absolutePath || !file_exists($absolutePath)) {
                return new JsonResponse(['success' => false, 'message' => '[Image Alt AI] Datei nicht gefunden: ' . $filePath], Response::HTTP_NOT_FOUND);
            }

            $file = new File($absolutePath);
            $mimeType = $file->getMimeType();

            if (!str_starts_with($mimeType, 'image/')) {
                return new JsonResponse(['success' => false, 'message' => '[Image Alt AI] Die angegebene Datei ist kein Bild'], Response::HTTP_BAD_REQUEST);
            }

            $responses = [];


            foreach ($languages as $language) {
                $imageContent = fopen($absolutePath, 'r');

                if ($imageContent === false) {
                    return new JsonResponse(['success' => false, 'message' => '[Image Alt AI] Die angegebene Datei konnte nicht geladen werden'], Response::HTTP_NOT_FOUND);
                }

                $responses[] = $this->imageAltAi->sendToExternalApi($imageContent, $filePath, $apiKey, $language, $keywords, $contextUrl);

                fclose($imageContent);
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
            return new JsonResponse(['success' => false, 'message' => '[Image Alt AI] Fehler bei der Verarbeitung: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
