<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\classes\BilderAlt;
use Bluebranch\BilderAlt\config\Constants;
use Bluebranch\BilderAlt\Security\BilderAltPermissions;
use Contao\Backend;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\DataContainer;
use Contao\FilesModel;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\RequestStack;

class BackendTlFilesListener extends Backend
{
    use BackendScopeTrait;

    const IMAGE_AI = 'bundles/bilderalt/icons/ai.svg';
    const IMAGE_AI_NO_ALT = 'bundles/bilderalt/icons/ai-no-alt.svg';

    private RequestStack $requestStack;
    private ScopeMatcher $scopeMatcher;
    private BilderAlt $bilderAlt;

    public function __construct(
        RequestStack $requestStack,
        ScopeMatcher $scopeMatcher,
        BilderAlt $bilderAlt
    )
    {
        parent::__construct();

        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->bilderAlt = $bilderAlt;
    }

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
                'button_callback' => [$this, 'renderButton'],
            ];
        }

        if (BilderAltPermissions::canCreateBatch()) {
            $GLOBALS['TL_DCA']['tl_files']['select']['buttons_callback'][] = [self::class, 'modifySelectButtons'];

            if (
                Input::post('FORM_SUBMIT') === 'tl_select' &&
                Input::post('bilder_alt_bulk') === 'getSelectedFiles'
            ) {
                $session = $this->requestStack->getSession();
                $session->set('CURRENT', ['IDS' => Input::post('IDS')]);
                $selectedFiles = $session->get('CURRENT')['IDS'] ?? [];

                if (!empty($selectedFiles)) {
                    $this->redirect('/contao/bilder-alt/batch');
                }
            }
        }
    }

    public function renderButton(array $row, string $href, string $label, string $title, string $icon, string $attributes): string
    {
        if (empty($row['id'])) {
            return '';
        }

        $ext = strtolower(pathinfo($row['id'], PATHINFO_EXTENSION));
        if (!in_array($ext, Constants::ALLOWED_EXTENSIONS, true)) {
            return '';
        }

        $model = FilesModel::findByPath(urldecode($row['id']));
        $hasAltInAllLanguages = false;

        if ($model !== null) {
            $meta = StringUtil::deserialize($model->meta);
            if (is_array($meta)) {
                $availableLanguages = $this->bilderAlt->getAvailableLanguages();
                if (!empty($availableLanguages)) {
                    $hasAltInAllLanguages = true;
                    foreach (array_keys($availableLanguages) as $lang) {
                        if (empty($meta[$lang]['alt'])) {
                            $hasAltInAllLanguages = false;
                            break;
                        }
                    }
                } else {
                    // Fallback when no root pages are configured: check any existing meta entries
                    $hasAltInAllLanguages = !empty($meta);
                    foreach ($meta as $metaEntry) {
                        if (empty($metaEntry['alt'])) {
                            $hasAltInAllLanguages = false;
                            break;
                        }
                    }
                }
            }
        }

        if (!$hasAltInAllLanguages) {
            $icon = self::IMAGE_AI_NO_ALT;
        }

        $href = self::addToUrl($href . '&id=' . $row['id']);

        return sprintf(
            '<a href="%s" title="%s" data-file-path="%s" onclick="generateImageTag(event, this)" class="bilder_alt_button %s">%s</a>',
            $href,
            StringUtil::specialchars($title),
            $row['id'],
            $hasAltInAllLanguages ?: 'no-alt',
            Image::getHtml($icon, $label, 'style="width: 16px; height: 16px;"')
        );
    }

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
