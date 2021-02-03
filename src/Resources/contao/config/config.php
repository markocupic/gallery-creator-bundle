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

use Contao\Config;
use Contao\Input;
use Markocupic\GalleryCreatorBundle\Listener\ContaoHook\InitializeSystem;

// Define upload path
Config::set('galleryCreatorUploadPath', Config::get('uploadPath') . '/gallery_creator_albums');



/**
 * Back end module
 */
$GLOBALS['BE_MOD']['content']['gallery_creator'] = array(
	'icon'   => 'bundles/markocupicgallerycreator/images/picture.png',
	'tables' => array(
		'tl_gallery_creator_albums',
		'tl_gallery_creator_pictures'
	)
);

if (TL_MODE === 'BE')
{
	// Check tables script
	if (count($_GET) <= 2 && Input::get('do') === 'gallery_creator' && Input::get('mode') !== 'revise_tables')
	{
		$GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be_check_tables.js';
	}

	// Revise table script
	if (Input::get('do') === 'gallery_creator' && Input::get('mode') === 'revise_tables')
	{
		$GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be_revise_tables.js';
	}

	$GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicgallerycreator/js/gallery_creator_be.js';
	$GLOBALS['TL_CSS'][] = 'bundles/markocupicgallerycreator/css/gallery_creator_be.css';
}

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['initializeSystem'][] = [InitializeSystem::class, 'setContentElements'];
