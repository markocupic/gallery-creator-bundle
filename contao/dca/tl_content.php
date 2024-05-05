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

use Contao\BackendUser;
use Contao\System;
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\GalleryCreatorController;
use Markocupic\GalleryCreatorBundle\Controller\ContentElement\GalleryCreatorNewsController;

// Selectors
$GLOBALS['TL_DCA']['tl_content']['palettes']['__selector__'][] = 'gcShowAlbumSelection';

// Palettes
$GLOBALS['TL_DCA']['tl_content']['palettes'][GalleryCreatorController::TYPE] = 'name,type,headline;
{miscellaneous_legend},gcAddBreadcrumb;
{album_selection_legend},gcShowAlbumSelection;
{pagination_legend},gcAlbumsPerPage,gcThumbsPerPage,gcRootAlbum;
{album_listing_legend},gcSorting,gcSortingDirection,gcSizeAlbumListing;
{picture_listing_legend},gcFullSize,gcPictureSorting,gcPictureSortingDirection,gcSizeDetailView;
{template_legend:hide},customTpl;
{protected_legend:hide},protected;
{expert_legend:hide},align,space,cssID';

$GLOBALS['TL_DCA']['tl_content']['palettes'][GalleryCreatorNewsController::TYPE] = 'name,type,headline;
{album_listing_legend},gcPublishSingleAlbum;
{pagination_legend},gcThumbsPerPage;
{picture_listing_legend},gcFullSize,gcPictureSorting,gcPictureSortingDirection,gcSizeDetailView;
{template_legend:hide},customTpl;
{protected_legend:hide},protected;
{expert_legend:hide},align,space,cssID';

// Subpalettes
$GLOBALS['TL_DCA']['tl_content']['subpalettes']['gcShowAlbumSelection'] = 'gcAlbumSelection';

$GLOBALS['TL_DCA']['tl_content']['fields']['gcSorting'] = [
    'eval'      => ['tl_class' => 'w50', 'submitOnChange' => true],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => ['date', 'sorting', 'id', 'tstamp', 'name', 'alias', 'caption', 'visitors'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['gcSortingField'],
    'sql'       => "varchar(64) NOT NULL default 'date'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcSortingDirection'] = [
    'eval'      => ['tl_class' => 'w50', 'submitOnChange' => true],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => ['DESC', 'ASC'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['gcSortingDirection'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcPictureSorting'] = [
    'eval'      => ['tl_class' => 'w50', 'submitOnChange' => false],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => ['sorting', 'id', 'date', 'name', 'cuser', 'caption', 'title'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['gcSortingField'],
    'sql'       => "varchar(64) NOT NULL default 'date'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcPictureSortingDirection'] = [
    'eval'      => ['tl_class' => 'w50', 'submitOnChange' => false],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => ['DESC', 'ASC'],
    'reference' => &$GLOBALS['TL_LANG']['tl_content']['gcSortingDirection'],
    'sql'       => "varchar(64) NOT NULL default 'DESC'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcAlbumsPerPage'] = [
    'eval'      => ['rgxp' => 'digit', 'tl_class' => 'clr'],
    'exclude'   => true,
    'inputType' => 'text',
    'sql'       => "smallint(5) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcSizeAlbumListing'] = [
    'eval'             => ['rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'],
    'exclude'          => true,
    'inputType'        => 'imageSize',
    'options_callback' => static fn() => System::getContainer()->get('contao.image.sizes')->getOptionsForUser(BackendUser::getInstance()),
    'reference'        => &$GLOBALS['TL_LANG']['MSC'],
    'sql'              => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcSizeDetailView'] = [
    'eval'             => ['rgxp' => 'natural', 'includeBlankOption' => true, 'nospace' => true, 'helpwizard' => true, 'tl_class' => 'w50'],
    'exclude'          => true,
    'inputType'        => 'imageSize',
    'options_callback' => static fn() => System::getContainer()->get('contao.image.sizes')->getOptionsForUser(BackendUser::getInstance()),
    'reference'        => &$GLOBALS['TL_LANG']['MSC'],
    'sql'              => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcFullSize'] = [
    'eval'      => ['isBoolean' => true, 'tl_class' => 'clr'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcThumbsPerPage'] = [
    'eval'      => ['rgxp' => 'digit', 'tl_class' => 'clr'],
    'exclude'   => true,
    'inputType' => 'text',
    'sql'       => "smallint(5) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcShowAlbumSelection'] = [
    'eval'      => ['submitOnChange' => true, 'isBoolean' => true, 'tl_class' => 'clr'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcAlbumSelection'] = [
    'eval'      => ['mandatory' => true, 'multiple' => true, 'tl_class' => 'w50 clr'],
    'exclude'   => true,
    'inputType' => 'gcAlbumTree',
    'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcPublishSingleAlbum'] = [
    'eval'      => ['mandatory' => false, 'tl_class' => 'clr'],
    'exclude'   => true,
    'inputType' => 'radio',
    'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_content']['fields']['gcAddBreadcrumb'] = [
    'eval'      => ['isBoolean' => true, 'tl_class' => 'clr'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'sql'       => "char(1) NOT NULL default ''",
];
