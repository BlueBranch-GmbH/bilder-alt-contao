<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

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

$GLOBALS['TL_DCA']['tl_user']['fields']['seiten_alt'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_user']['seiten_alt'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'options'   => ['create_page_title', 'create_page_description', 'create_page_batch'],
    'reference' => &$GLOBALS['TL_LANG']['tl_user']['seiten_alt_permissions'],
    'eval'      => ['multiple' => true],
    'sql'       => "blob NULL"
];

/**
 * Extend tl_user palettes
 */
PaletteManipulator::create()
    ->addLegend('bilder_alt_legend', 'amg_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('bilder_alt', 'bilder_alt_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('seiten_alt', 'bilder_alt_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('extend', 'tl_user')
    ->applyToPalette('custom', 'tl_user');
