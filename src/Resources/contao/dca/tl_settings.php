<?php

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = str_replace(
    '{files_legend',
    '{image_alt_ai_legend},imageAltAiApiKey,imageAltAiAutoGenerate,imageAltAiCredits;{files_legend',
    $GLOBALS['TL_DCA']['tl_settings']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['imageAltAiApiKey'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_settings']['imageAltAiApiKey'],
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'tl_class' => 'w100', 'preserveTags' => true]
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['imageAltAiAutoGenerate'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['imageAltAiAutoGenerate'],
    'inputType' => 'checkbox',
    'eval' => ['tl_class' => 'w50 m12']
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['imageAltAiCredits'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['imageAltAiCredits'],
    'inputType' => 'text',
    'eval' => [
        'readonly' => true,
        'tl_class' => 'w50',
        'preserveTags' => true,
        'disabled' => true,
    ],
    'load_callback' => [
        ['Bluebranch\ImageAltAi\EventListener\TlSettingsCallback', 'loadCredits']
    ]
];
