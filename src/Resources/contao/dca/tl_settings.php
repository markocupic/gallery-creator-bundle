<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Markocupic\GalleryCreatorBundle\Widget\Backend\ChmodTable;

PaletteManipulator::create()
    ->addLegend('gallery_creator_chmod_legend', 'chmod_legend', PaletteManipulator::POSITION_AFTER)
    ->addField(['gcDefaultUser', 'gcDefaultGroup', 'gcDefaultChmod'], 'gallery_creator_chmod_legend')
    ->applyToPalette('default', 'tl_settings')
;

// Fields
$GLOBALS['TL_DCA']['tl_settings']['fields']['gcDefaultUser'] = [
    'inputType' => 'select',
    'foreignKey' => 'tl_user.username',
    'eval' => ['chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['gcDefaultGroup'] = [
    'inputType' => 'select',
    'foreignKey' => 'tl_user_group.name',
    'eval' => ['chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['gcDefaultChmod'] = [
    'inputType' => ChmodTable::NAME,
    'eval' => ['tl_class' => 'clr'],
];
