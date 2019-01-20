<?php

/*
 * This file is part of Gallery Creator Bundle (extension for the Contao CMS).
 *
 * (c) Marko Cupic
 *
 * @license MIT
 */

$GLOBALS['TL_DCA']['tl_gallery_creator_pictures'] = array(
    // Config
    'config'      => array(
        'ptable'            => 'tl_gallery_creator_albums',
        'notCopyable'       => true,
        'notCreatable'      => true,
        'enableVersioning'  => true,
        'dataContainer'     => 'Table',
        'onload_callback'   => array(
            array(
                'tl_gallery_creator_pictures',
                'onloadCbCheckPermission',
            ),
            array(
                'tl_gallery_creator_pictures',
                'onloadCbSetUpPalettes',
            ),
        ),
        'ondelete_callback' => array(
            array(
                'tl_gallery_creator_pictures',
                'ondeleteCb',
            ),
        ),
        'oncut_callback'    => array(
            array(
                'tl_gallery_creator_pictures',
                'oncutCb',
            ),
        ),
        'sql'               => array(
            'keys' => array(
                'id'  => 'primary',
                'pid' => 'index',
            ),
        ),
    ),
    //list
    'list'        => array(
        'sorting'           => array(
            'mode'                  => 4,
            'fields'                => array('sorting'),
            'panelLayout'           => 'filter;search,limit',
            'headerFields'          => array('id', 'date', 'owners_name', 'name', 'comment', 'thumb'),
            'child_record_callback' => array('tl_gallery_creator_pictures', 'childRecordCb'),
        ),
        'global_operations' => array(
            'fileupload' => array(
                'label'      => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['fileupload'],
                'href'       => 'act=edit&table=tl_gallery_creator_albums&mode=fileupload',
                'class'      => 'icon_image_add',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
            ),
            'all'        => array(
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
            ),
        ),
        'operations'        => array(
            'edit'        => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['edit'],
                'href'            => 'act=edit',
                'icon'            => 'edit.gif',
                'button_callback' => array('tl_gallery_creator_pictures', 'buttonCbEditImage'),
            ),
            'delete'      => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['delete'],
                'href'            => 'act=delete',
                'icon'            => 'delete.gif',
                'attributes'      => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmPicture'] . '\')) return false; Backend.getScrollOffset();"',
                'button_callback' => array('tl_gallery_creator_pictures', 'buttonCbDeletePicture'),
            ),
            'cut'         => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['cut'],
                'href'            => 'act=paste&mode=cut',
                'icon'            => 'cut.gif',
                'attributes'      => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => array('tl_gallery_creator_pictures', 'buttonCbCutImage'),
            ),
            'imagerotate' => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['imagerotate'],
                'href'            => 'mode=imagerotate',
                'icon'            => 'bundles/markocupicgallerycreator/images/arrow_rotate_clockwise.png',
                'attributes'      => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => array('tl_gallery_creator_pictures', 'buttonCbRotateImage'),
            ),
            'toggle'      => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['toggle'],
                'icon'            => 'visible.gif',
                'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => array('tl_gallery_creator_pictures', 'toggleIcon'),
            ),
        ),
    ),
    // Palettes
    'palettes'    => array(
        '__selector__'    => array('addCustomThumb'),
        'default'         => 'published,picture,owner,date,image_info,addCustomThumb,title,comment;{media_integration:hide},socialMediaSRC,localMediaSRC;{expert_legend:hide},cssID',
        'restricted_user' => 'image_info,picture',
    ),
    // Subpalettes
    'subpalettes' => array('addCustomThumb' => 'customThumb'),
    // Fields
    'fields'      => array(

        'id'             => array('sql' => "int(10) unsigned NOT NULL auto_increment"),
        'pid'            => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['pid'],
            'foreignKey' => 'tl_gallery_creator_albums.alias',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
            'eval'       => array('doNotShow' => true),
        ),
        'path'           => array('sql' => "varchar(255) NOT NULL default ''"),
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
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['published'],
            'inputType' => 'checkbox',
            'filter'    => true,
            'eval'      => array('isBoolean' => true, 'submitOnChange' => true, 'tl_class' => 'long'),
            'sql'       => "char(1) NOT NULL default '1'",
        ),
        'image_info'     => array(
            'label'                => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['image_info'],
            'input_field_callback' => array('tl_gallery_creator_pictures', 'inputFieldCbGenerateImageInformation'),
            'eval'                 => array('tl_class' => 'clr',),
        ),
        'title'          => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['title'],
            'exclude'   => true,
            'inputType' => 'text',
            'filter'    => true,
            'search'    => true,
            'eval'      => array('allowHtml' => false, 'decodeEntities' => true, 'rgxp' => 'alnum'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        //activate subpalette
        'externalFile'   => array('sql' => "char(1) NOT NULL default ''"),
        'comment'        => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['comment'],
            'inputType' => 'textarea',
            'exclude'   => true,
            'filter'    => true,
            'search'    => true,
            'cols'      => 20,
            'rows'      => 6,
            'eval'      => array('decodeEntities' => true, 'tl_class' => 'clr'),
            'sql'       => "text NULL",
        ),
        'picture'        => array(
            'label'                => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['picture'],
            'input_field_callback' => array('tl_gallery_creator_pictures', 'inputFieldCbGenerateImage'),
            'eval'                 => array('tl_class' => 'clr'),
        ),
        'date'           => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['date'],
            'inputType' => 'text',
            // when upload a new image, the image inherits the date of the parent album
            'default'   => time(),
            'filter'    => true,
            'search'    => true,
            'eval'      => array('mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'clr wizard ', 'submitOnChange' => false),
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ),
        'addCustomThumb' => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['addCustomThumb'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => array('submitOnChange' => true,),
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'customThumb'    => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['customThumb'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => array('fieldType' => 'radio', 'files' => true, 'filesOnly' => true, 'extensions' => 'jpeg,jpg,gif,png,bmp,tiff'),
            'sql'       => "blob NULL",
        ),
        'owner'          => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['owner'],
            'default'    => \BackendUser::getInstance()->id,
            'foreignKey' => 'tl_user.name',
            'inputType'  => 'select',
            'filter'     => true,
            'search'     => true,
            'eval'       => array('includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'clr w50'),
            'sql'        => "int(10) NOT NULL default '0'",
            'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
        ),
        'socialMediaSRC' => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['socialMediaSRC'],
            'exclude'   => true,
            'filter'    => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'localMediaSRC'  => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['localMediaSRC'],
            'exclude'   => true,
            'filter'    => true,
            'search'    => true,
            'inputType' => 'fileTree',
            'eval'      => array('files' => true, 'filesOnly' => true, 'fieldType' => 'radio'),
            'sql'       => "binary(16) NULL",
        ),
        'cssID'          => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['cssID'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => array('multiple' => true, 'size' => 2, 'tl_class' => 'w50 clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
    ),
);
