<?php

namespace Bluebranch\BilderAlt\Controller;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Contao\Config;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// @keep
use Symfony\Component\Routing\Annotation\Route as Route;

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

    /**
     * Rekursive Methode zum Finden von Bildern in Ordnern und Unterordnern
     *
     * @param string $folderUuid UUID des Ordners
     * @param array &$imageFiles Array zum Speichern der gefundenen Bild-UUIDs
     * @param array $imageExtensions Erlaubte Bilderweiterungen
     */
    private function findImagesRecursively(string $folderUuid, array &$imageFiles, array $imageExtensions): void
    {
        $filesInFolder = \Contao\FilesModel::findByPid($folderUuid, ['order' => 'name']);

        if ($filesInFolder === null) {
            return;
        }

        while ($filesInFolder->next()) {
            // Rekursiv durch Unterordner gehen
            if ($filesInFolder->type === 'folder') {
                $this->findImagesRecursively($filesInFolder->uuid, $imageFiles, $imageExtensions);
            }
            // Bild-Dateien hinzufügen
            elseif (in_array(strtolower(pathinfo($filesInFolder->path, PATHINFO_EXTENSION)), $imageExtensions, true)) {
                $imageFiles[] = $filesInFolder->uuid;
            }
        }
    }

    public function __invoke(Request $request): Response
    {
        $this->framework->initialize();

        // Holen der Session-Daten
        $session = $request->getSession();
        $data = $session->all();
        $selectedFiles = $data['CURRENT']['IDS'] ?? [];

        // Filtere nach Bilddateien
        $imageFiles = [];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        foreach ($selectedFiles as $file) {
            var_dump($file);
            // Prüfen, ob es sich um einen Ordner handelt
            $fileModel = \Contao\FilesModel::findById($file);

            var_dump($fileModel);

            if ($fileModel !== null) {
                if ($fileModel->type === 'folder') {
                    // Rekursiv alle Dateien im Ordner finden
                    $filesInFolder = \Contao\FilesModel::findByPid($fileModel->uuid, ['order' => 'name']);

                    if ($filesInFolder !== null) {
                        while ($filesInFolder->next()) {
                            // Rekursiv durch Unterordner gehen
                            if ($filesInFolder->type === 'folder') {
                                $this->findImagesRecursively($filesInFolder->uuid, $imageFiles, $imageExtensions);
                            } 
                            // Bild-Dateien hinzufügen
                            elseif (in_array(strtolower(pathinfo($filesInFolder->path, PATHINFO_EXTENSION)), $imageExtensions, true)) {
                                $imageFiles[] = $filesInFolder->uuid;
                            }
                        }
                    }
                } 
                // Einzelne Datei prüfen
                else {
                    $ext = strtolower(pathinfo($fileModel->path, PATHINFO_EXTENSION));
                    if (in_array($ext, $imageExtensions, true)) {
                        $imageFiles[] = $file;
                    }
                }
            }
        }

        var_dump($imageFiles);;

        $apiKey = Config::get('bilderAltApiKey');
        $hasApiKey = !empty($apiKey);

        // Hole die Anzahl der verfügbaren Credits, wenn ein API-Key vorhanden ist
        $creditsInfo = [];
        if ($hasApiKey) {
            try {
                $creditsInfo = $this->bilderAlt->getCreditsBalance($apiKey);
            } catch (\Exception $e) {
                $creditsInfo = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        $content = '<a href="' . str_replace('/contao/bilder-alt/batch', '/contao/tl_files', $request->getUri()) . '" class="header_back" title="Zurück">';


        $GLOBALS['TL_CSS'][] = '/bundles/bilderalt/css/batch.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/bilderalt/js/batch.js';

        return $this->render('@BilderAlt/Backend/bilder_alt_batch_index.html.twig', [
            'title' => 'SEO Alt Text Generator',
            'headline' => 'SEO Alt Text Generator',
            'imageFiles' => $imageFiles,
            'credits' => $creditsInfo['credits'],
            'back_link' => '/contao/tl_files'
        ]);
    }
}
