<?php

namespace Bluebranch\BilderAlt\EventListener;

use Contao\Backend;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Image;
use Contao\StringUtil;

/**
 * @Hook("loadDataContainer")
 */
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

        // JavaScript für die Dateiverwaltung laden
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/bilderalt/js/tl_files.js|static';
        $GLOBALS['TL_CSS'][] = 'bundles/bilderalt/css/tl_files.css';

        // Button einfügen
        $GLOBALS['TL_DCA']['tl_files']['list']['operations']['bilder_alt_button'] = [
            'label' => ['Alt Text', 'Alt Text generieren'],
            'href' => 'key=',
            'icon' => 'bundles/bilderalt/icons/ai.svg',
            'button_callback' => [self::class, 'renderButton'],
        ];
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
}