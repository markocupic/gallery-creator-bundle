<?php

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */

namespace Contao;

/**
 * Reads and writes tl_gallery_creator_pictures
 */
class GalleryCreatorPicturesModel extends \Model
{
	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_gallery_creator_pictures';
}
