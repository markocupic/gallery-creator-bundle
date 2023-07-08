<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

use Markocupic\GalleryCreatorBundle\DataContainer\GalleryCreatorAlbums;
use Contao\BackendUser;

$GLOBALS['TL_DCA']['tl_gallery_creator_albums'] = [
    'config'      => [
        'ctable'            => ['tl_gallery_creator_pictures'],
        'notCopyable'       => true,
        'enableVersioning'  => true,
        'dataContainer'     => 'Table',
        'onload_callback'   => [
            [GalleryCreatorAlbums::class, 'onloadCbFileUpload'],
            [GalleryCreatorAlbums::class, 'onloadCbSetUpPalettes'],
            [GalleryCreatorAlbums::class, 'onloadCbImportFromFilesystem'],
            [GalleryCreatorAlbums::class, 'isAjaxRequest'],
            [GalleryCreatorAlbums::class, 'onloadCbCheckFolderSettings'],
        ],
        'ondelete_callback' => [
            [GalleryCreatorAlbums::class, 'ondeleteCb'],
        ],
        'sql'               => [
            'keys' => [
                'id'    => 'primary',
                'pid'   => 'index',
                'alias' => 'index',
            ],
        ],
    ],
    'edit'        => [
        'buttons_callback' => [[GalleryCreatorAlbums::class, 'buttonsCallback']],
    ],
    'list'        => [
        'sorting'           => [
            'panelLayout'           => 'limit,sort',
            'mode'                  => 5,
            'paste_button_callback' => [GalleryCreatorAlbums::class, 'buttonCbPastePicture'],
        ],
        'label'             => [
            'fields'         => ['name'],
            'format'         => '<span style="#padding-left#"><a href="#href#" title="#title#"><img src="#icon#"></span> %s <span style="color:#b3b3b3; padding-left:3px;">[#datum#] [#count_pics# images]</span></a>',
            'label_callback' => [GalleryCreatorAlbums::class, 'labelCb'],
        ],
        'global_operations' => [
            'all'           => [
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
            ],
            'revise_tables' => [
                'href'       => 'href is set in $this->setUpPalettes',
                'icon'       => 'bundles/markocupicgallerycreator/images/database_gear.png',
                'class'      => 'icon_revise_tables',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
            ],
        ],
        'operations'        => [
            'editheader'    => [
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['edit_album'],
                'href'            => 'act=edit',
                'icon'            => 'header.svg',
                'button_callback' => [GalleryCreatorAlbums::class, 'buttonCbEditHeader'],
            ],
            'edit'          => [
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['list_pictures'],
                'href'            => 'table=tl_gallery_creator_pictures',
                'icon'            => 'bundles/markocupicgallerycreator/images/text_list_bullets.png',
                'attributes'      => 'class="contextmenu"',
                'button_callback' => [GalleryCreatorAlbums::class, 'buttonCbEdit'],
            ],
            'delete'        => [
                'href'            => 'act=delete',
                'icon'            => 'delete.svg',
                'attributes'      => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmAlbum'].'\'))return false;Backend.getScrollOffset()"',
                'button_callback' => [GalleryCreatorAlbums::class, 'buttonCbDelete'],
            ],
            'toggle'        => [
                'icon'            => 'visible.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => [GalleryCreatorAlbums::class, 'toggleIcon'],
            ],
            'upload_images' => [
                'icon'            => 'bundles/markocupicgallerycreator/images/image_add.png',
                'button_callback' => [GalleryCreatorAlbums::class, 'buttonCbAddImages'],
            ],
            'import_images' => [
                'icon'            => 'bundles/markocupicgallerycreator/images/folder_picture.png',
                'button_callback' => [GalleryCreatorAlbums::class, 'buttonCbImportImages'],
            ],
            'cut'           => [
                'href'            => 'act=paste&mode=cut',
                'icon'            => 'cut.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => [GalleryCreatorAlbums::class, 'buttonCbCutPicture'],
            ],
        ],
    ],
    'palettes'    => [
        '__selector__'    => ['protected'],
        'default'         => '{album_info},published,name,alias,description,keywords,assignedDir,album_info,owner,photographer,date,event_location,filePrefix,sortBy,comment,visitors;{album_preview_thumb_legend},thumb;{insert_article},insert_article_pre,insert_article_post;{protection:hide},protected',
        'restricted_user' => '{album_info},link_edit_images,album_info',
        'fileupload'      => '{upload_settings},preserve_filename,img_resolution,img_quality;{uploader_legend},uploader,fileupload',
        'import_images'   => '{upload_settings},preserve_filename,multiSRC',
        'revise_tables'   => '{maintenance},revise_tables',
    ],
    'subpalettes' => [
        'protected' => 'groups',
    ],
    'fields'      => [
        'id'                  => ['sql' => "int(10) unsigned NOT NULL auto_increment"],
        'pid'                 => [
            'foreignKey' => 'tl_gallery_creator_albums.alias',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'sorting'             => ['sql' => "int(10) unsigned NOT NULL default '0'"],
        'tstamp'              => ['sql' => "int(10) unsigned NOT NULL default '0'"],
        'published'           => [
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true],
            'sql'       => "char(1) NOT NULL default '1'",
        ],
        'date'                => [
            'inputType' => 'text',
            'default'   => time(),
            'eval'      => ['mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'w50 wizard', 'submitOnChange' => false],
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'owner'               => [
            'default'    => BackendUser::getInstance()->id,
            'foreignKey' => 'tl_user.name',
            'inputType'  => 'select',
            'eval'       => ['chosen' => true, 'includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'w50'],
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
        ],
        'assignedDir'         => [
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => ['mandatory' => false, 'fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql'       => "blob NULL",
        ],
        'owners_name'         => [
            'default' => BackendUser::getInstance()->name,
            'eval'    => ['doNotShow' => true, 'tl_class' => 'w50 readonly'],
            'sql'     => "text NULL",
        ],
        'photographer'        => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'event_location'      => [
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'name'                => [
            'inputType' => 'text',
            'eval'      => ['mandatory' => true, 'tl_class' => 'w50', 'submitOnChange' => false],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'alias'               => [
            'inputType'     => 'text',
            'eval'          => ['doNotShow' => false, 'doNotCopy' => true, 'maxlength' => 50, 'tl_class' => 'w50', 'unique' => true],
            'save_callback' => [[GalleryCreatorAlbums::class, 'saveCbGenerateAlias']],
            'sql'           => "varchar(255) NOT NULL default ''",
        ],
        'description'         => [
            'exclude'   => true,
            'inputType' => 'textarea',
            'search'    => true,
            'eval'      => ['style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'],
            'sql'       => "text NULL",
        ],
        'keywords'            => [
            'exclude'   => true,
            'inputType' => 'textarea',
            'search'    => true,
            'eval'      => ['style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'],
            'sql'       => "text NULL",
        ],
        'comment'             => [
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => ['tl_class' => 'clr long', 'style' => 'height:7em;', 'allowHtml' => false, 'submitOnChange' => false, 'wrap' => 'soft'],
            'sql'       => "text NULL",
        ],
        'thumb'               => [
            'inputType'            => 'radio',
            'input_field_callback' => [GalleryCreatorAlbums::class, 'inputFieldCbThumb'],
            'eval'                 => ['doNotShow' => true, 'includeBlankOption' => true, 'nospace' => true, 'rgxp' => 'digit', 'maxlength' => 64, 'tl_class' => 'clr', 'submitOnChange' => true],
            'sql'                  => "int(10) unsigned NOT NULL default '0'",
        ],
        'fileupload'          => [
            'input_field_callback' => [GalleryCreatorAlbums::class, 'inputFieldCbGenerateUploaderMarkup'],
            'eval'                 => ['doNotShow' => true],
        ],
        'album_info'          => [
            'input_field_callback' => [GalleryCreatorAlbums::class, 'inputFieldCbGenerateAlbumInformationTable'],
            'eval'                 => ['doNotShow' => true],
        ],
        'uploader'            => [
            'inputType'     => 'select',
            'load_callback' => [[GalleryCreatorAlbums::class, 'loadCbGetUploader']],
            'save_callback' => [[GalleryCreatorAlbums::class, 'saveCbSaveUploader']],
            'options'       => ['be_gc_html5_uploader'],
            'eval'          => ['doNotShow' => true, 'tl_class' => 'clr', 'submitOnChange' => true],
            'sql'           => "varchar(32) NOT NULL default 'be_gc_html5_uploader'",
        ],
        'img_resolution'      => [
            'inputType'     => 'select',
            'load_callback' => [[GalleryCreatorAlbums::class, 'loadCbGetImageResolution']],
            'save_callback' => [[GalleryCreatorAlbums::class, 'saveCbSaveImageResolution']],
            'options'       => array_merge(['no_scaling'], range(100, 3500, 50)),
            'reference'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reference'],
            'eval'          => ['doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true],
            'sql'           => "smallint(5) unsigned NOT NULL default '600'",
        ],
        // save value in tl_user
        'img_quality'         => [
            'inputType'     => 'select',
            'load_callback' => [[GalleryCreatorAlbums::class, 'loadCbGetImageQuality']],
            'save_callback' => [[GalleryCreatorAlbums::class, 'saveCbSaveImageQuality']],
            'options'       => range(10, 100, 10),
            'eval'          => ['doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true],
            'sql'           => "smallint(3) unsigned NOT NULL default '100'",
        ],
        'preserve_filename'   => [
            'inputType' => 'checkbox',
            'eval'      => ['doNotShow' => true, 'submitOnChange' => true],
            'sql'       => "char(1) NOT NULL default '1'",
        ],
        'multiSRC'            => [
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => ['doNotShow' => true, 'multiple' => true, 'fieldType' => 'checkbox', 'files' => true, 'mandatory' => true],
            'sql'       => "blob NULL",
        ],
        'protected'           => [
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['doNotShow' => true, 'submitOnChange' => true, 'tl_class' => 'clr'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'groups'              => [
            'inputType'  => 'checkbox',
            'foreignKey' => 'tl_member_group.name',
            'eval'       => ['doNotShow' => true, 'mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'],
            'sql'        => "blob NULL",
        ],
        'insert_article_pre'  => [
            'inputType' => 'text',
            'eval'      => ['doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50',],
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'insert_article_post' => [
            'inputType' => 'text',
            'eval'      => ['doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50',],
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'revise_tables'       => [
            'input_field_callback' => [GalleryCreatorAlbums::class, 'inputFieldCbCleanDb'],
            'eval'                 => ['doNotShow' => true],
        ],
        'visitors_details'    => [
            'inputType' => 'textarea',
            'sql'       => "blob NULL",
        ],
        'visitors'            => [
            'inputType' => 'text',
            'eval'      => ['maxlength' => 10, 'tl_class' => 'w50', 'rgxp' => 'digit'],
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ],
        'sortBy'              => [
            'exclude'       => true,
            'save_callback' => [[GalleryCreatorAlbums::class, 'saveCbSortAlbum']],
            'inputType'     => 'select',
            'options'       => ['----', 'name_asc', 'name_desc', 'date_asc', 'date_desc'],
            'reference'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums'],
            'eval'          => ['chosen' => true, 'submitOnChange' => true, 'tl_class' => 'w50'],
            'sql'           => "varchar(32) NOT NULL default ''",
        ],
        'filePrefix'          => [
            'exclude'       => true,
            'inputType'     => 'text',
            'save_callback' => [[GalleryCreatorAlbums::class, 'saveCbValidateFileprefix']],
            'eval'          => ['mandatory' => false, 'tl_class' => 'clr', 'rgxp' => 'alnum', 'nospace' => true],
            'sql'           => "varchar(32) NOT NULL default ''",
        ],
    ],
];

