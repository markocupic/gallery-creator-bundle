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
use Markocupic\GalleryCreatorBundle\Dca\TlGalleryCreatorAlbums;

$GLOBALS['TL_DCA']['tl_gallery_creator_albums'] = [
    // Config
    'config' => [
        'ctable' => ['tl_gallery_creator_pictures'],
        'notCopyable' => true,
        'enableVersioning' => true,
        'dataContainer' => 'Table',
        'onload_callback' => [
            [TlGalleryCreatorAlbums::class, 'handleAjaxRequests'],
            [TlGalleryCreatorAlbums::class, 'onloadCbCheckFolderSettings'],
            [TlGalleryCreatorAlbums::class, 'onloadCbFileupload'],
            [TlGalleryCreatorAlbums::class, 'onloadCbImportFromFilesystem'],
            [TlGalleryCreatorAlbums::class, 'onloadCbSetUpPalettes'],
        ],
        'ondelete_callback' => [
            [TlGalleryCreatorAlbums::class, 'ondeleteCb'],
        ],
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pid' => 'index',
                'alias' => 'index',
            ],
        ],
    ],
    'edit' => [
        'buttons_callback' => [[TlGalleryCreatorAlbums::class, 'buttonsCallback']],
    ],
    'list' => [
        'sorting' => [
            'mode' => 5,
            'panelLayout' => 'limit,sort',
            'paste_button_callback' => [TlGalleryCreatorAlbums::class, 'buttonCbPastePicture'],
        ],
        'label' => [
            'fields' => ['name'],
            'format' => '<span style="#padding-left#"><a href="#href#" title="#title#"><img src="#icon#"></span> %s <span style="color:#b3b3b3; padding-left:3px;">[#datum#] [#count_pics# images]</span></a>',
            'label_callback' => [TlGalleryCreatorAlbums::class, 'labelCb'],
        ],
        'global_operations' => [
            'all' => [
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
                'class' => 'header_edit_all',
                'href' => 'act=select',
            ],
            'reviseDatabase' => [
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
                'class' => 'icon_reviseDatabase',
                // href is set in TlGalleryCreatorAlbums::onloadCbSetUpPalettes
                'href' => '',
            ],
        ],
        'operations' => [
            'editheader' => [
                'button_callback' => [TlGalleryCreatorAlbums::class, 'buttonCbEditHeader'],
                'href' => 'act=edit',
                'icon' => 'header.svg',
            ],
            'edit' => [
                'attributes' => 'class="contextmenu"',
                'button_callback' => [TlGalleryCreatorAlbums::class, 'buttonCbEdit'],
                'href' => 'table=tl_gallery_creator_pictures',
                'icon' => 'bundles/markocupicgallerycreator/images/text_list_bullets.png',
            ],
            'delete' => [
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmAlbum'].'\'))return false;Backend.getScrollOffset()"',
                'button_callback' => [TlGalleryCreatorAlbums::class, 'buttonCbDelete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
            ],
            'toggle' => [
                'attributes' => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => [TlGalleryCreatorAlbums::class, 'toggleIcon'],
                'icon' => 'visible.gif',
            ],
            'uploadImages' => [
                'button_callback' => [TlGalleryCreatorAlbums::class, 'buttonCbAddImages'],
                'icon' => 'bundles/markocupicgallerycreator/images/image_add.png',
            ],
            'importImages' => [
                'button_callback' => [TlGalleryCreatorAlbums::class, 'buttonCbImportImages'],
                'icon' => 'bundles/markocupicgallerycreator/images/folder_picture.png',
            ],
            'cut' => [
                'attributes' => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => [TlGalleryCreatorAlbums::class, 'buttonCbCutPicture'],
                'href' => 'act=paste&mode=cut',
                'icon' => 'cut.gif',
            ],
        ],
    ],
    'palettes' => [
        '__selector__' => ['protected'],
        'default' => '{albumInfo},published,name,alias,description,keywords,assignedDir,albumInfo,owner,photographer,date,eventLocation,filePrefix,sortBy,caption,visitors;{album_preview_thumb_legend},thumb;{insert_article},insertArticlePre,insertArticlePost;{protection:hide},protected',
        'fileupload' => '{upload_settings},preserveFilename,imageResolution,imageQuality;{uploader_legend},uploader,fileupload',
        'importImages' => '{upload_settings},preserveFilename,multiSRC',
        'restrictedUser' => '{albumInfo},link_edit_images,albumInfo',
        'reviseDatabase' => '{maintenance},reviseDatabase',
    ],
    'subpalettes' => [
        'protected' => 'groups',
    ],
    'fields' => [
        'id' => ['sql' => 'int(10) unsigned NOT NULL auto_increment'],
        'pid' => [
            'foreignKey' => 'tl_gallery_creator_albums.alias',
            'relation' => ['type' => 'belongsTo', 'load' => 'lazy'],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'sorting' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'published' => [
            'eval' => ['submitOnChange' => true],
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default '1'",
        ],
        'date' => [
            'default' => time(),
            'eval' => ['mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'w50 wizard', 'submitOnChange' => false],
            'inputType' => 'text',
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'owner' => [
            'default' => BackendUser::getInstance()->id,
            'eval' => ['chosen' => true, 'includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'w50'],
            'foreignKey' => 'tl_user.name',
            'inputType' => 'select',
            'relation' => ['type' => 'hasOne', 'load' => 'eager'],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'assignedDir' => [
            'eval' => ['mandatory' => false, 'fieldType' => 'radio', 'tl_class' => 'clr'],
            'exclude' => true,
            'inputType' => 'fileTree',
            'sql' => 'blob NULL',
        ],
        'ownersName' => [
            'default' => BackendUser::getInstance()->name,
            'eval' => ['doNotShow' => true, 'tl_class' => 'w50 readonly'],
            'sql' => 'text NULL',
        ],
        'photographer' => [
            'eval' => ['mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false],
            'exclude' => true,
            'inputType' => 'text',
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'eventLocation' => [
            'eval' => ['mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false],
            'exclude' => true,
            'inputType' => 'text',
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'name' => [
            'eval' => ['mandatory' => true, 'tl_class' => 'w50', 'submitOnChange' => false],
            'inputType' => 'text',
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'alias' => [
            'eval' => ['doNotShow' => false, 'doNotCopy' => true, 'maxlength' => 50, 'tl_class' => 'w50', 'unique' => true],
            'inputType' => 'text',
            'save_callback' => [
                [TlGalleryCreatorAlbums::class, 'saveCbGenerateAlias'],
            ],
            'sql' => "varchar(128) COLLATE utf8_bin NOT NULL default ''",
        ],
        'description' => [
            'eval' => ['decodeEntities' => true, 'tl_class' => 'clr'],
            'exclude' => true,
            'inputType' => 'textarea',
            'search' => true,
            'sql' => 'text NULL',
        ],
        'keywords' => [
            'eval' => ['decodeEntities' => true, 'tl_class' => 'clr'],
            'exclude' => true,
            'inputType' => 'textarea',
            'search' => true,
            'sql' => 'text NULL',
        ],
        'caption' => [
            'eval' => ['tl_class' => 'clr long', 'allowHtml' => false, 'wrap' => 'soft'],
            'exclude' => true,
            'inputType' => 'textarea',
            'search' => true,
            'sql' => 'text NULL',
        ],
        'thumb' => [
            'eval' => ['doNotShow' => true, 'includeBlankOption' => true, 'nospace' => true, 'rgxp' => 'digit', 'maxlength' => 64, 'tl_class' => 'clr', 'submitOnChange' => true],
            'inputType' => 'radio',
            'input_field_callback' => [
                TlGalleryCreatorAlbums::class, 'inputFieldCbThumb',
            ],
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'fileupload' => [
            'eval' => ['doNotShow' => true],
            'input_field_callback' => [
                TlGalleryCreatorAlbums::class, 'inputFieldCbGenerateUploaderMarkup',
            ],
        ],
        'albumInfo' => [
            'eval' => ['doNotShow' => true],
            'input_field_callback' => [
                TlGalleryCreatorAlbums::class, 'inputFieldCbGenerateAlbumInformations',
            ],
        ],
        'uploader' => [
            'eval' => ['doNotShow' => true, 'tl_class' => 'clr', 'submitOnChange' => true],
            'inputType' => 'select',
            'load_callback' => [[TlGalleryCreatorAlbums::class, 'loadCbGetUploader']],
            'options' => ['be_gc_html5_uploader'],
            'save_callback' => [
                [TlGalleryCreatorAlbums::class, 'saveCbSaveUploader'],
            ],
            'sql' => "varchar(32) NOT NULL default 'be_gc_html5_uploader'",
        ],
        'imageResolution' => [
            'eval' => ['doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true],
            'inputType' => 'select',
            'load_callback' => [
                [TlGalleryCreatorAlbums::class, 'loadCbGetImageResolution'],
            ],
            'options' => array_merge(['no_scaling'], range(100, 3500, 50)),
            'reference' => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reference'],
            'save_callback' => [
                [TlGalleryCreatorAlbums::class, 'saveCbSaveImageResolution'],
            ],
            'sql' => "smallint(5) unsigned NOT NULL default '600'",
        ],
        'imageQuality' => [
            'eval' => ['doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true],
            'inputType' => 'select',
            'load_callback' => [
                [TlGalleryCreatorAlbums::class, 'loadCbGetImageQuality'],
            ],
            'options' => range(10, 100, 10),
            'save_callback' => [
                [TlGalleryCreatorAlbums::class, 'saveCbSaveImageQuality'],
            ],
            'sql' => "smallint(3) unsigned NOT NULL default '100'",
        ],
        'preserveFilename' => [
            'eval' => ['doNotShow' => true, 'submitOnChange' => true],
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default '1'",
        ],
        'multiSRC' => [
            'eval' => ['doNotShow' => true, 'multiple' => true, 'fieldType' => 'checkbox', 'files' => true, 'mandatory' => true],
            'exclude' => true,
            'inputType' => 'fileTree',
            'sql' => 'blob NULL',
        ],
        'protected' => [
            'eval' => ['doNotShow' => true, 'submitOnChange' => true, 'tl_class' => 'clr'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'sql' => "char(1) NOT NULL default ''",
        ],
        'groups' => [
            'eval' => ['doNotShow' => true, 'mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'],
            'foreignKey' => 'tl_member_group.name',
            'inputType' => 'checkbox',
            'sql' => 'blob NULL',
        ],
        'insertArticlePre' => [
            'eval' => ['doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50'],
            'inputType' => 'text',
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'insertArticlePost' => [
            'eval' => ['doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50'],
            'inputType' => 'text',
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'reviseDatabase' => [
            'eval' => ['doNotShow' => true],
            'input_field_callback' => [TlGalleryCreatorAlbums::class, 'inputFieldCbCleanDb'],
        ],
        'visitorsDetails' => [
            'inputType' => 'textarea',
            'sql' => 'blob NULL',
        ],
        'visitors' => [
            'eval' => ['maxlength' => 10, 'tl_class' => 'w50', 'rgxp' => 'digit'],
            'inputType' => 'text',
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'sortBy' => [
            'eval' => ['chosen' => true, 'submitOnChange' => true, 'tl_class' => 'w50'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => ['----', 'name_asc', 'name_desc', 'date_asc', 'date_desc'],
            'reference' => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums'],
            'save_callback' => [
                [TlGalleryCreatorAlbums::class, 'saveCbSortAlbum'],
            ],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'filePrefix' => [
            'eval' => ['mandatory' => false, 'tl_class' => 'clr', 'rgxp' => 'alnum', 'nospace' => true],
            'exclude' => true,
            'inputType' => 'text',
            'save_callback' => [
                [TlGalleryCreatorAlbums::class, 'saveCbValidateFileprefix'],
            ],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
    ],
];
