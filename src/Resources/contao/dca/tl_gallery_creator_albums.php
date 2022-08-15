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

use Contao\BackendUser;
use Contao\Config;
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
            'panelLayout' => 'filter;sorting,search,limit',
        ],
        'label'             => [
            'fields' => ['name'],
            'format' => '#icon# %s [#datum#] [#count_pics# images]',
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
                'attributes'          => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"',
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
            ],
            'toggle'                     => [
                'attributes'           => 'onclick="Backend.getScrollOffset();"',
                'haste_ajax_operation' => [
                    'field'                     => 'published',
                    'options'                   => [
                        ['value' => '', 'icon' => 'invisible.svg'],
                        ['value' => '1', 'icon' => 'visible.svg'],
                    ],
                    'check_permission_callback' => [GalleryCreatorAlbums::class, 'checkPermissionCallbackToggle'],
                ],
            ],
            'uploadImages'               => [
                'attributes' => 'data-icon="gc-op-icon"',
                'href'       => 'act=edit&key=fileUpload',
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
        '__selector__'               => ['protected', 'includeChmod'],
        'default'                    => '{title_legend},name,alias;{meta_legend},pageTitle,robots,description,serpPreview;{details_legend},date,location,teaser,photographer,visitors;{caption_legend},captionType,caption,markdownCaption;{album_preview_thumb_legend},sortBy,filePrefix,thumb;{gallery_creator_chmod_legend:hide},includeChmod;{insert_article_legend},insertArticlePre,insertArticlePost;{uploadDir_legend},assignedDir;{protection_legend:hide},protected',
        'fileUpload'                 => '{upload_settings_legend},preserveFilename,imageResolution;{uploader_legend},fileUpload',
        'importImagesFromFilesystem' => '{upload_settings_legend},preserveFilename,multiSRC',
        'reviseDatabase'             => '{maintenance},reviseDatabase',
    ],
    'subpalettes' => [
        'protected'    => 'groups',
        'includeChmod' => 'cuser,cgroup,chmod',
    ],
    'fields'      => [
        'id'                => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'pid'               => [
            'foreignKey' => 'tl_gallery_creator_albums.alias',
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp'            => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'sorting'           => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'published'         => [
            'filter'    => true,
            'eval'      => ['submitOnChange' => true, 'isBoolean' => true],
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default '1'",
        ],
        'name'              => [
            'filter'    => true,
            'search'    => true,
            'sorting'   => true,
            'eval'      => ['mandatory' => true, 'tl_class' => 'w50', 'submitOnChange' => false],
            'inputType' => 'text',
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'alias'             => [
            'filter'    => true,
            'search'    => true,
            'sorting'   => true,
            'eval'      => ['doNotCopy' => true, 'maxlength' => 50, 'tl_class' => 'w50', 'unique' => true],
            'inputType' => 'text',
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'pageTitle'         => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'robots'            => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'select',
            'options'   => ['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'],
            'eval'      => ['tl_class' => 'w50', 'includeBlankOption' => true],
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'description'       => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'],
            'sql'       => 'text NULL',
        ],
        'date'              => [
            'sorting'   => true,
            'default'   => time(),
            'eval'      => ['mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'w50 wizard', 'submitOnChange' => false],
            'inputType' => 'text',
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'location'          => [
            'filter'    => true,
            'search'    => true,
            'sorting'   => true,
            'eval'      => ['mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false],
            'exclude'   => true,
            'inputType' => 'text',
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'teaser'            => [
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => ['style' => 'height:60px', 'tl_class' => 'clr long', 'allowHtml' => false, 'wrap' => 'soft'],
            'sql'       => 'text NULL',
        ],
        'photographer'      => [
            'filter'    => true,
            'search'    => true,
            'sorting'   => true,
            'eval'      => ['mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false],
            'exclude'   => true,
            'inputType' => 'text',
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'assignedDir'       => [
            'eval'      => ['mandatory' => false, 'fieldType' => 'radio', 'tl_class' => 'clr'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'sql'       => 'blob NULL',
        ],
        'visitors'          => [
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50', 'rgxp' => 'digit'],
            'inputType' => 'text',
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'visitorsDetails'   => [
            'sql' => 'blob NULL',
        ],
        'captionType'       => [
            'filter'    => true,
            'eval'      => ['submitOnChange' => true, 'tl_class' => 'clr'],
            'inputType' => 'select',
            'options'   => ['text', 'markdown'],
            'sql'       => "varchar(64) NOT NULL default 'text'",
        ],
        'caption'           => [
            'cols'      => 20,
            'eval'      => ['style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr long', 'allowHtml' => false, 'wrap' => 'soft'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'rows'      => 5,
            'search'    => true,
            'sql'       => 'text NULL',
        ],
        'markdownCaption'   => [
            'search'      => true,
            'exclude'     => true,
            'inputType'   => 'textarea',
            'eval'        => ['mandatory' => false, 'style' => 'height:60px', 'preserveTags' => true, 'decodeEntities' => true, 'class' => 'monospace', 'rte' => 'ace', 'helpwizard' => true, 'tl_class' => 'clr'],
            'explanation' => 'insertTags',
            'sql'         => 'text NULL',
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
            'eval'      => ['mandatory' => false, 'rgxp' => 'alnum', 'nospace' => true, 'tl_class' => 'w50'],
            'exclude'   => true,
            'inputType' => 'text',
            'sql'       => "varchar(32) NOT NULL default ''",
        ],
        'thumb'             => [
            'eval'      => ['includeBlankOption' => true, 'nospace' => true, 'rgxp' => 'digit', 'maxlength' => 64, 'tl_class' => 'clr', 'submitOnChange' => true],
            'inputType' => 'radio',
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'includeChmod'      => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true, 'isBoolean' => true],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'cuser'             => [
            'filter'     => true,
            'sorting'    => true,
            'default'    => Config::get('gcDefaultUser') ?: BackendUser::getInstance()->id,
            'eval'       => ['chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
            'foreignKey' => 'tl_user.name',
            'inputType'  => 'select',
            'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
            'sql'        => 'int(10) unsigned NOT NULL default 0',
        ],
        'cgroup'            => [
            'default'    => (int)Config::get('gcDefaultGroup'),
            'search'     => true,
            'exclude'    => true,
            'inputType'  => 'select',
            'foreignKey' => 'tl_user_group.name',
            'eval'       => ['mandatory' => true, 'chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
            'sql'        => 'int(10) unsigned NOT NULL default 0',
            'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
        ],
        'chmod'             => [
            'default'   => Config::get('gcDefaultChmod'),
            'exclude'   => true,
            'inputType' => 'gcAlbumChmod',
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'insertArticlePre'  => [
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'inputType' => 'text',
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'insertArticlePost' => [
            'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
            'inputType' => 'text',
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'protected'         => [
            'filter'    => true,
            'eval'      => ['submitOnChange' => true, 'isBoolean' => true, 'tl_class' => 'clr'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'groups'            => [
            'filter'     => true,
            'eval'       => ['mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'],
            'foreignKey' => 'tl_member_group.name',
            'inputType'  => 'checkbox',
            'sql'        => 'blob NULL',
        ],
        'fileUpload'        => [
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
            'eval'      => ['doNotShow' => true, 'isBoolean' => true, 'submitOnChange' => true],
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default '1'",
        ],
        'multiSRC'          => [
            'eval'      => ['multiple' => true, 'fieldType' => 'checkbox', 'files' => true, 'mandatory' => true, 'extensions' => System::getContainer()->getParameter('markocupic_gallery_creator.valid_extensions')],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'sql'       => 'blob NULL',
        ],
        'reviseDatabase'    => [
            'eval' => ['doNotShow' => true],
        ],
    ],
];
