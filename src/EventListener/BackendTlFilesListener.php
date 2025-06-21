<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\config\Constants;
use Contao\Backend;
use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;

class BackendTlFilesListener extends Backend
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Hook: ausgeführt beim Laden eines DCA
     */
    public function __invoke(string $table): void
    {
        if ($table !== 'tl_files') {
            return;
        }

        // JS + CSS laden
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/bilderalt/js/tl_files.js|static';
        $GLOBALS['TL_CSS'][] = 'bundles/bilderalt/css/tl_files.css';

        // Einzelbutton in der Dateiliste
        $GLOBALS['TL_DCA']['tl_files']['list']['operations']['bilder_alt_button'] = [
            'label' => ['Alt Text', 'Alt Text generieren'],
            'href' => 'key=',
            'icon' => 'bundles/bilderalt/icons/ai.svg',
            'button_callback' => [self::class, 'renderButton'],
        ];

        // Button unterhalb der Mehrfachauswahl
        $GLOBALS['TL_DCA']['tl_files']['select']['buttons_callback'][] = [self::class, 'modifySelectButtons'];

        // Bulk-Action verarbeiten
        if (
            Input::post('FORM_SUBMIT') === 'tl_select' &&
            Input::post('bilder_alt_bulk') === 'getSelectedFiles'
        ) {
            $session = System::getContainer()->get('request_stack')->getSession();
            $session->set('CURRENT', ['IDS' => Input::post('IDS')]);
            $selectedFiles = $session->get('CURRENT')['IDS'] ?? [];

            if (!empty($selectedFiles)) {
                $this->redirect('/contao/bilder-alt/batch');
            }
        }
    }

    public static function renderButton(array $row, string $href, string $label, string $title, string $icon, string $attributes): string
    {
        if (empty($row['id'])) {
            return '';
        }

        $ext = strtolower(pathinfo($row['id'], PATHINFO_EXTENSION));
        if (!in_array($ext, Constants::ALLOWED_EXTENSIONS, true)) {
            return '';
        }

        $href = self::addToUrl($href . '&id=' . $row['id']);

        return sprintf(
            '<a href="%s" title="%s" %s data-file-path="%s" onclick="generateImageTag(event, this)" class="bilder_alt_button">%s</a>',
            $href,
            StringUtil::specialchars($title),
            $attributes,
            $row['id'],
            Image::getHtml($icon, $label, 'style="width: 16px; height: 16px;"')
        );
    }

    /**
     * Mehrfachauswahl-Button unten ergänzen
     */
    public function modifySelectButtons(array $buttons, DataContainer $dc): array
    {
        if ($dc->table !== 'tl_files') {
            return $buttons;
        }

        $buttons['bilder_alt_bulk'] = sprintf(
            '<button type="submit" class="tl_submit bilder_alt_bulk_button" name="bilder_alt_bulk" value="getSelectedFiles" onclick="Backend.getScrollOffset()">%s Alt Texte generieren</button>',
            Image::getHtml('bundles/bilderalt/icons/ai.svg', 'Alt Texte generieren', 'style="width: 16px; height: 16px;"')
        );

        return $buttons;
    }
}
