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

/*
 * Error messages
 */
$GLOBALS['TL_LANG']['ERR']['fileNotFound'] = 'The file "%s" doesn\'t exist on your server!';
$GLOBALS['TL_LANG']['ERR']['linkToNotExistingFile'] = 'The db-entry with ID %s in "tl_gallery_pictures" links to a not existing file. <br>Please clean up the database or check the existence of %s in the album with alias: %s!';
$GLOBALS['TL_LANG']['ERR']['uploadError'] = 'The file "%s" could not been uploaded!';
$GLOBALS['TL_LANG']['ERR']['dirNotWriteable'] = 'The directory "%s" is not writeable! Check chmod settings!';
$GLOBALS['TL_LANG']['ERR']['rejectWriteAccessToAlbum'] = 'You are not allowed to edit the album: "%s".';
$GLOBALS['TL_LANG']['ERR']['notAllowedFilenameOrExtension'] = 'Invalid file name or extension: => %s. Please check the list of supported file extensions (%s).';
$GLOBALS['TL_LANG']['ERR']['rotateImageError'] = 'Error while trying to rotate picture "%s".';
$GLOBALS['TL_LANG']['ERR']['notAllowedToDeleteAlbum'] = 'Not enough permissions to delete album with ID %d.';
$GLOBALS['TL_LANG']['ERR']['notAllowedToDeletePicture'] = 'Not enough permissions to delete picture with ID %d.';

/*
 * Confirm
 */
$GLOBALS['TL_LANG']['CONFIRM']['gcDeleteConfirmAlbum'] = 'Do you really want to delete album with ID %s? \\r\\nAttention! \\nAll image-files in the assigned directory will be deleted too!!!';
$GLOBALS['TL_LANG']['CONFIRM']['gcDeleteConfirmPicture'] = 'Do you really want to delete picture with ID %s? \\r\\nAttention! \\nThe image-file will be deleted too!!!';

/*
 * Frontend
 */
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['backLink'] = 'back';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['childAlbums'] = 'Child albums';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['childAlbumsOf'] = 'Child albums of';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['pictures'] = 'pictures';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['contains'] = 'contains';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['visitors'] = 'visitors';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['feAuthenticationError'] = ['Authentication error', 'You tried to enter a protected album. Please log in as a frontend user or check your member-rights.'];
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['photographerName'] = 'Photographers name';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['eventLocation'] = 'Location';

/*
 * Backend
 */
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['selectAllAlbums'] = 'Select all albums.';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['localMedia'] = 'Replace image with a movie or sound from the Contao filesystem.';
$GLOBALS['TL_LANG']['GALLERY_CREATOR']['socialMedia'] = 'Replace image with a social media.';
