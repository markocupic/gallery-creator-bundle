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

use Contao\BackendUser;
use Markocupic\GalleryCreatorBundle\Dca\TlGalleryCreatorAlbums;

/**
 * Table tl_gallery_creator_albums
 */
$GLOBALS['TL_DCA']['tl_gallery_creator_albums'] = array(
	// Config
	'config'      => array(
		'ctable'            => array('tl_gallery_creator_pictures'),
		'notCopyable'       => true,
		'enableVersioning'  => true,
		'dataContainer'     => 'Table',
		'onload_callback'   => array(
			array(TlGalleryCreatorAlbums::class, 'handleAjaxRequests'),
			array(TlGalleryCreatorAlbums::class, 'onloadCbCheckFolderSettings'),
			array(TlGalleryCreatorAlbums::class, 'onloadCbFileupload'),
			array(TlGalleryCreatorAlbums::class, 'onloadCbImportFromFilesystem'),
			array(TlGalleryCreatorAlbums::class, 'onloadCbSetUpPalettes'),
		),
		'ondelete_callback' => array(
			array(TlGalleryCreatorAlbums::class, 'ondeleteCb'),
		),
		'sql'               => array(
			'keys' => array(
				'id'    => 'primary',
				'pid'   => 'index',
				'alias' => 'index',
			),
		),
	),
	// Buttons callback
	'edit'        => array(
		'buttons_callback' => array(array(TlGalleryCreatorAlbums::class, 'buttonsCallback'))
	),
	// List
	'list'        => array(
		'sorting'           => array(
			'mode'                  => 5,
			'panelLayout'           => 'limit,sort',
			'paste_button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbPastePicture'),
		),
		'label'             => array(
			'fields'         => array('name'),
			'format'         => '<span style="#padding-left#"><a href="#href#" title="#title#"><img src="#icon#"></span> %s <span style="color:#b3b3b3; padding-left:3px;">[#datum#] [#count_pics# images]</span></a>',
			'label_callback' => array(TlGalleryCreatorAlbums::class, 'labelCb'),
		),
		'global_operations' => array(
			'all'           => array(
				'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
				'class'      => 'header_edit_all',
				'href'       => 'act=select',
			),
			'revise_database' => array(
				'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
				'class'      => 'icon_revise_database',
				// href is set in TlGalleryCreatorAlbums::onloadCbSetUpPalettes
				'href'       => '',
			),
		),
		'operations'        => array(
			'editheader'    => array
			(
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbEditHeader'),
				'href'            => 'act=edit',
				'icon'            => 'header.svg',
			),
			'edit'          => array(
				'attributes'      => 'class="contextmenu"',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbEdit'),
				'href'            => 'table=tl_gallery_creator_pictures',
				'icon'            => 'bundles/markocupicgallerycreator/images/text_list_bullets.png',
			),
			'delete'        => array(
				'attributes'      => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmAlbum'] . '\'))return false;Backend.getScrollOffset()"',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbDelete'),
				'href'            => 'act=delete',
				'icon'            => 'delete.gif',
			),
			'toggle'        => array(
				'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'toggleIcon'),
				'icon'            => 'visible.gif',
			),
			'upload_images' => array(
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbAddImages'),
				'icon'            => 'bundles/markocupicgallerycreator/images/image_add.png',
			),
			'import_images' => array(
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbImportImages'),
				'icon'            => 'bundles/markocupicgallerycreator/images/folder_picture.png',
			),
			'cut'           => array(
				'attributes'      => 'onclick="Backend.getScrollOffset();"',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbCutPicture'),
				'href'            => 'act=paste&mode=cut',
				'icon'            => 'cut.gif',
			),
		),
	),
	// Palettes
	'palettes'    => array(
		'__selector__'    => array('protected'),
		'default'         => '{album_info},published,name,alias,description,keywords,assignedDir,album_info,owner,photographer,date,event_location,filePrefix,sortBy,comment,visitors;{album_preview_thumb_legend},thumb;{insert_article},insert_article_pre,insert_article_post;{protection:hide},protected',
		'fileupload'      => '{upload_settings},preserve_filename,img_resolution,img_quality;{uploader_legend},uploader,fileupload',
		'import_images'   => '{upload_settings},preserve_filename,multiSRC',
		'restricted_user' => '{album_info},link_edit_images,album_info',
		'revise_database'   => '{maintenance},revise_database',
	),
	// Subpalettes
	'subpalettes' => array(
		'protected' => 'groups'
	),
	// Fields
	'fields'      => array(
		'id'                  => array('sql' => "int(10) unsigned NOT NULL auto_increment"),
		'pid'                 => array(
			'foreignKey' => 'tl_gallery_creator_albums.alias',
			'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		),
		'sorting'             => array('sql' => "int(10) unsigned NOT NULL default '0'"),
		'tstamp'              => array('sql' => "int(10) unsigned NOT NULL default '0'"),
		'published'           => array(
			'eval'      => array('submitOnChange' => true),
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default '1'",
		),
		'date'                => array(
			'default'   => time(),
			'eval'      => array('mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'w50 wizard', 'submitOnChange' => false),
			'inputType' => 'text',
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		),
		'owner'               => array(
			'default'    => BackendUser::getInstance()->id,
			'eval'       => array('chosen' => true, 'includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'w50'),
			'foreignKey' => 'tl_user.name',
			'inputType'  => 'select',
			'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		),
		'assignedDir'         => array(
			'eval'      => array('mandatory' => false, 'fieldType' => 'radio', 'tl_class' => 'clr'),
			'exclude'   => true,
			'inputType' => 'fileTree',
			'sql'       => "blob NULL",
		),
		'owners_name'         => array(
			'default' => BackendUser::getInstance()->name,
			'eval'    => array('doNotShow' => true, 'tl_class' => 'w50 readonly'),
			'sql'     => "text NULL",
		),
		'photographer'      => array(
			'eval'      => array('mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false),
			'exclude'   => true,
			'inputType' => 'text',
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'event_location'      => array(
			'eval'      => array('mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false),
			'exclude'   => true,
			'inputType' => 'text',
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'name'                => array(
			'eval'      => array('mandatory' => true, 'tl_class' => 'w50', 'submitOnChange' => false),
			'inputType' => 'text',
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'alias'               => array(
			'eval'          => array('doNotShow' => false, 'doNotCopy' => true, 'maxlength' => 50, 'tl_class' => 'w50', 'unique' => true),
			'inputType'     => 'text',
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbGenerateAlias')),
			'sql'           => "varchar(128) COLLATE utf8_bin NOT NULL default ''",
		),
		'description'         => array(
			'eval'      => array('style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'),
			'exclude'   => true,
			'inputType' => 'textarea',
			'search'    => true,
			'sql'       => "text NULL",
		),
		'keywords'            => array(
			'eval'      => array('style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'),
			'exclude'   => true,
			'inputType' => 'textarea',
			'search'    => true,
			'sql'       => "text NULL",
		),
		'comment'             => array(
			'eval'      => array('tl_class' => 'clr long', 'style' => 'height:7em;', 'allowHtml' => false, 'submitOnChange' => false, 'wrap' => 'soft'),
			'exclude'   => true,
			'inputType' => 'textarea',
			'sql'       => "text NULL",
		),
		'thumb'               => array(
			'eval'                 => array('doNotShow' => true, 'includeBlankOption' => true, 'nospace' => true, 'rgxp' => 'digit', 'maxlength' => 64, 'tl_class' => 'clr', 'submitOnChange' => true),
			'inputType'            => 'radio',
			'input_field_callback' => array(TlGalleryCreatorAlbums::class, 'inputFieldCbThumb'),
			'sql'                  => "int(10) unsigned NOT NULL default '0'",
		),
		'fileupload'          => array(
			'eval'                 => array('doNotShow' => true),
			'input_field_callback' => array(TlGalleryCreatorAlbums::class, 'inputFieldCbGenerateUploaderMarkup'),
		),
		'album_info'          => array(
			'eval'                 => array('doNotShow' => true),
			'input_field_callback' => array(TlGalleryCreatorAlbums::class, 'inputFieldCbGenerateAlbumInformations'),
		),
		// save value in tl_user
		'uploader'            => array(
			'default'       => 'be_gc_jumploader',
			'eval'          => array('doNotShow' => true, 'tl_class' => 'clr', 'submitOnChange' => true),
			'inputType'     => 'select',
			'load_callback' => array(array(TlGalleryCreatorAlbums::class, 'loadCbGetUploader')),
			'options'       => array('be_gc_html5_uploader'),
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbSaveUploader')),
			'sql'           => "varchar(32) NOT NULL default 'be_gc_html5_uploader'",
		),
		// save value in tl_user
		'img_resolution'      => array(
			'default'       => '600',
			'eval'          => array('doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true),
			'inputType'     => 'select',
			'load_callback' => array(array(TlGalleryCreatorAlbums::class, 'loadCbGetImageResolution')),
			'options'       => array_merge(array('no_scaling'), range(100, 3500, 50)),
			'reference'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reference'],
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbSaveImageResolution')),
			'sql'           => "smallint(5) unsigned NOT NULL default '600'",
		),
		// save value in tl_user
		'img_quality'         => array(
			'default'       => '100',
			'eval'          => array('doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true),
			'inputType'     => 'select',
			'load_callback' => array(array(TlGalleryCreatorAlbums::class, 'loadCbGetImageQuality')),
			'options'       => range(10, 100, 10),
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbSaveImageQuality')),
			'sql'           => "smallint(3) unsigned NOT NULL default '100'",
		),
		'preserve_filename'   => array(
			'default'   => true,
			'eval'      => array('doNotShow' => true, 'submitOnChange' => true),
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default ''",
		),
		'multiSRC'            => array(
			'eval'      => array('doNotShow' => true, 'multiple' => true, 'fieldType' => 'checkbox', 'files' => true, 'mandatory' => true),
			'exclude'   => true,
			'inputType' => 'fileTree',
			'sql'       => "blob NULL",
		),
		'protected'           => array(
			'eval'      => array('doNotShow' => true, 'submitOnChange' => true, 'tl_class' => 'clr'),
			'exclude'   => true,
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default ''",
		),
		'groups'              => array(
			'eval'       => array('doNotShow' => true, 'mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'),
			'foreignKey' => 'tl_member_group.name',
			'inputType'  => 'checkbox',
			'sql'        => "blob NULL",
		),
		'insert_article_pre'  => array(
			'eval'      => array('doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50'),
			'inputType' => 'text',
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		),
		'insert_article_post' => array(
			'eval'      => array('doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50'),
			'inputType' => 'text',
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		),
		'revise_database'       => array(
			'eval'                 => array('doNotShow' => true),
			'input_field_callback' => array(TlGalleryCreatorAlbums::class, 'inputFieldCbCleanDb'),
		),
		'visitors_details'    => array(
			'inputType' => 'textarea',
			'sql'       => "blob NULL",
		),
		'visitors'            => array(
			'eval'      => array('maxlength' => 10, 'tl_class' => 'w50', 'rgxp' => 'digit'),
			'inputType' => 'text',
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		),
		'sortBy'              => array(
			'default'       => 'custom',
			'eval'          => array('chosen' => true, 'submitOnChange' => true, 'tl_class' => 'w50'),
			'exclude'       => true,
			'inputType'     => 'select',
			'options'       => array('----', 'name_asc', 'name_desc', 'date_asc', 'date_desc'),
			'reference'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums'],
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbSortAlbum')),
			'sql'           => "varchar(32) NOT NULL default ''",
		),
		'filePrefix'          => array(
			'eval'          => array('mandatory' => false, 'tl_class' => 'clr', 'rgxp' => 'alnum', 'nospace' => true),
			'exclude'       => true,
			'inputType'     => 'text',
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbValidateFileprefix')),
			'sql'           => "varchar(32) NOT NULL default ''",
		),
	),
);
