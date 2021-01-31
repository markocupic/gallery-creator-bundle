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

use Contao\System;
use Markocupic\GalleryCreatorBundle\Dca\TlContent;

/**
 * Add palettes to tl_content
 */
$GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] = array(
	TlContent::class,
	'onloadCbSetUpPalettes',
);

$GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator_ce'] = 'name,type,headline;
{miscellaneous_legend},gc_hierarchicalOutput,gc_publish_all_albums,gc_publish_albums,gc_redirectSingleAlb;
{pagination_legend},gc_AlbumsPerPage,gc_ThumbsPerPage,gc_PaginationNumberOfLinks;
{album_listing_legend},gc_sorting,gc_sorting_direction,gc_size_albumlisting,gc_imagemargin_albumlisting;
{picture_listing_legend},gc_rows,gc_fullsize,gc_picture_sorting,gc_picture_sorting_direction,gc_size_detailview,gc_imagemargin_detailview;
{template_legend:hide},gc_template;
{protected_legend:hide},protected;
{expert_legend:hide},align,space,cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator_ce_news'] = 'name,type,headline;
{album_listing_legend},gc_publish_single_album;
{pagination_legend},gc_ThumbsPerPage,gc_PaginationNumberOfLinks;
{picture_listing_legend},gc_rows,gc_fullsize,gc_picture_sorting,gc_picture_sorting_direction,gc_size_detailview,gc_imagemargin_detailview;
{template_legend:hide},gc_template;
{protected_legend:hide},protected;
{expert_legend:hide},align,space,cssID';

/**
 * Add fields to tl_content
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['gc_rows'] = array(
	'exclude'   => true,
	'default'   => '4',
	'inputType' => 'select',
	'options'   => range(0, 30),
	'eval'      => array('tl_class' => 'clr'),
	'sql'       => "smallint(5) unsigned NOT NULL default '4'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_template'] = array(
	'exclude'          => true,
	'inputType'        => 'select',
	'options_callback' => array(TlContent::class, 'getGalleryCreatorTemplates'),
	'eval'             => array('tl_class' => 'clr'),
	'sql'              => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_hierarchicalOutput'] = array(
	'exclude'   => true,
	'default'   => false,
	'inputType' => 'checkbox',
	'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_sorting'] = array(
	'exclude'   => true,
	'options'   => explode(',', 'date,sorting,id,tstamp,name,alias,comment,visitors'),
	'reference' => &$GLOBALS['TL_LANG']['tl_content']['gc_sortingField'],
	'default'   => 'date',
	'inputType' => 'select',
	'eval'      => array('tl_class' => 'w50', 'submitOnChange' => true),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_sorting_direction'] = array(
	'exclude'   => true,
	'options'   => explode(',', 'DESC,ASC'),
	'reference' => &$GLOBALS['TL_LANG']['tl_content']['gc_sortingDirection'],
	'default'   => 'DESC',
	'inputType' => 'select',
	'eval'      => array('tl_class' => 'w50', 'submitOnChange' => true),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_picture_sorting'] = array(
	'exclude'   => true,
	'options'   => explode(',', 'sorting,id,date,name,owner,comment,title'),
	'reference' => &$GLOBALS['TL_LANG']['tl_content']['gc_sortingField'],
	'default'   => 'date',
	'inputType' => 'select',
	'eval'      => array('tl_class' => 'w50', 'submitOnChange' => false),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_picture_sorting_direction'] = array(
	'exclude'   => true,
	'reference' => &$GLOBALS['TL_LANG']['tl_content']['gc_sortingDirection'],
	'options'   => explode(',', 'DESC,ASC'),
	'default'   => 'DESC',
	'inputType' => 'select',
	'eval'      => array('tl_class' => 'w50', 'submitOnChange' => false),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_redirectSingleAlb'] = array(
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_AlbumsPerPage'] = array(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'digit', 'tl_class' => 'clr'),
	'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_PaginationNumberOfLinks'] = array(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'digit', 'tl_class' => 'clr'),
	'sql'       => "smallint(5) unsigned NOT NULL default '7'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_size_detailview'] = array(
	'exclude'   => true,
	'inputType' => 'imageSize',
	'options'   => System::getImageSizes(),
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'eval'      => array('rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_imagemargin_detailview'] = array(
	'exclude'   => true,
	'inputType' => 'trbl',
	'options'   => $GLOBALS['TL_CSS_UNITS'],
	'eval'      => array('includeBlankOption' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(128) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_size_albumlisting'] = array(
	'exclude'   => true,
	'inputType' => 'imageSize',
	'options'   => System::getImageSizes(),
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'eval'      => array('rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_imagemargin_albumlisting'] = array(
	'exclude'   => true,
	'inputType' => 'trbl',
	'options'   => $GLOBALS['TL_CSS_UNITS'],
	'eval'      => array('includeBlankOption' => true, 'tl_class' => 'w50'),
	'sql'       => "varchar(128) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_fullsize'] = array(
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('tl_class' => 'clr'),
	'sql'       => "char(1) NOT NULL default '1'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_ThumbsPerPage'] = array(
	'default'   => 0,
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'digit', 'tl_class' => 'clr'),
	'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_publish_albums'] = array(
	'inputType'            => 'checkbox',
	'exclude'              => true,
	'input_field_callback' => array(TlContent::class, 'inputFieldCallbackListAlbums'),
	'eval'                 => array('multiple' => true, 'mandatory' => false, 'tl_class' => 'clr'),
	'sql'                  => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_publish_single_album'] = array(
	'inputType'        => 'radio',
	'exclude'          => true,
	'options_callback' => array(TlContent::class, 'optionsCallbackListAlbums'),
	'eval'             => array('mandatory' => false, 'multiple' => false, 'tl_class' => 'clr'),
	'sql'              => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_publish_all_albums'] = array(
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('tl_class' => 'clr', 'submitOnChange' => true),
	'sql'       => "char(1) NOT NULL default ''",
);
