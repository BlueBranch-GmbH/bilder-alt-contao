<?php

/**
 * Extend tl_user_group palettes
 */
$GLOBALS['TL_DCA']['tl_user_group']['palettes']['default'] = str_replace(
    'formp;',
    'formp;{bilder_alt_legend},bilder_alt;',
    $GLOBALS['TL_DCA']['tl_user_group']['palettes']['default']
);

/**
 * Add fields to tl_user_group
 */
$GLOBALS['TL_DCA']['tl_user_group']['fields']['bilder_alt'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_user_group']['bilder_alt'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'options'   => ['create_single', 'create_batch', 'create_auto_upload'],
    'reference' => &$GLOBALS['TL_LANG']['tl_user_group']['bilder_alt_permissions'],
    'eval'      => ['multiple' => true],
    'sql'       => "blob NULL"
];
