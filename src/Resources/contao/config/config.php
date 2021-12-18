<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

use Contao\Input;
use Markocupic\GalleryCreatorBundle\Listener\ContaoHook\InitializeSystem;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;

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
