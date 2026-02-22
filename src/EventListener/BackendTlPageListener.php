<?php

namespace Bluebranch\BilderAlt\EventListener;

use Bluebranch\BilderAlt\Security\BilderAltPermissions;
use Contao\Backend;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;

class BackendTlPageListener extends Backend
{
    private RequestStack $requestStack;
    private ScopeMatcher $scopeMatcher;

    public function __construct(RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        parent::__construct();
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    public function __invoke(string $table): void
    {
        if ($table !== 'tl_page' || $this->isFrontend()) {
            return;
        }

        $GLOBALS['TL_CSS'][] = 'bundles/bilderalt/css/tl_page_ai.css';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/bilderalt/js/tl_page_ai.js|static';

        if (BilderAltPermissions::canCreatePageTitle() || BilderAltPermissions::canCreatePageDescription()) {
            $GLOBALS['TL_DCA']['tl_page']['edit']['buttons_callback'][] = [self::class, 'addEditButtons'];
        }

        if (BilderAltPermissions::canCreatePageBatch()) {
            $GLOBALS['TL_DCA']['tl_page']['list']['global_operations']['bilder_alt_seiten_batch'] = [
                'label' => ['KI SEO-Texte generieren', 'Seitentitel und Beschreibungen mit KI generieren'],
                'href' => '',
                'icon' => 'bundles/bilderalt/icons/ai.svg',
                'button_callback' => [self::class, 'renderBatchGlobalButton'],
            ];
        }
    }

    public function addEditButtons(array $buttons, DataContainer $dc): array
    {
        $aiIcon = Image::getHtml('bundles/bilderalt/icons/ai.svg', '', 'style="width: 16px; height: 16px; vertical-align: middle;"');

        if (BilderAltPermissions::canCreatePageTitle()) {
            $buttons['bilder_alt_generate_title'] = sprintf(
                '<button type="button" class="tl_submit bilder_alt_page_btn" onclick="seitenAiGenerateTitle(%d, this)">%s Titel generieren</button>',
                $dc->id,
                $aiIcon
            );
        }

        if (BilderAltPermissions::canCreatePageDescription()) {
            $buttons['bilder_alt_generate_description'] = sprintf(
                '<button type="button" class="tl_submit bilder_alt_page_btn" onclick="seitenAiGenerateDescription(%d, this)">%s Beschreibung generieren</button>',
                $dc->id,
                $aiIcon
            );
        }

        return $buttons;
    }

    public static function renderBatchGlobalButton(string $href, string $label, string $title, string $class, string $attributes): string
    {
        $aiIcon = Image::getHtml('bundles/bilderalt/icons/ai.svg', $label, 'style="width: 16px; height: 16px;"');

        return sprintf(
            '<a href="/contao/bilder-alt/seiten-batch" class="%s" title="%s">%s %s</a> ',
            $class,
            StringUtil::specialchars($title),
            $aiIcon,
            $label
        );
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
