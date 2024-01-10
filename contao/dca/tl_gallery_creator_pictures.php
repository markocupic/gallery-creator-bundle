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

use Markocupic\GalleryCreatorBundle\DataContainer\GalleryCreatorPictures;
use Contao\BackendUser;

$GLOBALS['TL_DCA']['tl_gallery_creator_pictures'] = [
    'config'      => [
        'ptable'            => 'tl_gallery_creator_albums',
        'notCopyable'       => true,
        'notCreatable'      => true,
        'enableVersioning'  => true,
        'dataContainer'     => 'Table',
        'onload_callback'   => [
            [
                GalleryCreatorPictures::class,
                'onloadCbCheckPermission',
            ],
            [
                GalleryCreatorPictures::class,
                'onloadCbSetUpPalettes',
            ],
        ],
        'ondelete_callback' => [
            [
                GalleryCreatorPictures::class,
                'ondeleteCb',
            ],
        ],
        'oncut_callback'    => [
            [
                GalleryCreatorPictures::class,
                'oncutCb',
            ],
        ],
        'sql'               => [
            'keys' => [
                'id'  => 'primary',
                'pid' => 'index',
            ],
        ],
    ],
    'list'        => [
        'sorting'           => [
            'mode'                  => 4,
            'fields'                => ['sorting'],
            'panelLayout'           => 'filter;search,limit',
            'headerFields'          => ['id', 'date', 'owners_name', 'name', 'comment', 'thumb'],
            'child_record_callback' => [GalleryCreatorPictures::class, 'childRecordCb'],
        ],
        'global_operations' => [
            'fileupload' => [
                'href'       => 'act=edit&table=tl_gallery_creator_albums&mode=fileupload',
                'icon'       => 'bundles/markocupicgallerycreator/images/image_add.png',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
            ],
            'all'        => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
            ],
        ],
        'operations'        => [
            'edit'        => [
                'href'            => 'act=edit',
                'icon'            => 'edit.svg',
                'button_callback' => [GalleryCreatorPictures::class, 'buttonCbEditImage'],
            ],
            'delete'      => [
                'href'            => 'act=delete',
                'icon'            => 'delete.svg',
                'attributes'      => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmPicture'] ?? '').'\'))return false;Backend.getScrollOffset()"',
                'button_callback' => [GalleryCreatorPictures::class, 'buttonCbDeletePicture'],
            ],
            'cut'         => [
                'href'            => 'act=paste&mode=cut',
                'icon'            => 'cut.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => [GalleryCreatorPictures::class, 'buttonCbCutImage'],
            ],
            'imagerotate' => [
                'href'            => 'mode=imagerotate',
                'icon'            => 'bundles/markocupicgallerycreator/images/arrow_rotate_clockwise.png',
                'attributes'      => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => [GalleryCreatorPictures::class, 'buttonCbRotateImage'],
            ],
            'toggle'      => [
                'icon'            => 'visible.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => [GalleryCreatorPictures::class, 'toggleIcon'],
            ],
        ],
    ],
    'palettes'    => [
        '__selector__'    => ['addCustomThumb'],
        'default'         => 'published,picture,owner,date,image_info,addCustomThumb,title,comment;{media_integration:hide},socialMediaSRC,localMediaSRC;{expert_legend:hide},cssID',
        'restricted_user' => 'image_info,picture',
    ],
    'subpalettes' => ['addCustomThumb' => 'customThumb'],
    'fields'      => [

        'id'             => ['sql' => "int(10) unsigned NOT NULL auto_increment"],
        'pid'            => [
            'label'      => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['pid'],
            'foreignKey' => 'tl_gallery_creator_albums.alias',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
            'eval'       => ['doNotShow' => true],
        ],
        'path'           => ['sql' => "varchar(255) NOT NULL default ''"],
        'uuid'           => [
            'sql' => "binary(16) NULL",
        ],
        'sorting'        => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp'         => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'published'      => [
            'inputType' => 'checkbox',
            'filter'    => true,
            'eval'      => ['isBoolean' => true, 'submitOnChange' => true, 'tl_class' => 'long'],
            'sql'       => "char(1) NOT NULL default '1'",
        ],
        'image_info'     => [
            'input_field_callback' => [GalleryCreatorPictures::class, 'inputFieldCbGenerateImageInformation'],
            'eval'                 => ['tl_class' => 'clr',],
        ],
        'title'          => [
            'exclude'   => true,
            'inputType' => 'text',
            'filter'    => true,
            'search'    => true,
            'eval'      => ['allowHtml' => false, 'decodeEntities' => true, 'rgxp' => 'alnum'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        //activate subpalette
        'externalFile'   => ['sql' => "char(1) NOT NULL default ''"],
        'comment'        => [
            'inputType' => 'textarea',
            'exclude'   => true,
            'filter'    => true,
            'search'    => true,
            'cols'      => 20,
            'rows'      => 6,
            'eval'      => ['decodeEntities' => true, 'tl_class' => 'clr'],
            'sql'       => "text NULL",
        ],
        'picture'        => [
            'input_field_callback' => [GalleryCreatorPictures::class, 'inputFieldCbGenerateImage'],
            'eval'                 => ['tl_class' => 'clr'],
        ],
        'date'           => [
            'inputType' => 'text',
            // new uploaded image inherits the date of the parent album
            'default'   => time(),
            'filter'    => true,
            'search'    => true,
            'eval'      => ['mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'clr wizard ', 'submitOnChange' => false],
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'addCustomThumb' => [
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true,],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'customThumb'    => [
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => ['fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'extensions' => 'jpeg,jpg,gif,png,bmp,tiff'],
            'sql'       => "blob NULL",
        ],
        'owner'          => [
            'default'    => BackendUser::getInstance()->id,
            'foreignKey' => 'tl_user.name',
            'inputType'  => 'select',
            'filter'     => true,
            'search'     => true,
            'eval'       => ['includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'clr w50'],
            'sql'        => "int(10) NOT NULL default '0'",
            'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
        ],
        'socialMediaSRC' => [
            'exclude'   => true,
            'filter'    => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => ['tl_class' => 'clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'localMediaSRC'  => [
            'exclude'   => true,
            'filter'    => true,
            'search'    => true,
            'inputType' => 'fileTree',
            'eval'      => ['files' => true, 'filesOnly' => true, 'fieldType' => 'radio'],
            'sql'       => "binary(16) NULL",
        ],
        'cssID'          => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['multiple' => true, 'size' => 2, 'tl_class' => 'w50 clr'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
    ],
];
