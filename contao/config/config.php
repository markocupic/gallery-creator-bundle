<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle for Contao CMS.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

use Contao\ArrayUtil;
use Contao\Input;
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\ContentGalleryCreator;
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\ContentGalleryCreatorNews;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;

/**
 * Front end content element
 */
ArrayUtil::arrayInsert($GLOBALS['TL_CTE'], 2, ['ce_type_gallery_creator' => ['gallery_creator_ce_news' => ContentGalleryCreatorNews::class]]);
ArrayUtil::arrayInsert($GLOBALS['TL_CTE'], 2, ['ce_type_gallery_creator' => ['gallery_creator_ce' => ContentGalleryCreator::class]]);

/**
 * Show news content element in the news-module only
 */
if (Input::get('do') === 'news') {
    unset($GLOBALS['TL_CTE']['ce_type_gallery_creator']['gallery_creator_ce']);
}

if (Input::get('do') !== 'news') {
    unset($GLOBALS['TL_CTE']['ce_type_gallery_creator']['gallery_creator_ce_news']);
}

/**
 * Back end module
 */
$GLOBALS['BE_MOD']['content']['gallery_creator'] = [
    'icon'   => 'bundles/markocupicgallerycreator/images/picture.png',
    'tables' => [
        'tl_gallery_creator_albums',
        'tl_gallery_creator_pictures',
    ],
];


/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_gallery_creator_albums'] = GalleryCreatorAlbumsModel::class;
$GLOBALS['TL_MODELS']['tl_gallery_creator_pictures'] = GalleryCreatorPicturesModel::class;
