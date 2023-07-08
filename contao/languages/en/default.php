<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

/**
 * Error messages
 */
$GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file'] = 'The file "%s" doesn\'t exist on your server!';
$GLOBALS['TL_LANG']['ERR']['link_to_not_existing_file_1'] = 'The db-entry with ID %s in "tl_gallery_pictures" links to a not existing file. <br>Please clean up the database or check the existence of %s in the album with alias: %s!';
$GLOBALS['TL_LANG']['ERR']['uploadError'] = 'The file "%s" could not been uploaded!';
$GLOBALS['TL_LANG']['ERR']['fileDontExist'] = 'The file "%s" does not exist!';
$GLOBALS['TL_LANG']['ERR']['fileNotReadable'] = 'The file "%s" ist not readable! Check access rights.';
$GLOBALS['TL_LANG']['ERR']['dirNotWriteable'] = 'The directory "%s" is not writeable! Check chmod settings!';
$GLOBALS['TL_LANG']['ERR']['accept_jpg'] = 'Gallery Creator only supports jpeg/jpg files.';


/**
 * Frontend
 */
$GLOBALS['TL_LANG']['gallery_creator']['back_to_general_view'] = 'back to general view';
$GLOBALS['TL_LANG']['gallery_creator']['subalbums'] = 'subalbums';
$GLOBALS['TL_LANG']['gallery_creator']['subalbums_of'] = 'Subalbums of';
$GLOBALS['TL_LANG']['gallery_creator']['pictures'] = 'pictures';
$GLOBALS['TL_LANG']['gallery_creator']['contains'] = 'contains';
$GLOBALS['TL_LANG']['gallery_creator']['visitors'] = 'visitors';
$GLOBALS['TL_LANG']['gallery_creator']['fe_authentication_error'] = array('Authentication error', 'You tried to enter a protected album. Please log in as a frontend user or check your member-rights.');
$GLOBALS['TL_LANG']['gallery_creator']['photographerName'] = 'Photographers name';
$GLOBALS['TL_LANG']['gallery_creator']['eventLocation'] = 'event location';

/**
 * Miscellaneous
 */
$GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmAlbum'] = "Do you really want to delete album with ID %s? \\r\\nAttention! \\nAll image-files in the assigned directory will be deleted too!!!";
$GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmPicture'] = "Do you really want to delete picture with ID %s? \\r\\nAttention! \\nThe image-file will be deleted too!!!";
