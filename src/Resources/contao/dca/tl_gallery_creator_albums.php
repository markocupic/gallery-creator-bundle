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
			array(TlGalleryCreatorAlbums::class, 'onloadCbFileupload'),
			array(TlGalleryCreatorAlbums::class, 'onloadCbSetUpPalettes'),
			array(TlGalleryCreatorAlbums::class, 'onloadCbImportFromFilesystem'),
			array(TlGalleryCreatorAlbums::class, 'isAjaxRequest'),
			array(TlGalleryCreatorAlbums::class, 'onloadCbCheckFolderSettings'),
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
			'panelLayout'           => 'limit,sort',
			'mode'                  => 5,
			'paste_button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbPastePicture'),
		),
		'label'             => array(
			'fields'         => array('name'),
			'format'         => '<span style="#padding-left#"><a href="#href#" title="#title#"><img src="#icon#"></span> %s <span style="color:#b3b3b3; padding-left:3px;">[#datum#] [#count_pics# images]</span></a>',
			'label_callback' => array(TlGalleryCreatorAlbums::class, 'labelCb'),
		),
		'global_operations' => array(
			'all'           => array(
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
			),
			'revise_tables' => array(
				'href'       => 'href is set in $this->setUpPalettes',
				'class'      => 'icon_revise_tables',
				'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
			),
		),
		'operations'        => array(
			'editheader'    => array
			(
				'href'            => 'act=edit',
				'icon'            => 'header.svg',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbEditHeader'),
			),
			'edit'          => array(
				'href'            => 'table=tl_gallery_creator_pictures',
				'icon'            => 'bundles/markocupicgallerycreator/images/text_list_bullets.png',
				'attributes'      => 'class="contextmenu"',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbEdit'),
			),
			'delete'        => array(
				'href'            => 'act=delete',
				'icon'            => 'delete.gif',
				'attributes'      => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmAlbum'] . '\'))return false;Backend.getScrollOffset()"',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbDelete'),
			),
			'toggle'        => array(
				'icon'            => 'visible.gif',
				'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'toggleIcon'),
			),
			'upload_images' => array(
				'icon'            => 'bundles/markocupicgallerycreator/images/image_add.png',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbAddImages'),
			),
			'import_images' => array(
				'icon'            => 'bundles/markocupicgallerycreator/images/folder_picture.png',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbImportImages'),
			),
			'cut'           => array(
				'href'            => 'act=paste&mode=cut',
				'icon'            => 'cut.gif',
				'attributes'      => 'onclick="Backend.getScrollOffset();"',
				'button_callback' => array(TlGalleryCreatorAlbums::class, 'buttonCbCutPicture'),
			),
		),
	),
	// Palettes
	'palettes'    => array(
		'__selector__'    => array('protected'),
		'default'         => '{album_info},published,name,alias,description,keywords,assignedDir,album_info,owner,photographer,date,event_location,filePrefix,sortBy,comment,visitors;{album_preview_thumb_legend},thumb;{insert_article},insert_article_pre,insert_article_post;{protection:hide},protected',
		'restricted_user' => '{album_info},link_edit_images,album_info',
		'fileupload'      => '{upload_settings},preserve_filename,img_resolution,img_quality;{uploader_legend},uploader,fileupload',
		'import_images'   => '{upload_settings},preserve_filename,multiSRC',
		'revise_tables'   => '{maintenance},revise_tables',
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
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
		),
		'sorting'             => array('sql' => "int(10) unsigned NOT NULL default '0'"),
		'tstamp'              => array('sql' => "int(10) unsigned NOT NULL default '0'"),
		'published'           => array(
			'inputType' => 'checkbox',
			'eval'      => array('submitOnChange' => true),
			'sql'       => "char(1) NOT NULL default '1'",
		),
		'date'                => array(
			'inputType' => 'text',
			'default'   => time(),
			'eval'      => array('mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'w50 wizard', 'submitOnChange' => false),
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		),
		'owner'               => array(
			'default'    => BackendUser::getInstance()->id,
			'foreignKey' => 'tl_user.name',
			'inputType'  => 'select',
			'eval'       => array('chosen' => true, 'includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'w50'),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
		),
		'assignedDir'         => array(
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => array('mandatory' => false, 'fieldType' => 'radio', 'tl_class' => 'clr'),
			'sql'       => "blob NULL",
		),
		'owners_name'         => array(
			'default' => BackendUser::getInstance()->name,
			'eval'    => array('doNotShow' => true, 'tl_class' => 'w50 readonly'),
			'sql'     => "text NULL",
		),
		'photographer'      => array(
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'event_location'      => array(
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'name'                => array(
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'tl_class' => 'w50', 'submitOnChange' => false),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'alias'               => array(
			'inputType'     => 'text',
			'eval'          => array('doNotShow' => false, 'doNotCopy' => true, 'maxlength' => 50, 'tl_class' => 'w50', 'unique' => true),
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbGenerateAlias')),
			'sql'           => "varchar(128) COLLATE utf8_bin NOT NULL default ''",
		),
		'description'         => array(
			'exclude'   => true,
			'inputType' => 'textarea',
			'search'    => true,
			'eval'      => array('style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'),
			'sql'       => "text NULL",
		),
		'keywords'            => array(
			'exclude'   => true,
			'inputType' => 'textarea',
			'search'    => true,
			'eval'      => array('style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'),
			'sql'       => "text NULL",
		),
		'comment'             => array(
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => array('tl_class' => 'clr long', 'style' => 'height:7em;', 'allowHtml' => false, 'submitOnChange' => false, 'wrap' => 'soft'),
			'sql'       => "text NULL",
		),
		'thumb'               => array(
			'inputType'            => 'radio',
			'input_field_callback' => array(TlGalleryCreatorAlbums::class, 'inputFieldCbThumb'),
			'eval'                 => array('doNotShow' => true, 'includeBlankOption' => true, 'nospace' => true, 'rgxp' => 'digit', 'maxlength' => 64, 'tl_class' => 'clr', 'submitOnChange' => true),
			'sql'                  => "int(10) unsigned NOT NULL default '0'",
		),
		'fileupload'          => array(
			'input_field_callback' => array(TlGalleryCreatorAlbums::class, 'inputFieldCbGenerateUploaderMarkup'),
			'eval'                 => array('doNotShow' => true),
		),
		'album_info'          => array(
			'input_field_callback' => array(TlGalleryCreatorAlbums::class, 'inputFieldCbGenerateAlbumInformations'),
			'eval'                 => array('doNotShow' => true),
		),
		// save value in tl_user
		'uploader'            => array(
			'default'       => 'be_gc_jumploader',
			'inputType'     => 'select',
			'load_callback' => array(array(TlGalleryCreatorAlbums::class, 'loadCbGetUploader')),
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbSaveUploader')),
			'options'       => array('be_gc_html5_uploader'),
			'eval'          => array('doNotShow' => true, 'tl_class' => 'clr', 'submitOnChange' => true),
			'sql'           => "varchar(32) NOT NULL default 'be_gc_html5_uploader'",
		),
		// save value in tl_user
		'img_resolution'      => array(
			'default'       => '600',
			'inputType'     => 'select',
			'load_callback' => array(array(TlGalleryCreatorAlbums::class, 'loadCbGetImageResolution')),
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbSaveImageResolution')),
			'options'       => array_merge(array('no_scaling'), range(100, 3500, 50)),
			'reference'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reference'],
			'eval'          => array('doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true),
			'sql'           => "smallint(5) unsigned NOT NULL default '600'",
		),
		// save value in tl_user
		'img_quality'         => array(
			'default'       => '100',
			'inputType'     => 'select',
			'load_callback' => array(array(TlGalleryCreatorAlbums::class, 'loadCbGetImageQuality')),
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbSaveImageQuality')),
			'options'       => range(10, 100, 10),
			'eval'          => array('doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true),
			'sql'           => "smallint(3) unsigned NOT NULL default '100'",
		),
		'preserve_filename'   => array(
			'inputType' => 'checkbox',
			'default'   => true,
			'eval'      => array('doNotShow' => true, 'submitOnChange' => true),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'multiSRC'            => array(
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => array('doNotShow' => true, 'multiple' => true, 'fieldType' => 'checkbox', 'files' => true, 'mandatory' => true),
			'sql'       => "blob NULL",
		),
		'protected'           => array(
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => array('doNotShow' => true, 'submitOnChange' => true, 'tl_class' => 'clr'),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'groups'              => array(
			'inputType'  => 'checkbox',
			'foreignKey' => 'tl_member_group.name',
			'eval'       => array('doNotShow' => true, 'mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'),
			'sql'        => "blob NULL",
		),
		'insert_article_pre'  => array(
			'inputType' => 'text',
			'eval'      => array('doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50'),
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		),
		'insert_article_post' => array(
			'inputType' => 'text',
			'eval'      => array('doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50'),
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		),
		'revise_tables'       => array(
			'input_field_callback' => array(TlGalleryCreatorAlbums::class, 'inputFieldCbCleanDb'),
			'eval'                 => array('doNotShow' => true),
		),
		'visitors_details'    => array(
			'inputType' => 'textarea',
			'sql'       => "blob NULL",
		),
		'visitors'            => array(
			'inputType' => 'text',
			'eval'      => array('maxlength' => 10, 'tl_class' => 'w50', 'rgxp' => 'digit'),
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		),
		'sortBy'              => array(
			'exclude'       => true,
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbSortAlbum')),
			'inputType'     => 'select',
			'default'       => 'custom',
			'options'       => array('----', 'name_asc', 'name_desc', 'date_asc', 'date_desc'),
			'reference'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums'],
			'eval'          => array('chosen' => true, 'submitOnChange' => true, 'tl_class' => 'w50'),
			'sql'           => "varchar(32) NOT NULL default ''",
		),
		'filePrefix'          => array(
			'exclude'       => true,
			'inputType'     => 'text',
			'save_callback' => array(array(TlGalleryCreatorAlbums::class, 'saveCbValidateFileprefix')),
			'eval'          => array('mandatory' => false, 'tl_class' => 'clr', 'rgxp' => 'alnum', 'nospace' => true),
			'sql'           => "varchar(32) NOT NULL default ''",
		),
	),
);
