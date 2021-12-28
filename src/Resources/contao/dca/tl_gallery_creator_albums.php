<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

use Contao\BackendUser;
use Contao\System;
use Markocupic\GalleryCreatorBundle\DataContainer\GalleryCreatorAlbums;

$GLOBALS['TL_DCA']['tl_gallery_creator_albums'] = [
    // Config
    'config'      => [
        'ctable'           => ['tl_gallery_creator_pictures'],
        'notCopyable'      => true,
        'enableVersioning' => true,
        'dataContainer'    => 'Table',
        'sql'              => [
            'keys' => [
                'id'    => 'primary',
                'pid'   => 'index',
                'alias' => 'index',
            ],
        ],
    ],
    'list'        => [
        'sorting'           => [
            'mode'        => 5,
            'panelLayout' => 'limit,sort',
        ],
        'label'             => [
            'fields' => ['name'],
            'format' => '<span><a href="#href#" title="#title#"><img width="18" height="18" data-icon="#icon#" src="bundles/markocupicgallerycreator/images/#icon#"></span> %s <span style="color:#b3b3b3; padding-left:3px;">[#datum#] [#count_pics# images]</span></a>',
        ],
        'global_operations' => [
            'all'            => [
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
                'class'      => 'header_edit_all',
                'href'       => 'act=select',
            ],
            'reviseDatabase' => [
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
                'class'      => 'gc-gop-icon gc-gop-revise-database',
                // Complete the href attribute in GalleryCreatorAlbums::onloadCbSetUpPalettes()
                'href'       => 'act=edit&table&key=reviseDatabase&id=%s',
                'icon'       => 'bundles/markocupicgallerycreator/images/revise_database.svg',
            ],
        ],
        'operations'        => [
            'editheader'                 => [
                'attributes' => 'data-icon="gc-op-icon"',
                'href'       => 'act=edit',
                'icon'       => 'header.svg',
            ],
            'edit'                       => [
                'attributes' => 'data-icon="gc-op-icon"',
                'href'       => 'table=tl_gallery_creator_pictures',
                'icon'       => 'bundles/markocupicgallerycreator/images/list.svg',
            ],
            'delete'                     => [
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['CONFIRM']['gcDeleteConfirmAlbum'].'\'))return false;Backend.getScrollOffset()"',
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
            ],
            'toggle'                     => [
                'attributes'           => 'onclick="Backend.getScrollOffset();"',
                'haste_ajax_operation' => [
                    'field'                     => 'published',
                    'options'                   => [
                        [
                            'value' => '',
                            'icon'  => 'invisible.svg',
                        ],
                        [
                            'value' => '1',
                            'icon'  => 'visible.svg',
                        ],
                    ],
                    'check_permission_callback' => [GalleryCreatorAlbums::class, 'checkPermissionCbToggle'],
                ],
            ],
            'uploadImages'               => [
                'attributes' => 'data-icon="gc-op-icon"',
                'href'       => 'id=%s&act=edit&table=tl_gallery_creator_albums&key=fileUpload',
                'icon'       => 'bundles/markocupicgallerycreator/images/add_image.svg',
            ],
            'importImagesFromFilesystem' => [
                'attributes' => 'data-icon="gc-op-icon"',
                'href'       => 'id=%s&act=edit&table=tl_gallery_creator_albums&key=importImagesFromFilesystem',
                'icon'       => 'bundles/markocupicgallerycreator/images/import_from_filesystem.svg',
            ],
            'cut'                        => [
                'attributes' => 'onclick="Backend.getScrollOffset();"',
                'href'       => 'act=paste&mode=cut',
                'icon'       => 'cut.svg',
            ],
            'show'                       => [
                'href' => 'act=show',
                'icon' => 'show.svg',
            ],
        ],
    ],
    'palettes'    => [
        '__selector__'               => ['protected'],
        'default'                    => '{album_inf_legend},published,name,alias,description,keywords,assignedDir,albumInfo,owner,photographer,date,eventLocation,filePrefix,sortBy,caption,visitors;{album_preview_thumb_legend},thumb;{insert_article},insertArticlePre,insertArticlePost;{protection_legend:hide},protected',
        'fileUpload'                 => '{upload_settings_legend},preserveFilename,imageResolution;{uploader_legend},fileUpload',
        'importImagesFromFilesystem' => '{upload_settings_legend},preserveFilename,multiSRC',
        'restrictedUser'             => '{album_inf_legend},link_edit_images,albumInfo',
        'reviseDatabase'             => '{maintenance},reviseDatabase',
    ],
    'subpalettes' => [
        'protected' => 'groups',
    ],
    'fields'      => [
        'id'                => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'pid'               => [
            'foreignKey' => 'tl_gallery_creator_albums.alias',
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'sorting'           => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp'            => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'published'         => [
            'eval'      => ['submitOnChange' => true],
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default '1'",
        ],
        'date'              => [
            'default'   => time(),
            'eval'      => ['mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'w50 wizard', 'submitOnChange' => false],
            'inputType' => 'text',
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'owner'             => [
            'default'    => BackendUser::getInstance()->id,
            'eval'       => ['chosen' => true, 'includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'w50'],
            'foreignKey' => 'tl_user.name',
            'inputType'  => 'select',
            'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'assignedDir'       => [
            'eval'      => ['mandatory' => false, 'fieldType' => 'radio', 'tl_class' => 'clr'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'sql'       => 'blob NULL',
        ],
        'ownersName'        => [
            'default' => BackendUser::getInstance()->name,
            'eval'    => ['doNotShow' => true, 'tl_class' => 'w50 gc-readonly'],
            'sql'     => 'text NULL',
        ],
        'photographer'      => [
            'eval'      => ['mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false],
            'exclude'   => true,
            'inputType' => 'text',
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'eventLocation'     => [
            'eval'      => ['mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false],
            'exclude'   => true,
            'inputType' => 'text',
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'name'              => [
            'eval'      => ['mandatory' => true, 'tl_class' => 'w50', 'submitOnChange' => false],
            'inputType' => 'text',
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'alias'             => [
            'eval'      => ['doNotShow' => false, 'doNotCopy' => true, 'maxlength' => 50, 'tl_class' => 'w50', 'unique' => true],
            'inputType' => 'text',
            'sql'       => "varchar(255) BINARY NOT NULL default ''",
        ],
        'description'       => [
            'eval'      => ['decodeEntities' => true, 'tl_class' => 'clr'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'search'    => true,
            'sql'       => 'text NULL',
        ],
        'keywords'          => [
            'eval'      => ['decodeEntities' => true, 'tl_class' => 'clr'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'search'    => true,
            'sql'       => 'text NULL',
        ],
        'caption'           => [
            'eval'      => ['tl_class' => 'clr long', 'allowHtml' => false, 'wrap' => 'soft'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'search'    => true,
            'sql'       => 'text NULL',
        ],
        'thumb'             => [
            'eval'      => ['doNotShow' => true, 'includeBlankOption' => true, 'nospace' => true, 'rgxp' => 'digit', 'maxlength' => 64, 'tl_class' => 'clr', 'submitOnChange' => true],
            'inputType' => 'radio',
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'fileUpload'        => [
            'eval' => ['doNotShow' => true],
        ],
        'albumInfo'         => [
            'eval' => ['doNotShow' => true],
        ],
        'imageResolution'   => [
            'eval'      => ['doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true],
            'inputType' => 'select',
            'options'   => array_merge(['no_scaling'], range(100, 9000, 50)),
            'reference' => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums'],
            'sql'       => "smallint(5) unsigned NOT NULL default '1600'",
        ],
        'preserveFilename'  => [
            'eval'      => ['doNotShow' => true, 'submitOnChange' => true],
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default '1'",
        ],
        'multiSRC'          => [
            'eval'      => ['doNotShow' => true, 'multiple' => true, 'fieldType' => 'checkbox', 'files' => true, 'mandatory' => true, 'extensions' => System::getContainer()->getParameter('markocupic_gallery_creator.valid_extensions')],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'sql'       => 'blob NULL',
        ],
        'protected'         => [
            'eval'      => ['doNotShow' => true, 'submitOnChange' => true, 'tl_class' => 'clr'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'groups'            => [
            'eval'       => ['doNotShow' => true, 'mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'],
            'foreignKey' => 'tl_member_group.name',
            'inputType'  => 'checkbox',
            'sql'        => 'blob NULL',
        ],
        'insertArticlePre'  => [
            'eval'      => ['doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50'],
            'inputType' => 'text',
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'insertArticlePost' => [
            'eval'      => ['doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50'],
            'inputType' => 'text',
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'reviseDatabase'    => [
            'eval' => ['doNotShow' => true],
        ],
        'visitorsDetails'   => [
            'inputType' => 'textarea',
            'sql'       => 'blob NULL',
        ],
        'visitors'          => [
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50', 'rgxp' => 'digit'],
            'inputType' => 'text',
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'sortBy'            => [
            'eval'      => ['chosen' => true, 'submitOnChange' => true, 'tl_class' => 'w50'],
            'exclude'   => true,
            'inputType' => 'select',
            'options'   => ['----', 'name_asc', 'name_desc', 'date_asc', 'date_desc'],
            'reference' => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums'],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'filePrefix'        => [
            'eval'      => ['mandatory' => false, 'tl_class' => 'clr', 'rgxp' => 'alnum', 'nospace' => true],
            'exclude'   => true,
            'inputType' => 'text',
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
    ],
];
