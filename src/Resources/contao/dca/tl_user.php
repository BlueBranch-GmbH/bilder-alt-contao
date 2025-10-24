<?php

/**
 * Extend tl_user palettes
 */
$GLOBALS['TL_DCA']['tl_user']['palettes']['extend'] = str_replace(
    'formp;',
    'formp;{bilder_alt_legend},bilder_alt;',
    $GLOBALS['TL_DCA']['tl_user']['palettes']['extend']
);

$GLOBALS['TL_DCA']['tl_user']['palettes']['custom'] = str_replace(
    'formp;',
    'formp;{bilder_alt_legend},bilder_alt;',
    $GLOBALS['TL_DCA']['tl_user']['palettes']['custom']
);

/**
 * Add fields to tl_user
 */
$GLOBALS['TL_DCA']['tl_user']['fields']['bilder_alt'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_user']['bilder_alt'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'options'   => ['create_single', 'create_batch', 'create_auto_upload'],
    'reference' => &$GLOBALS['TL_LANG']['tl_user']['bilder_alt_permissions'],
    'eval'      => ['multiple' => true],
    'sql'       => "blob NULL"
];
