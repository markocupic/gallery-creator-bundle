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
use Markocupic\GalleryCreatorBundle\Dca\TlGalleryCreatorPictures;

$GLOBALS['TL_DCA']['tl_gallery_creator_pictures'] = array(
	'config'      => array(
		'ptable'            => 'tl_gallery_creator_albums',
		'notCopyable'       => true,
		'notCreatable'      => true,
		'enableVersioning'  => true,
		'dataContainer'     => 'Table',
		'onload_callback'   => array(
			array(TlGalleryCreatorPictures::class,				'onloadCbCheckPermission'),
			array(TlGalleryCreatorPictures::class,				'onloadCbSetUpPalettes'),
		),
		'ondelete_callback' => array(
			array(TlGalleryCreatorPictures::class,				'ondeleteCb'),
		),
		'oncut_callback'    => array(
			array(TlGalleryCreatorPictures::class,				'oncutCb'),
		),
		'sql'               => array(
			'keys' => array(
				'id'  => 'primary',
				'pid' => 'index',
			),
		),
	),
	'edit' => array(
		'buttons_callback' => array(TlGalleryCreatorPictures::class, 'editButtonsCallback')
	),
	'list'        => array(
		'sorting'           => array(
			'child_record_callback' => array(TlGalleryCreatorPictures::class, 'childRecordCb'),
			'fields'                => array('sorting'),
			'headerFields'          => array('id', 'date', 'owners_name', 'name', 'comment', 'thumb'),
			'mode'                  => 4,
			'panelLayout'           => 'filter;search,limit',
		),
		'global_operations' => array(
			'fileupload' => array(
				'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
				'class'      => 'icon_image_add',
				'href'       => 'act=edit&table=tl_gallery_creator_albums&mode=fileupload',
			),
			'all'        => array(
				'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
				'class'      => 'header_edit_all',
				'href'       => 'act=select',
			),
		),
		'operations'        => array(
			'edit'        => array(
				'button_callback' => array(TlGalleryCreatorPictures::class, 'buttonCbEditImage'),
				'href'            => 'act=edit',
				'icon'            => 'edit.gif',
			),
			'delete'      => array(
				'attributes'      => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmPicture'] . '\')) return false; Backend.getScrollOffset();"',
				'button_callback' => array(TlGalleryCreatorPictures::class, 'buttonCbDeletePicture'),
				'href'            => 'act=delete',
				'icon'            => 'delete.gif',
			),
			'cut'         => array(
				'attributes'      => 'onclick="Backend.getScrollOffset();"',
				'button_callback' => array(TlGalleryCreatorPictures::class, 'buttonCbCutImage'),
				'href'            => 'act=paste&mode=cut',
				'icon'            => 'cut.gif',
			),
			'imagerotate' => array(
				'attributes'      => 'onclick="Backend.getScrollOffset();"',
				'button_callback' => array(TlGalleryCreatorPictures::class, 'buttonCbRotateImage'),
				'href'            => 'mode=imagerotate',
				'icon'            => 'bundles/markocupicgallerycreator/images/arrow_rotate_clockwise.png',
			),
			'toggle'      => array(
				'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
				'button_callback' => array(TlGalleryCreatorPictures::class, 'toggleIcon'),
				'icon'            => 'visible.gif',
			),
		),
	),
	// Palettes
	'palettes'    => array(
		'__selector__'    => array('addCustomThumb'),
		'default'         => 'published,picture,owner,date,imageInfo,addCustomThumb,title,comment;{media_integration:hide},socialMediaSRC,localMediaSRC;{expert_legend:hide},cssID',
		'restricted_user' => 'imageInfo,picture',
	),
	// Subpalettes
	'subpalettes' => array('addCustomThumb' => 'customThumb'),
	// Fields
	'fields'      => array(
		'id'             => array('sql' => "int(10) unsigned NOT NULL auto_increment"),
		'pid'            => array(
			'eval'       => array('doNotShow' => true),
			'foreignKey' => 'tl_gallery_creator_albums.alias',
			'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
			'sql'        => "int(10) unsigned NOT NULL default '0'",
		),
		'path'           => array(
			'sql' => "varchar(255) NOT NULL default ''",
		),
		'uuid'           => array(
			'sql' => "binary(16) NULL",
		),
		'sorting'        => array(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'tstamp'         => array(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'published'      => array(
			'eval'      => array('isBoolean' => true, 'submitOnChange' => true, 'tl_class' => 'long'),
			'filter'    => true,
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default '1'",
		),
		'imageInfo'     => array(
			'eval'                 => array('tl_class' => 'clr'),
			'input_field_callback' => array(TlGalleryCreatorPictures::class, 'inputFieldCbGenerateImageInformation'),
		),
		'title'          => array(
			'eval'      => array('allowHtml' => false, 'decodeEntities' => true, 'rgxp' => 'alnum'),
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'text',
			'search'    => true,
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		//activate subpalette
		'externalFile'   => array(
			'sql' => "char(1) NOT NULL default ''"
		),
		'comment'        => array(
			'cols'      => 20,
			'eval'      => array('decodeEntities' => true, 'tl_class' => 'clr'),
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'textarea',
			'rows'      => 6,
			'search'    => true,
			'sql'       => "text NULL",
		),
		'picture'        => array(
			'eval'                 => array('tl_class' => 'clr'),
			'input_field_callback' => array(TlGalleryCreatorPictures::class, 'inputFieldCbGenerateImage'),
		),
		'date'           => array(
			'inputType' => 'text',
			// when upload a new image, the image inherits the date of the parent album
			'default'   => time(),
			'filter'    => true,
			'search'    => true,
			'eval'      => array('mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'clr wizard ', 'submitOnChange' => false),
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		),
		'addCustomThumb' => array(
			'eval'      => array('submitOnChange' => true),
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default ''",
		),
		'customThumb'    => array(
			'eval'      => array('fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'extensions' => 'jpeg,jpg,gif,png,bmp,tiff'),
			'exclude'   => true,
			'inputType' => 'fileTree',
			'sql'       => "blob NULL",
		),
		'owner'          => array(
			'default'    => BackendUser::getInstance()->id,
			'eval'       => array('includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'clr w50'),
			'filter'     => true,
			'foreignKey' => 'tl_user.name',
			'inputType'  => 'select',
			'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
			'search'     => true,
			'sql'        => "int(10) NOT NULL default '0'",
		),
		'socialMediaSRC' => array(
			'eval'      => array('tl_class' => 'clr'),
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'text',
			'search'    => true,
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'localMediaSRC'  => array(
			'eval'      => array('files' => true, 'filesOnly' => true, 'fieldType' => 'radio'),
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'fileTree',
			'search'    => true,
			'sql'       => "binary(16) NULL",
		),
		'cssID'          => array(
			'eval'      => array('multiple' => true, 'size' => 2, 'tl_class' => 'w50 clr'),
			'exclude'   => true,
			'inputType' => 'text',
			'sql'       => "varchar(255) NOT NULL default ''",
		),
	),
);
