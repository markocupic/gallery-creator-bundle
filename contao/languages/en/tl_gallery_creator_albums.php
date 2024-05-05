<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

/*
 * Legends
 */
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['title_legend'] = 'Album settings';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['meta_legend'] = 'Metadata';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['details_legend'] = 'Album details';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['caption_legend'] = 'Album caption settings';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['uploadDir_legend'] = 'Upload directory settings';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['protection_legend'] = 'Protect album';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['upload_settings_legend'] = 'Image settings';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['insert_article_legend'] = 'Insert articles before or after the album';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['uploader_legend'] = 'Uploader';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['maintenance'] = 'Revise tables';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['album_preview_thumb_legend'] = 'Picture and sorting settings';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['gallery_creator_chmod_legend'] = 'Album access rights';

/*
 * Fields
 */
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['id'] = ['Album-ID'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['alias'] = ['Album alias', 'The album alias defines although the album folder name.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['pageTitle'] = ['Page title', 'Here you can add a custom meta title to overwrite the default page title.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['robots'] = ['Robots tag', 'Here you can overwrite how search engines handle the event.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['published'] = ['Publish album'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['date'] = ['Date of creation'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['location'] = ['Event location', 'Set the event location.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['teaser'] = ['Event teaser', 'The event teaser can be shown in an album list instead of the full album-caption.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['description'] = ['Meta page description', 'Here you can enter a short description of the page which will be evaluated by search engines like Google or Yahoo. Search engines usually indicate between 150 and 300 characters.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['name'] = ['Album name', 'Here you can define the album name.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['photographer'] = ['Photographer', 'Please add photographers names.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['captionType'] = ['Album caption type', 'Select the caption type, please.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['caption'] = ['Album-caption'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['markdownCaption'] = ['Album-caption (markdown)', 'Use markdown to style the album caption. https://www.markdownguide.org/basic-syntax'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['thumb'] = ['Thumbnail', 'Drag the items to re-order them.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['includeChmod'] = ['Assign access rights', 'Access rights determine what back end users are allowed to do with the album.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['cuser'] = ['Album owner', 'Please select the album owner.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['cgroup'] = ['Album owner group', 'Please select the album owner group.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['chmod'] = ['Access rights', 'Please select the access rights for the album and its childalbums.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['protected'] = ['Protect album', 'Allow the album to logged in front end users only.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['groups'] = ['Allowed frontend groups', 'Allow watching the album to these member groups only.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['insertArticlePre'] = ['Insert article optionally before the album', 'Insert the id of the article that you optionally like have displayed in the detail view.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['insertArticlePost'] = ['Insert article optionally after the album', 'Insert the id of the article that you optionally like have displayed in the detail view.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['fileUpload'] = ['File Upload', 'Browse your local computer and select the files you want to upload to the server.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['uploader'] = ['Uploader', 'Please choose the uploader.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['imageResolution'] = ['Image width', 'During the upload process the image resolution will be scaled to the selected value.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['preserveFilename'] = ['Preserve the original filename', 'Otherwise the filename will be generated automatically.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['visitors'] = ['Number of visitors', 'Set the number of album visitors.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['sortBy'] = ['Re-order images by', 'Please choose the sort order.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['assignedDir'] = ['Assigned directory', 'New images will be uploaded to this directory.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['filePrefix'] = ['Rename all pictures and use a prefix', 'Enter a valid file prefix to rename all pictures of this album (e.g. "me-in-paris-2012").'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['acceptedFiles'] = ['Accepted files', 'Accepted files: %s.'];

/*
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reviseTablesBtn'] = ['Revise tables'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reviseDatabase'] = ['Revise tables', 'Remove orphaned/incorrect entries'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reviseTablesBtn'] = 'Revise database';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['new'] = ['New album', 'Create a new album.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['edit'] = ['Edit and list pictures', 'Edit and list pictures of album with ID %s.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['editheader'] = ['Change album settings', 'Change the album settings with ID %s.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['delete'] = ['Delete album', 'Delete album with ID %s.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['toggle'] = ['Toggle visibility', 'Toggle visibility of album ID %s'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['uploadImages'] = ['Upload images', 'Upload images to album with ID %s.'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['importImagesFromFilesystem'] = ['Import images from the Contao file system', 'Import images from the Contao filesystem on to the album with ID %s'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['cut'] = ['Move album', 'Move album with ID %s'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['pasteafter'] = ['Paste after', 'Paste after album ID %s'];
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['pasteinto'] = ['Paste into', 'Paste into album ID %s'];

/*
 * References
 */
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['no_scaling'] = 'Do not scale images during the upload process.';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['name_asc'] = 'File name (ascending)';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['name_desc'] = 'File name (descending)';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['date_asc'] = 'Date (ascending)';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['date_desc'] = 'Date (descending)';
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['custom'] = 'Custom order';

/*
 * Messages
 */
$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['messages']['reviseDatabase'] = 'Revise tables: Clean the database from damaged/invalid/orphaned entries.';
