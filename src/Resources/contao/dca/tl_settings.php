<?php

use Contao\PageModel;

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = str_replace(
    '{files_legend',
    '{bilder_alt_legend},bilderAltApiKey,bilderAltCredits,bilderAltAutoGenerate,bilderAltAddExistingAltTag,bilderAltKeywords,bilderAltExcludeLanguages;{files_legend',
    $GLOBALS['TL_DCA']['tl_settings']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['bilderAltApiKey'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['bilderAltApiKey'],
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'tl_class' => 'w50', 'preserveTags' => true]
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bilderAltAutoGenerate'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['bilderAltAutoGenerate'],
    'inputType' => 'checkbox',
    'eval' => [
        'tl_class' => 'w50 m12'
    ]
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bilderAltAddExistingAltTag'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['bilderAltAddExistingAltTag'],
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12']
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bilderAltKeywords'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['bilderAltKeywords'],
    'inputType' => 'text',
    'eval' => [
        'mandatory' => false,
        'tl_class' => 'clr w100',
        'preserveTags' => true,
        'maxlength' => 255
    ],
    'save_callback' => [
        ['SettingsCallbacks', 'cleanKeywords']
    ]
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bilderAltCredits'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['bilderAltCredits'],
    'inputType' => 'text',
    'eval' => [
        'readonly' => true,
        'tl_class' => 'w50',
        'preserveTags' => true,
        'disabled' => true,
    ],
    'load_callback' => [
        ['Bluebranch\BilderAlt\EventListener\TlSettingsCallback', 'loadCredits']
    ]
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bilderAltExcludeLanguages'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['bilderAltExcludeLanguages'],
    'inputType' => 'checkboxWizard',
    'options_callback' => ['SettingsCallbacks', 'getAllLanguages'],
    'eval' => [
        'multiple' => true,
        'tl_class' => 'clr w50'
    ]
];

class SettingsCallbacks
{
    public function cleanKeywords($value)
    {
        $keywords = array_filter(array_map('trim', explode(',', $value ?? '')));
        return implode(', ', $keywords);
    }

    public function getAllLanguages(): array
    {
        $languages = [];

        $roots = PageModel::findByType('root');

        if (null === $roots) {
            return $languages;
        }

        foreach ($roots as $root) {
            $lang = $root->language;

            if ($lang !== '') {
                $languages[$lang] = $lang . ($root->fallback ? ' (fallback)' : '');
            }
        }

        ksort($languages);

        return $languages;
    }
}