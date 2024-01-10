<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

use Markocupic\GalleryCreatorBundle\DataContainer\Content;
use Contao\System;
use Contao\Controller;

/**
 * Add palettes to tl_content
 */
$GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] = [
    Content::class,
    'onloadCbSetUpPalettes',
];

$GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator_ce'] = '
{type_legend},type,headline;
{miscellaneous_legend},gc_hierarchicalOutput,gc_publish_all_albums,gc_publish_albums,gc_redirectSingleAlb;
{album_listing_legend},gc_sorting,gc_sorting_direction,gc_size_albumlisting,gc_imagemargin_albumlisting;
{picture_listing_legend},gc_rows,gc_fullsize,gc_picture_sorting,gc_picture_sorting_direction,gc_size_detailview,gc_imagemargin_detailview;
{pagination_legend},gc_AlbumsPerPage,gc_ThumbsPerPage,gc_PaginationNumberOfLinks;
{template_legend:hide},gc_template;
{protected_legend:hide},protected;
{expert_legend:hide},align,space,cssID
';

$GLOBALS['TL_DCA']['tl_content']['palettes']['gallery_creator_ce_news'] = '
{type_legend},type,headline;
{album_listing_legend},gc_publish_single_album;
{picture_listing_legend},gc_rows,gc_fullsize,gc_picture_sorting,gc_picture_sorting_direction,gc_size_detailview,gc_imagemargin_detailview;
{pagination_legend},gc_ThumbsPerPage,gc_PaginationNumberOfLinks;
{template_legend:hide},gc_template;
{protected_legend:hide},protected;
{expert_legend:hide},align,space,cssID
';

// Miscellaneous legend
$GLOBALS['TL_DCA']['tl_content']['fields']['gc_hierarchicalOutput'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_publish_albums'] = [
    'inputType'            => 'checkbox',
    'exclude'              => true,
    'input_field_callback' => [Content::class, 'inputFieldCallbackListAlbums'],
    'eval'                 => ['multiple' => true, 'mandatory' => false, 'tl_class' => 'clr'],
    'sql'                  => "blob NULL",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_publish_single_album'] = [
    'inputType'        => 'radio',
    'exclude'          => true,
    'options_callback' => [Content::class, 'optionsCallbackListAlbums'],
    'eval'             => ['mandatory' => false, 'multiple' => false, 'tl_class' => 'clr'],
    'sql'              => "blob NULL",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_publish_all_albums'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'clr', 'submitOnChange' => true],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_redirectSingleAlb'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default ''",
];

// Album listing legend
$GLOBALS['TL_DCA']['tl_content']['fields']['gc_sorting'] = [
    'exclude'   => true,
    'options'   => explode(',', 'date,sorting,id,tstamp,name,alias,comment,visitors'),
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['gc_sortingField'],
    'inputType' => 'select',
    'eval'      => ['tl_class' => 'w50', 'submitOnChange' => true],
    'sql'       => "varchar(64) NOT NULL default 'date'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_sorting_direction'] = [
    'exclude'   => true,
    'options'   => explode(',', 'DESC,ASC'),
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['gc_sortingDirection'],
    'inputType' => 'select',
    'eval'      => ['tl_class' => 'w50', 'submitOnChange' => true],
    'sql'       => "varchar(64) NOT NULL default 'DESC'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_size_albumlisting'] = [
    'exclude'   => true,
    'inputType' => 'imageSize',
    'options'   => System::getContainer()->get('contao.image.sizes')->getAllOptions(),
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'eval'      => ['rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_imagemargin_albumlisting'] = [
    'exclude'   => true,
    'inputType' => 'trbl',
    'options'   => $GLOBALS['TL_CSS_UNITS'],
    'eval'      => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql'       => "varchar(128) NOT NULL default ''",
];

// Picture sorting legend
$GLOBALS['TL_DCA']['tl_content']['fields']['gc_rows'] = [
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => range(0, 30),
    'eval'      => ['tl_class' => 'w50 clr'],
    'sql'       => "smallint(5) unsigned NOT NULL default '4'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_fullsize'] = [
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'clr'],
    'sql'       => "char(1) NOT NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_picture_sorting'] = [
    'exclude'   => true,
    'options'   => explode(',', 'sorting,id,date,name,owner,comment,title'),
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['gc_sortingField'],
    'inputType' => 'select',
    'eval'      => ['tl_class' => 'w50', 'submitOnChange' => false],
    'sql'       => "varchar(64) NOT NULL default 'date'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_picture_sorting_direction'] = [
    'exclude'   => true,
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['gc_sortingDirection'],
    'options'   => explode(',', 'DESC,ASC'),
    'inputType' => 'select',
    'eval'      => ['tl_class' => 'w50', 'submitOnChange' => false],
    'sql'       => "varchar(64) NOT NULL default 'DESC'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_size_detailview'] = [
    'exclude'   => true,
    'inputType' => 'imageSize',
    'options'   => System::getContainer()->get('contao.image.sizes')->getAllOptions(),
    'reference' => &$GLOBALS['TL_LANG']['MSC'],
    'eval'      => ['rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_imagemargin_detailview'] = [
    'exclude'   => true,
    'inputType' => 'trbl',
    'options'   => $GLOBALS['TL_CSS_UNITS'],
    'eval'      => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql'       => "varchar(128) NOT NULL default ''",
];

// Pagination legend
$GLOBALS['TL_DCA']['tl_content']['fields']['gc_AlbumsPerPage'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql'       => "smallint(5) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_ThumbsPerPage'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql'       => "smallint(5) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_PaginationNumberOfLinks'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql'       => "smallint(5) unsigned NOT NULL default '7'",
];

// Template legend
$GLOBALS['TL_DCA']['tl_content']['fields']['gc_template'] = [
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => Controller::getTemplateGroup('ce_gc_'),
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gc_AlbumsPerPage'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql'       => "smallint(5) unsigned NOT NULL default '0'",
];
