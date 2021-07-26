<?php
/**
 * Gallery Creator Bundle
 * Provide methods for using the gallery_creator extension
 * @copyright  Marko Cupic 2019
 * @license MIT
 * @author     Marko Cupic, Oberkirch, Switzerland ->  mailto: m.cupic@gmx.ch
 * @package    Gallery Creator Bundle
 */



/**
 * module config
 */

//Legends
$GLOBALS['TL_LANG']['tl_content']['pagination_legend'] = 'Pagination settings';
$GLOBALS['TL_LANG']['tl_content']['miscellaneous_legend'] = 'Miscellaneous settings';
$GLOBALS['TL_LANG']['tl_content']['album_listing_legend'] = 'Album listing legend';
$GLOBALS['TL_LANG']['tl_content']['picture_listing_legend'] = 'Picture listing legend';


//Fields
$GLOBALS['TL_LANG']['tl_content']['gc_publish_albums'] = array('Publish these albums only', 'Selected albums will be displayed in the frontend.');
$GLOBALS['TL_LANG']['tl_content']['gc_publish_single_album'] = array('Publish this album', 'The selected album will be displayed in the frontend.');
$GLOBALS['TL_LANG']['tl_content']['gc_publish_all_albums'] = array('Publish all given albums in the frontend');
$GLOBALS['TL_LANG']['tl_content']['gc_hierarchicalOutput'] = array('Hierarchically Frontend-Album-Output', 'Hierarchically Frontend-Album-Output (Albums and Subalbums)');
$GLOBALS['TL_LANG']['tl_content']['gc_template'] = array('Gallery template', 'Select a personal gallery template.');
$GLOBALS['TL_LANG']['tl_content']['gc_activateThumbSlider'] = array('Activate Ajax-Thumb-Slider', 'Activate Ajax-Thumb-Slider on mouseover in the album listing?');
$GLOBALS['TL_LANG']['tl_content']['gc_redirectSingleAlb'] = array('Redirection in case of a single album', 'Should be automatically redirected to the detail-view, in case of single-album-choice?');
$GLOBALS['TL_LANG']['tl_content']['gc_AlbumsPerPage'] = array('Items per page in the albumlisting', 'The number of items per page in the albumlisting. Set to 0 to disable pagination.');
$GLOBALS['TL_LANG']['tl_content']['gc_ThumbsPerPage'] = array('Thumbs per page in the detailview', 'The number of thumbnails per page in the detailview. Set to 0 to disable pagination.');
$GLOBALS['TL_LANG']['tl_content']['gc_PaginationNumberOfLinks'] = array('Number of links in the pagination navigation', 'Set the number of links in the pagination navigation. Default to 7.');
$GLOBALS['TL_LANG']['tl_content']['gc_sorting'] = array('Album sorting', 'According to which field the albums should be sorted?');
$GLOBALS['TL_LANG']['tl_content']['gc_sorting_direction'] = array('Sort sequence', 'DESC: descending, ASC: ascending');
$GLOBALS['TL_LANG']['tl_content']['gc_picture_sorting'] = array('Picture sorting', 'According to which field the pictures in a single album should be sorted?');
$GLOBALS['TL_LANG']['tl_content']['gc_picture_sorting_direction'] = array('Sort sequence', 'DESC: descending, ASC: ascending');
$GLOBALS['TL_LANG']['tl_content']['gc_size_detailview'] = array('Detailview: Thumbnail width and height', 'Here you can set the image dimensions and the resize mode.');
$GLOBALS['TL_LANG']['tl_content']['gc_imagemargin_detailview'] = array('Image margin detailview', 'Here you can enter the top, right, bottom and left margin.');
$GLOBALS['TL_LANG']['tl_content']['gc_size_albumlisting'] = array('Albumlist: Thumbnail width and height', 'Here you can set the image dimensions and the resize mode.');
$GLOBALS['TL_LANG']['tl_content']['gc_imagemargin_albumlisting'] = array('Image margin albumlisting', 'Here you can enter the top, right, bottom and left margin.');
$GLOBALS['TL_LANG']['tl_content']['gc_fullsize'] = array('Full-size view/new window', 'Open the full-size image in a lightbox or the link in a new browser window.');

// References
$GLOBALS['TL_LANG']['tl_content']['gc_sortingDirection']['DESC'] = "Ascending";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingDirection']['ASC'] = "Descending";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingField']['sorting'] = "Backend-module sorting (sorting)";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingField']['id'] = "ID";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingField']['date'] = "Date of creation (date)";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingField']['name'] = "Name (name)";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingField']['owner'] = "Owner (owner)";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingField']['comment'] = "Comment/Caption (comment)";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingField']['title'] = "Image-title (title)";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingField']['tstamp'] = "Revision date (tstamp)";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingField']['alias'] = "Albumalias (alias)";
$GLOBALS['TL_LANG']['tl_content']['gc_sortingField']['visitors'] = "Number of visitors (visitors)";
