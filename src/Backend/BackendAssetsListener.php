<?php

namespace Bluebranch\ImageAltAi\Backend;

use Contao\Backend;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\Image;
use Contao\StringUtil;

/**
 * @Hook("loadDataContainer")
 */
class BackendAssetsListener extends Backend
{
    public function __invoke(string $table): void
    {
        if ($table !== 'tl_files') {
            return;
        }

        // JavaScript für die Dateiverwaltung laden
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/imagealtai/js/tl_files.js|static';
        $GLOBALS['TL_CSS'][] = 'bundles/imagealtai/css/tl_files.css';

        // Button einfügen
        $GLOBALS['TL_DCA']['tl_files']['list']['operations']['image_alt_ai_button'] = [
            'label' => ['Alt Text', 'Alt Text generieren'],
            'href' => 'key=',
            'icon' => 'bundles/imagealtai/icons/ai.svg',
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
            '<a href="%s" title="%s" %s %s class="image_alt_ai_button">%s</a>',
            $href,
            StringUtil::specialchars($title),
            $attributes,
            "--data-file-path=\"{$row['id']}\" onclick=\"generateImageTag(event, this)\"",
            Image::getHtml($icon, $label, 'style="width: 16px; height: 16px;"')
        );
    }
}