<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

use Markocupic\GalleryCreatorBundle\Controller\ContentElement\GalleryCreatorController;
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\GalleryCreatorNewsController;

/*
 * Back end modules
 */
$GLOBALS['TL_LANG']['MOD']['gallery_creator'] = ['Gallery Creator', 'Create and edit album-galleries.'];

/*
 * Front end content elements
 */
$GLOBALS['TL_LANG']['CTE']['gallery_creator_elements'] = 'Gallery Creator';
$GLOBALS['TL_LANG']['CTE'][GalleryCreatorController::TYPE] = ['Gallery Creator', 'Include a Gallery Creator content element to your article.'];
$GLOBALS['TL_LANG']['CTE'][GalleryCreatorNewsController::TYPE] = ['Gallery Creator News', 'Include a gallery creator album.'];
