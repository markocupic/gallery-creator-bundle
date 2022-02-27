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

use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Widget\Backend\AlbumTreeWidget;
use Markocupic\GalleryCreatorBundle\Widget\Backend\ChmodTable;

/*
 * Back end module
 */
$GLOBALS['BE_MOD']['content']['gallery_creator'] = [
    'icon' => 'bundles/markocupicgallerycreator/images/picture.png',
    'tables' => [
        'tl_gallery_creator_albums',
        'tl_gallery_creator_pictures',
    ],
];

// Register contao models
$GLOBALS['TL_MODELS']['tl_gallery_creator_albums'] = GalleryCreatorAlbumsModel::class;
$GLOBALS['TL_MODELS']['tl_gallery_creator_pictures'] = GalleryCreatorPicturesModel::class;

// Backend widgets
$GLOBALS['BE_FFL'][AlbumTreeWidget::NAME] = AlbumTreeWidget::class;
$GLOBALS['BE_FFL'][ChmodTable::NAME] = ChmodTable::class;
