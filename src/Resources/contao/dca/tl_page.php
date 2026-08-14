<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/**
 * Add fields to tl_page (root pages only) so the alt text prefix/suffix
 * can be overridden per language/root page. Falls back to the global
 * bilderAltAltPrefix/bilderAltAltSuffix settings when left empty.
 */
$GLOBALS['TL_DCA']['tl_page']['fields']['bilderAltAltPrefix'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_page']['bilderAltAltPrefix'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'tl_class' => 'w50', 'maxlength' => 50],
    'save_callback' => [
        ['TlPageCallbacks', 'validateAltAffix']
    ],
    'sql' => "varchar(50) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_page']['fields']['bilderAltAltSuffix'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_page']['bilderAltAltSuffix'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => false, 'tl_class' => 'w50', 'maxlength' => 50],
    'save_callback' => [
        ['TlPageCallbacks', 'validateAltAffix']
    ],
    'sql' => "varchar(50) NOT NULL default ''"
];

PaletteManipulator::create()
    ->addLegend('bilder_alt_legend', 'language_legend', PaletteManipulator::POSITION_AFTER)
    ->addField('bilderAltAltPrefix', 'bilder_alt_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('bilderAltAltSuffix', 'bilder_alt_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('root', 'tl_page')
    ->applyToPalette('rootfallback', 'tl_page');

class TlPageCallbacks
{
    public function validateAltAffix($value)
    {
        return trim((string) $value);
    }
}
