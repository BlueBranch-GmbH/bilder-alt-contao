<?php

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = str_replace(
    '{files_legend',
    '{bilder_alt_legend},bilderAltApiKey,bilderAltAutoGenerate,bilderAltCredits;{files_legend',
    $GLOBALS['TL_DCA']['tl_settings']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['bilderAltApiKey'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['bilderAltApiKey'],
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'tl_class' => 'w100', 'preserveTags' => true]
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['bilderAltAutoGenerate'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['bilderAltAutoGenerate'],
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12']
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
