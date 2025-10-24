<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\config\Constants;
use Bluebranch\BilderAlt\Security\BilderAltPermissions;
use Contao\Backend;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\DataContainer;
use Contao\FilesModel;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;

class BackendTlFilesListener extends Backend
{
    const IMAGE_AI = 'bundles/bilderalt/icons/ai.svg';
    const IMAGE_AI_NO_ALT = 'bundles/bilderalt/icons/ai-no-alt.svg';

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    public function __construct(
        RequestStack $requestStack,
        ScopeMatcher $scopeMatcher
    )
    {
        parent::__construct();

        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * Hook: ausgeführt beim Laden eines DCA
     */
    public function __invoke(string $table): void
    {
        if ($table !== 'tl_files' || $this->isFrontend()) {
            return;
        }

        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/bilderalt/js/tl_files.js|static';
        $GLOBALS['TL_CSS'][] = 'bundles/bilderalt/css/tl_files.css';


        if (BilderAltPermissions::canCreateSingle()) {
            $GLOBALS['TL_DCA']['tl_files']['list']['operations']['bilder_alt_button'] = [
                'label' => ['Alt Text', 'Alt Text generieren'],
                'href' => 'key=',
                'icon' => self::IMAGE_AI,
                'button_callback' => [self::class, 'renderButton'],
            ];
        }

        if (BilderAltPermissions::canCreateBatch()) {
            $GLOBALS['TL_DCA']['tl_files']['select']['buttons_callback'][] = [self::class, 'modifySelectButtons'];

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

        $model = FilesModel::findByPath(urldecode($row['id']));
        $hasAltInAllLanguages = true;

        if ($model !== null) {
            $meta = StringUtil::deserialize($model->meta);
            if (is_array($meta)) {
                if (empty($meta)) {
                    $hasAltInAllLanguages = false;
                }
                foreach ($meta as $lang => $metaEntry) {
                    if (empty($metaEntry['alt'])) {
                        $hasAltInAllLanguages = false;
                        break;
                    }
                }
            } else {
                $hasAltInAllLanguages = false;
            }
        } else {
            $hasAltInAllLanguages = false;
        }

        if (!$hasAltInAllLanguages) {
            $icon = self::IMAGE_AI_NO_ALT;
        }

        $href = self::addToUrl($href . '&id=' . $row['id']);

        return sprintf(
            '<a href="%s" title="%s" data-file-path="%s" onclick="generateImageTag(event, this)" class="bilder_alt_button %s">%s</a>',
            $href,
            StringUtil::specialchars($title),
            //$attributes,
            $row['id'],
            $hasAltInAllLanguages ?: 'no-alt',
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

    public function isFrontend(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return false;
        }

        return $this->scopeMatcher->isFrontendRequest($request);
    }
}
