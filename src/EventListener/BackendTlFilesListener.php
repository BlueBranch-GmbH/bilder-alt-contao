<?php

namespace Bluebranch\BilderAlt\EventListener;

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

    public function __invoke(string $table): void
    {
        if ($table !== 'tl_files') {
            return;
        }

        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/bilderalt/js/tl_files.js|static';
        $GLOBALS['TL_CSS'][] = 'bundles/bilderalt/css/tl_files.css';

        $GLOBALS['TL_DCA']['tl_files']['list']['operations']['bilder_alt_button'] = [
            'label' => ['Alt Text', 'Alt Text generieren'],
            'href' => 'key=',
            'icon' => 'bundles/bilderalt/icons/ai.svg',
            'button_callback' => [self::class, 'renderButton'],
        ];

        $GLOBALS['TL_DCA']['tl_files']['select']['buttons_callback'][] = [self::class, 'modifySelectButtons'];

        if (Input::post('FORM_SUBMIT') == 'tl_select' && Input::post('bilder_alt_bulk') === 'getSelectedFiles') {
            $session = System::getContainer()->get('request_stack')->getSession();
            $selectedFiles = $session->get('CURRENT')['IDS'] ?? [];

            if (!empty($selectedFiles)) {
                $this->redirect('/contao/bilder-alt/batch');
            }
        }
    }

    public static function renderButton(array $row, string $href, string $label, string $title, string $icon, string $attributes): string
    {
        if (!isset($row['id'])) {
            return '';
        }

        $ext = strtolower(pathinfo($row['id'], PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $imageExtensions, true)) {
            return '';
        }

        $href = self::addToUrl($href . '&id=' . $row['id']);

        return sprintf(
            '<a href="%s" title="%s" %s %s class="bilder_alt_button">%s</a>',
            $href,
            StringUtil::specialchars($title),
            $attributes,
            "--data-file-path=\"{$row['id']}\" onclick=\"generateImageTag(event, this)\"",
            Image::getHtml($icon, $label, 'style="width: 16px; height: 16px;"')
        );
    }

    public function modifySelectButtons(array $buttons, DataContainer $dc): array
    {
        if ($dc->table !== 'tl_files') {
            return $buttons;
        }

        $buttons['bilder_alt_bulk'] = '<button type="submit" class="tl_submit bilder_alt_bulk_button" name="bilder_alt_bulk" value="getSelectedFiles" onclick="Backend.getScrollOffset()">' .
            Image::getHtml('bundles/bilderalt/icons/ai.svg', 'Alt Texte generieren', 'style="width: 16px; height: 16px;"') .
            ' Alt Texte generieren</button>';

        return $buttons;
    }
}