<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

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

$GLOBALS['TL_DCA']['tl_user_group']['fields']['seiten_alt'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_user_group']['seiten_alt'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'options'   => ['create_page_title', 'create_page_description', 'create_page_batch'],
    'reference' => &$GLOBALS['TL_LANG']['tl_user_group']['seiten_alt_permissions'],
    'eval'      => ['multiple' => true],
    'sql'       => "blob NULL"
];

/**
 * Extend tl_user_group palettes
 */
PaletteManipulator::create()
    ->addLegend('bilder_alt_legend', 'amg_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('bilder_alt', 'bilder_alt_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('seiten_alt', 'bilder_alt_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_user_group');
