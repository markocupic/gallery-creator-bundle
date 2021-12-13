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
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\GalleryCreatorController;
use Markocupic\GalleryCreatorBundle\Dca\TlContent;

/**
 * Add palettes to tl_content
 */
$GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] = array(
	TlContent::class,
	'onloadCbSetUpPalettes',
);

$GLOBALS['TL_DCA']['tl_content']['palettes'][GalleryCreatorController::TYPE] = 'name,type,headline;
{miscellaneous_legend},gcHierarchicalOutput,gcPublishAllAlbums,gcPublishAlbums,gcRedirectSingleAlb;
{pagination_legend},gcAlbumsPerPage,gcThumbsPerPage,gcPaginationNumberOfLinks;
{album_listing_legend},gcSorting,gcSortingDirection,gcSizeAlbumListing,gcImageMarginAlbumListing;
{picture_listing_legend},gcFullsize,gcPictureSorting,gcPictureSortingDirection,gcSizeDetailView,gcImageMarginDetailView;
{template_legend:hide},customTpl;
{protected_legend:hide},protected;
{expert_legend:hide},align,space,cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator_news'] = 'name,type,headline;
{album_listing_legend},gcPublishSingleAlbum;
{pagination_legend},gcThumbsPerPage,gcPaginationNumberOfLinks;
{picture_listing_legend},gcFullsize,gcPictureSorting,gcPictureSortingDirection,gcSizeDetailView,gcImageMarginDetailView;
{template_legend:hide},customTpl;
{protected_legend:hide},protected;
{expert_legend:hide},align,space,cssID';

/**
 * Add fields to tl_content
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['gcHierarchicalOutput'] = array(
	'eval'      => array('submitOnChange' => true, 'tl_class' => 'clr'),
	'exclude'   => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcSorting'] = array(
	'eval'      => array('tl_class' => 'w50', 'submitOnChange' => true),
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => array('date', 'sorting', 'id', 'tstamp', 'name', 'alias', 'comment', 'visitors'),
	'reference' => &$GLOBALS['TL_LANG']['tl_content']['gcSortingField'],
	'sql'       => "varchar(64) NOT NULL default 'date'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcSortingDirection'] = array(
	'eval'      => array('tl_class' => 'w50', 'submitOnChange' => true),
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => array('DESC', 'ASC'),
	'reference' => &$GLOBALS['TL_LANG']['tl_content']['gcSortingDirection'],
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcPictureSorting'] = array(
	'eval'      => array('tl_class' => 'w50', 'submitOnChange' => false),
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => array('sorting', 'id', 'date', 'name', 'owner', 'comment', 'title'),
	'reference' => &$GLOBALS['TL_LANG']['tl_content']['gcSortingField'],
	'sql'       => "varchar(64) NOT NULL default 'date'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcPictureSortingDirection'] = array(
	'eval'      => array('tl_class' => 'w50', 'submitOnChange' => false),
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => array('DESC', 'ASC'),
	'reference' => &$GLOBALS['TL_LANG']['tl_content']['gcSortingDirection'],
	'sql'       => "varchar(64) NOT NULL default 'DESC'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcRedirectSingleAlb'] = array(
	'eval'      => array('tl_class' => 'clr'),
	'exclude'   => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcAlbumsPerPage'] = array(
	'eval'      => array('rgxp' => 'digit', 'tl_class' => 'clr'),
	'exclude'   => true,
	'inputType' => 'text',
	'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcPaginationNumberOfLinks'] = array(
	'eval'      => array('rgxp' => 'digit', 'tl_class' => 'clr'),
	'exclude'   => true,
	'inputType' => 'text',
	'sql'       => "smallint(5) unsigned NOT NULL default '7'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcSizeDetailView'] = array(
	'eval'      => array('rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'),
	'exclude'   => true,
	'inputType' => 'imageSize',
	'options'   => System::getImageSizes(),
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcImageMarginDetailView'] = array(
	'eval'      => array('includeBlankOption' => true, 'tl_class' => 'w50'),
	'exclude'   => true,
	'inputType' => 'trbl',
	'options'   => $GLOBALS['TL_CSS_UNITS'],
	'sql'       => "varchar(128) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcSizeAlbumListing'] = array(
	'eval'      => array('rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'),
	'exclude'   => true,
	'inputType' => 'imageSize',
	'options'   => System::getImageSizes(),
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'sql'       => "varchar(64) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcImageMarginAlbumListing'] = array(
	'eval'      => array('includeBlankOption' => true, 'tl_class' => 'w50'),
	'exclude'   => true,
	'inputType' => 'trbl',
	'options'   => $GLOBALS['TL_CSS_UNITS'],
	'sql'       => "varchar(128) NOT NULL default ''",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcFullsize'] = array(
	'eval'      => array('tl_class' => 'clr'),
	'exclude'   => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default '1'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcThumbsPerPage'] = array(
	'eval'      => array('rgxp' => 'digit', 'tl_class' => 'clr'),
	'exclude'   => true,
	'inputType' => 'text',
	'sql'       => "smallint(5) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcPublishAlbums'] = array(
	'eval'                 => array('multiple' => true, 'mandatory' => false, 'tl_class' => 'clr'),
	'exclude'              => true,
	'inputType'            => 'checkbox',
	'input_field_callback' => array(TlContent::class, 'inputFieldCallbackListAlbums'),
	'sql'                  => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcPublishSingleAlbum'] = array(
	'eval'             => array('mandatory' => false, 'multiple' => false, 'tl_class' => 'clr'),
	'exclude'          => true,
	'inputType'        => 'radio',
	'options_callback' => array(TlContent::class, 'optionsCallbackListAlbums'),
	'sql'              => "blob NULL",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['gcPublishAllAlbums'] = array(
	'eval'      => array('tl_class' => 'clr', 'submitOnChange' => true),
	'exclude'   => true,
	'inputType' => 'checkbox',
	'sql'       => "char(1) NOT NULL default ''",
);
