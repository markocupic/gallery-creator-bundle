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
 * Legends
 */
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['album_info'] = 'albuminformations';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['protection'] = 'protect album';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['upload_settings'] = 'image settings';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['insert_article'] = 'insert articles before or after the album';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['uploader_legend'] = 'uploader';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['maintenance'] = 'Revise tables';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['album_preview_thumb_legend'] = 'Album-preview-thumb-settings & picture sorting';

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['id'] = array('Album-ID');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['alias'] = array('Albumalias', 'The Albumalias defines although the album-foldername.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['published'] = array('Publish Album');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['date'] = array('Date of creation');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['owner'] = array('Albumowner');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['event_location'] = array('Event-location');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['description'] = array('Meta page description', 'Here you can enter a short description of the page which will be evaluated by search engines like Google or Yahoo. Search engines usually indicate between 150 and 300 characters.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['keywords'] = array('Meta keywords', 'Here you can enter a list of comma separated keywords. Keywords, however, are no longer relevant to most search engines (including Google).');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['name'] = array('Albumname');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['owners_name'] = array('Albumowner');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['photographer'] = array('Photographers names', 'Please add photographers names.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['comment'] = array('Album-comment');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['thumb'] = array('Select the thumbnail which represents the Album in the listview', 'Drag the items to re-order them.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['protected'] = array('Protect album');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['groups'] = array('Allowed frontend-groups');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['insert_article_pre'] = array('Insert article optionally before the album','Insert the id of the article that you optionally like have displayed in the detail view.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['insert_article_post'] = array('Insert article optionally after the album','Insert the id of the article that you optionally like have displayed in the detail view.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['fileupload'] = array('File Upload', 'Browse your local computer and select the files you want to upload to the server.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['uploader'] = array('Uploader', 'Please choose the uploader.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['img_resolution'] = array('Image width','During the upload process the image resolution will be scaled to the selected value.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['img_quality'] = array('Image quality/compression','During the upload process the image will be compressed. (100 = best quality)');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['preserve_filename'] = array('Preserve the original filename','Otherwise the filename will be automatically generated.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['visitors'] = array('Number of visitors');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['visitors_details'] = array('Visitors details (ip, browser type, etc.)');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['sortBy'] = array('Re-order images by', 'Please choose the sort order.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['assignedDir'] = array('Assigned directory', 'New images will be uploaded to this directory.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['filePrefix'] = array('Rename all pictures using a file prefix', 'Enter a valid file prefix to rename all pictures of this album (e.g. "me-in-paris-2012").');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['acceptedFiles'] = array('Accepted files', 'Accepted files: %s.');

/**
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reviseTablesBtn'] = array('revise tables');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['revise_tables']['0'] = "Revise tables";
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['revise_tables']['1'] = "Remove orphaned/incorrect entries";
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reviseTablesBtn']['0'] = "Datenbank bereinigen";
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['new']    = array('new album', 'Create a new album.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['list_pictures']   = array('list pictures', 'List pictures of album with ID %s.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['edit_album'] = array('Edit album', 'Edit Album with ID %s.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['delete'] = array('delete album', 'Delete album with ID %s.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['toggle'] = array('Publish/unpublish album','Publish/unpublish album ID %s');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['upload_images'] = array('uplaod images', 'Upload images to album with ID %s.');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['import_images'] = array('copy images from directory on the server', 'copy images from directory on the server into the album with ID %s');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['cut'] = array('move album', 'move album with ID %s');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['pasteafter'] = array('Paste after', 'Paste after album ID %s');
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['pasteinto']  = array('Paste into', 'Paste into album ID %s');

/**
 * References
 */
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reference']['no_scaling'] = 'Do not scale images during the upload process.';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['name_asc'] = 'File name (ascending)';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['name_desc'] = 'File name (descending)';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['date_asc'] = 'Date (ascending)';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['date_desc'] = 'Date (descending)';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['custom'] = 'Custom order';

/**
 * Messages
 */
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['messages']['revise_database'] = 'Revise tables: Clean the database from damaged/invalid/orphaned entries';
