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
use Markocupic\GalleryCreatorBundle\Dca\TlGalleryCreatorPictures;

$GLOBALS['TL_DCA']['tl_gallery_creator_pictures'] = [
    'config' => [
        'ptable' => 'tl_gallery_creator_albums',
        'notCopyable' => true,
        'notCreatable' => true,
        'enableVersioning' => true,
        'dataContainer' => 'Table',
        'onload_callback' => [
            [TlGalleryCreatorPictures::class, 'onloadCbCheckPermission'],
            [TlGalleryCreatorPictures::class, 'onloadCbSetUpPalettes'],
        ],
        'ondelete_callback' => [
            [TlGalleryCreatorPictures::class, 'ondeleteCb'],
        ],
        'oncut_callback' => [
            [TlGalleryCreatorPictures::class, 'oncutCb'],
        ],
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
            ],
        ],
    ],
    'edit' => [
        'buttons_callback' => [TlGalleryCreatorPictures::class, 'editButtonsCallback'],
    ],
    'list' => [
        'sorting' => [
            'child_record_callback' => [TlGalleryCreatorPictures::class, 'childRecordCb'],
            'fields' => ['sorting'],
            'headerFields' => ['id', 'date', 'ownersName', 'name', 'caption', 'thumb'],
            'mode' => 4,
            'panelLayout' => 'filter;search,limit',
        ],
        'global_operations' => [
            'fileupload' => [
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
                'class' => 'icon_image_add',
                'href' => 'act=edit&table=tl_gallery_creator_albums&mode=fileupload',
            ],
            'all' => [
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
                'class' => 'header_edit_all',
                'href' => 'act=select',
            ],
        ],
        'operations' => [
            'edit' => [
                'button_callback' => [TlGalleryCreatorPictures::class, 'buttonCbEditImage'],
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
            'delete' => [
                'attributes' => 'onclick="if (!confirm(\''.$GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmPicture'].'\')) return false; Backend.getScrollOffset();"',
                'button_callback' => [TlGalleryCreatorPictures::class, 'buttonCbDeletePicture'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
            ],
            'cut' => [
                'attributes' => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => [TlGalleryCreatorPictures::class, 'buttonCbCutImage'],
                'href' => 'act=paste&mode=cut',
                'icon' => 'cut.gif',
            ],
            'imagerotate' => [
                'attributes' => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => [TlGalleryCreatorPictures::class, 'buttonCbRotateImage'],
                'href' => 'mode=imagerotate',
                'icon' => 'bundles/markocupicgallerycreator/images/arrow_rotate_clockwise.png',
            ],
            'toggle' => [
                'attributes' => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => [TlGalleryCreatorPictures::class, 'toggleIcon'],
                'icon' => 'visible.gif',
            ],
        ],
    ],
    'palettes' => [
        '__selector__' => ['addCustomThumb'],
        'default' => 'published,picture,owner,date,imageInfo,addCustomThumb,title,caption;{media_integration:hide},socialMediaSRC,localMediaSRC;{expert_legend:hide},cssID',
        'restrictedUser' => 'imageInfo,picture',
    ],
    'subpalettes' => [
        'addCustomThumb' => 'customThumb',
    ],
    'fields' => [
        'id' => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'pid' => [
            'eval' => ['doNotShow' => true],
            'foreignKey' => 'tl_gallery_creator_albums.alias',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'path' => [
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'uuid' => [
            'sql' => 'binary(16) NULL',
        ],
        'sorting' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'published' => [
            'eval' => ['isBoolean' => true, 'submitOnChange' => true, 'tl_class' => 'long'],
            'filter' => true,
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default '1'",
        ],
        'imageInfo' => [
            'eval' => ['tl_class' => 'clr'],
            'input_field_callback' => [TlGalleryCreatorPictures::class, 'inputFieldCbGenerateImageInformation'],
        ],
        'title' => [
            'eval' => ['allowHtml' => false, 'decodeEntities' => true, 'rgxp' => 'alnum'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'text',
            'search' => true,
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'externalFile' => [
            'sql' => "char(1) NOT NULL default ''",
        ],
        'caption' => [
            'cols' => 20,
            'eval' => ['decodeEntities' => true, 'tl_class' => 'clr'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'textarea',
            'rows' => 6,
            'search' => true,
            'sql' => 'text NULL',
        ],
        'picture' => [
            'eval' => ['tl_class' => 'clr'],
            'input_field_callback' => [TlGalleryCreatorPictures::class, 'inputFieldCbGenerateImage'],
        ],
        'date' => [
            'inputType' => 'text',
            // when upload a new image, the image inherits the date of the parent album
            'default' => time(),
            'filter' => true,
            'search' => true,
            'eval' => ['mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'clr wizard ', 'submitOnChange' => false],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'addCustomThumb' => [
            'eval' => ['submitOnChange' => true],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default ''",
        ],
        'customThumb' => [
            'eval' => ['fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'extensions' => 'jpeg,jpg,gif,png,bmp,tiff'],
            'exclude' => true,
            'inputType' => 'fileTree',
            'sql' => 'blob NULL',
        ],
        'owner' => [
            'default' => BackendUser::getInstance()->id,
            'eval' => ['includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'clr w50'],
            'filter' => true,
            'foreignKey' => 'tl_user.name',
            'inputType' => 'select',
            'relation' => ['type' => 'hasOne', 'load' => 'eager'],
            'search' => true,
            'sql' => "int(10) NOT NULL default '0'",
        ],
        'socialMediaSRC' => [
            'eval' => ['tl_class' => 'clr'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'text',
            'search' => true,
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'localMediaSRC' => [
            'eval' => ['files' => true, 'filesOnly' => true, 'fieldType' => 'radio'],
            'exclude' => true,
            'filter' => true,
            'inputType' => 'fileTree',
            'search' => true,
            'sql' => 'binary(16) NULL',
        ],
        'cssID' => [
            'eval' => ['multiple' => true, 'size' => 2, 'tl_class' => 'w50 clr'],
            'exclude' => true,
            'inputType' => 'text',
            'sql' => "varchar(255) NOT NULL default ''",
        ],
    ],
];
