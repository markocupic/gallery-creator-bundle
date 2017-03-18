<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

use Markocupic\GalleryCreatorBundle\GcHelpers;


$this->import('BackendUser', 'User');

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
            array('tl_gallery_creator_albums', 'onloadCbFileupload'),
            array('tl_gallery_creator_albums', 'onloadCbSetUpPalettes'),
            array('tl_gallery_creator_albums', 'onloadCbImportFromFilesystem'),
            array('tl_gallery_creator_albums', 'isAjaxRequest'),
            array('tl_gallery_creator_albums', 'onloadCbCheckFolderSettings'),
        ),
        'ondelete_callback' => array(
            array('tl_gallery_creator_albums', 'ondeleteCb'),
        ),
        'sql'               => array(
            'keys' => array(
                'id'    => 'primary',
                'pid'   => 'index',
                'alias' => 'index',
            ),
        ),
    ),
    // List
    'list'        => array(
        'sorting'           => array(
            'panelLayout'           => 'limit,sort',
            'mode'                  => 5,
            'paste_button_callback' => array('tl_gallery_creator_albums', 'buttonCbPastePicture'),
        ),
        'label'             => array(
            'fields'         => array('name'),
            'format'         => '<span style="#padding-left#"><a href="#href#" title="#title#"><img src="#icon#"></span> #datum# <span style="color:#b3b3b3; padding-left:3px;">[%s] [#count_pics# images]</span></a>',
            'label_callback' => array('tl_gallery_creator_albums', 'labelCb'),
        ),
        'global_operations' => array(
            'all'           => array(
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
            ),
            'revise_tables' => array(
                'label'      => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['revise_tables'],
                'href'       => 'href is set in $this->setUpPalettes',
                'class'      => 'icon_revise_tables',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
            ),
        ),
        'operations'        => array(
            'edit'          => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['list_pictures'],
                'href'            => 'table=tl_gallery_creator_pictures',
                'icon'            => 'bundles/markocupicgallerycreator/images/text_list_bullets.png',
                'attributes'      => 'class="contextmenu"',
                'button_callback' => array('tl_gallery_creator_albums', 'buttonCbEdit'),
            ),
            'delete'        => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['delete'],
                'href'            => 'act=delete',
                'icon'            => 'delete.gif',
                'attributes'      => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['gcDeleteConfirmAlbum'] . '\')) return false; Backend.getScrollOffset();"',
                'button_callback' => array('tl_gallery_creator_albums', 'buttonCbDelete'),
            ),
            'toggle'        => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['toggle'],
                'icon'            => 'visible.gif',
                'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => array('tl_gallery_creator_albums', 'toggleIcon'),
            ),
            'upload_images' => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['upload_images'],
                'icon'            => 'bundles/markocupicgallerycreator/images/image_add.png',
                'button_callback' => array('tl_gallery_creator_albums', 'buttonCbAddImages'),
            ),
            'import_images' => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['import_images'],
                'icon'            => 'bundles/markocupicgallerycreator/images/folder_picture.png',
                'button_callback' => array('tl_gallery_creator_albums', 'buttonCbImportImages'),
            ),
            'cut'           => array(
                'label'           => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['cut'],
                'href'            => 'act=paste&mode=cut',
                'icon'            => 'cut.gif',
                'attributes'      => 'onclick="Backend.getScrollOffset();"',
                'button_callback' => array('tl_gallery_creator_albums', 'buttonCbCutPicture'),
            ),
        ),
    ),
    // Palettes
    'palettes'    => array(
        '__selector__'    => array('protected'),
        'default'         => '{album_info},published,name,alias,description,keywords,assignedDir,album_info,owner,date,event_location,filePrefix,sortBy,comment,visitors;{album_preview_thumb_legend},thumb;{insert_article},insert_article_pre,insert_article_post;{protection:hide},protected',
        'restricted_user' => '{album_info},link_edit_images,album_info',
        'fileupload'      => '{upload_settings},preserve_filename,img_resolution,img_quality;{uploader_legend},uploader,fileupload',
        'import_images'   => '{upload_settings},preserve_filename,multiSRC',
        'revise_tables'   => '{maintenance},revise_tables',
    ),
    // Subpalettes
    'subpalettes' => array('protected' => 'groups'),
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
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['published'],
            'inputType' => 'checkbox',
            'eval'      => array('submitOnChange' => true),
            'sql'       => "char(1) NOT NULL default '1'",
        ),
        'date'                => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['date'],
            'inputType' => 'text',
            'default'   => time(),
            'eval'      => array('mandatory' => true, 'datepicker' => true, 'rgxp' => 'date', 'tl_class' => 'w50 wizard', 'submitOnChange' => false),
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ),
        'owner'               => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['owner'],
            'default'    => \BackendUser::getInstance()->id,
            'foreignKey' => 'tl_user.name',
            'inputType'  => 'select',
            'eval'       => array('chosen' => true, 'includeBlankOption' => true, 'blankOptionLabel' => 'noName', 'doNotShow' => true, 'nospace' => true, 'tl_class' => 'w50'),
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => array('type' => 'hasOne', 'load' => 'eager'),
        ),
        'assignedDir'         => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['assignedDir'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => array('mandatory' => false, 'fieldType' => 'radio', 'tl_class' => 'clr'),
            'sql'       => "blob NULL",
        ),
        'owners_name'         => array(
            'label'   => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['owners_name'],
            'default' => \BackendUser::getInstance()->name,
            'eval'    => array('doNotShow' => true, 'tl_class' => 'w50 readonly'),
            'sql'     => "text NULL",
        ),
        'event_location'      => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['event_location'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'tl_class' => 'w50', 'submitOnChange' => false),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'name'                => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['name'],
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'tl_class' => 'w50', 'submitOnChange' => false),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'alias'               => array(
            'label'         => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['alias'],
            'inputType'     => 'text',
            'eval'          => array('doNotShow' => false, 'doNotCopy' => true, 'maxlength' => 50, 'tl_class' => 'w50', 'unique' => true),
            'save_callback' => array(array('tl_gallery_creator_albums', 'saveCbGenerateAlias')),
            'sql'           => "varchar(128) COLLATE utf8_bin NOT NULL default ''",
        ),
        'description'         => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['description'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'search'    => true,
            'eval'      => array('style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'),
            'sql'       => "text NULL",
        ),
        'keywords'            => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['keywords'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'search'    => true,
            'eval'      => array('style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'),
            'sql'       => "text NULL",
        ),
        'comment'             => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['comment'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => array('tl_class' => 'clr long', 'style' => 'height:7em;', 'allowHtml' => false, 'submitOnChange' => false, 'wrap' => 'soft'),
            'sql'       => "text NULL",
        ),
        'thumb'               => array(
            'label'                => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['thumb'],
            'inputType'            => 'radio',
            'input_field_callback' => array('tl_gallery_creator_albums', 'inputFieldCbThumb'),
            'eval'                 => array('doNotShow' => true, 'includeBlankOption' => true, 'nospace' => true, 'rgxp' => 'digit', 'maxlength' => 64, 'tl_class' => 'clr', 'submitOnChange' => true),
            'sql'                  => "varchar(255) NOT NULL default ''",
        ),
        'fileupload'          => array(
            'label'                => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['fileupload'],
            'input_field_callback' => array('tl_gallery_creator_albums', 'inputFieldCbGenerateUploaderMarkup'),
            'eval'                 => array('doNotShow' => true),
        ),
        'album_info'          => array(
            'input_field_callback' => array('tl_gallery_creator_albums', 'inputFieldCbGenerateAlbumInformations'),
            'eval'                 => array('doNotShow' => true),
        ),
        // save value in tl_user
        'uploader'            => array(
            'label'         => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['uploader'],
            'default'       => 'be_gc_jumploader',
            'inputType'     => 'select',
            'load_callback' => array(array('tl_gallery_creator_albums', 'loadCbGetUploader')),
            'save_callback' => array(array('tl_gallery_creator_albums', 'saveCbSaveUploader')),
            'options'       => array('be_gc_html5_uploader'),
            'eval'          => array('doNotShow' => true, 'tl_class' => 'clr', 'submitOnChange' => true),
            'sql'           => "varchar(32) NOT NULL default 'be_gc_html5_uploader'",
        ),
        // save value in tl_user
        'img_resolution'      => array(
            'label'         => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['img_resolution'],
            'default'       => '600',
            'inputType'     => 'select',
            'load_callback' => array(array('tl_gallery_creator_albums', 'loadCbGetImageResolution')),
            'save_callback' => array(array('tl_gallery_creator_albums', 'saveCbSaveImageResolution')),
            'options'       => array_merge(array('no_scaling'), range(100, 3500, 50)),
            'reference'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reference'],
            'eval'          => array('doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true),
            'sql'           => "smallint(5) unsigned NOT NULL default '600'",
        ),
        // save value in tl_user
        'img_quality'         => array(
            'label'         => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['img_quality'],
            'default'       => '100',
            'inputType'     => 'select',
            'load_callback' => array(array('tl_gallery_creator_albums', 'loadCbGetImageQuality')),
            'save_callback' => array(array('tl_gallery_creator_albums', 'saveCbSaveImageQuality')),
            'options'       => range(10, 100, 10),
            'eval'          => array('doNotShow' => true, 'tl_class' => 'w50', 'submitOnChange' => true),
            'sql'           => "smallint(3) unsigned NOT NULL default '100'",
        ),
        'preserve_filename'   => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['preserve_filename'],
            'inputType' => 'checkbox',
            'default'   => true,
            'eval'      => array('doNotShow' => true, 'submitOnChange' => true),
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'multiSRC'            => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_content']['multiSRC'],
            'exclude'   => true,
            'inputType' => 'fileTree',
            'eval'      => array('doNotShow' => true, 'multiple' => true, 'fieldType' => 'checkbox', 'files' => true, 'mandatory' => true),
            'sql'       => "blob NULL",
        ),
        'protected'           => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['protected'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => array('doNotShow' => true, 'submitOnChange' => true, 'tl_class' => 'clr'),
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'groups'              => array(
            'label'      => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['groups'],
            'inputType'  => 'checkbox',
            'foreignKey' => 'tl_member_group.name',
            'eval'       => array('doNotShow' => true, 'mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'),
            'sql'        => "blob NULL",
        ),
        'insert_article_pre'  => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['insert_article_pre'],
            'inputType' => 'text',
            'eval'      => array('doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50',),
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ),
        'insert_article_post' => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['insert_article_post'],
            'inputType' => 'text',
            'eval'      => array('doNotShow' => false, 'rgxp' => 'digit', 'tl_class' => 'w50',),
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ),
        'revise_tables'       => array(
            'input_field_callback' => array('tl_gallery_creator_albums', 'inputFieldCbCleanDb'),
            'eval'                 => array('doNotShow' => true),
        ),
        'visitors_details'    => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['visitors_details'],
            'inputType' => 'textarea',
            'sql'       => "blob NULL",
        ),
        'visitors'            => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['visitors'],
            'inputType' => 'text',
            'eval'      => array('maxlength' => 10, 'tl_class' => 'w50', 'rgxp' => 'digit'),
            'sql'       => "int(10) unsigned NOT NULL default '0'",
        ),
        'sortBy'              => array(
            'label'         => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['sortBy'],
            'exclude'       => true,
            'save_callback' => array(array('tl_gallery_creator_albums', 'saveCbSortAlbum')),
            'inputType'     => 'select',
            'default'       => 'custom',
            'options'       => array('----', 'name_asc', 'name_desc', 'date_asc', 'date_desc'),
            'reference'     => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums'],
            'eval'          => array('chosen' => true, 'submitOnChange' => true, 'tl_class' => 'w50'),
            'sql'           => "varchar(32) NOT NULL default ''",
        ),
        'filePrefix'          => array(
            'label'         => &$GLOBALS['TL_LANG']['tl_gallery_creator_albums']['filePrefix'],
            'exclude'       => true,
            'inputType'     => 'text',
            'save_callback' => array(array('tl_gallery_creator_albums', 'saveCbValidateFileprefix')),
            'eval'          => array('mandatory' => false, 'tl_class' => 'clr', 'rgxp' => 'alnum', 'nospace' => true),
            'sql'           => "varchar(32) NOT NULL default ''",
        ),
    ),
);


/**
 * Class tl_gallery_creator_albums
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @copyright  Marko Cupic
 * @author     Marko Cupic
 * @package    GalleryCreator
 */
class tl_gallery_creator_albums extends Backend
{

    public $restrictedUser = false;

    /**
     *  Pfad ab TL_ROOT ins Bildverzeichnis
     *
     * @var string
     */
    public $uploadPath;

    public function __construct()
    {

        parent::__construct();
        $this->import('BackendUser', 'User');
        $this->import('Files');

        // path to the gallery_creator upload-directory
        $this->uploadPath = GALLERY_CREATOR_UPLOAD_PATH;

        // register the parseBackendTemplate Hook
        $GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = array(
            'tl_gallery_creator_albums',
            'myParseBackendTemplate',
        );

        if ($_SESSION['BE_DATA']['CLIPBOARD']['tl_gallery_creator_albums']['mode'] == 'copyAll')
        {
            $this->redirect('contao/main.php?do=gallery_creator&clipboard=1');
        }

    }

    /**
     * Return the add-images-button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function buttonCbAddImages($row, $href, $label, $title, $icon, $attributes)
    {

        $href = $href . 'id=' . $row['id'] . '&act=edit&table=tl_gallery_creator_albums&mode=fileupload';

        return '<a href="' . $this->addToUrl($href) . '" title="' . specialchars($title) . '"' . $attributes . ' style="margin-right:5px">' . Image::getHtml($icon, $label) . '</a>';
    }

    /**
     * Return the "toggle visibility" button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {

        if (strlen(Input::get('tid')))
        {
            $this->toggleVisibility(Input::get('tid'), (Input::get('state') == 1));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->isAdmin && $row['owner'] != $this->User->id && !$GLOBALS['TL_CONFIG']['gc_disable_backend_edit_protection'])
        {
            return '';
        }

        $href .= '&amp;tid=' . $row['id'] . '&amp;state=' . ($row['published'] ? '' : 1);

        if (!$row['published'])
        {
            $icon = 'invisible.gif';
        }

        $this->Database->prepare("SELECT * FROM tl_gallery_creator_albums WHERE id=?")->limit(1)->execute($row['id']);

        if (!$this->User->isAdmin && $row['owner'] != $this->User->id && !$GLOBALS['TL_CONFIG']['gc_disable_backend_edit_protection'])
        {
            return Image::getHtml($icon) . ' ';
        }

        return '<a href="' . $this->addToUrl($href) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';
    }

    /**
     * toggle visibility of a certain album
     *
     * @param integer
     * @param boolean
     */
    public function toggleVisibility($intId, $blnVisible)
    {


        $objAlbum = GalleryCreatorAlbumsModel::findByPk($intId);

        // Check permissions to publish
        if (!$this->User->isAdmin && $objAlbum->owner != $this->User->id && !$GLOBALS['TL_CONFIG']['gc_disable_backend_edit_protection'])
        {
            $this->log('Not enough permissions to publish/unpublish tl_gallery_creator_albums ID "' . $intId . '"', __METHOD__, TL_ERROR);
            $this->redirect('contao/main.php?act=error');
        }

        $objVersions = new Versions('tl_gallery_creator_albums', $intId);
        $objVersions->initialize();

        // Trigger the save_callback
        if (is_array($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['published']['save_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['published']['save_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $blnVisible = $this->$callback[0]->$callback[1]($blnVisible, $this);
                }
                elseif (is_callable($callback))
                {
                    $blnVisible = $callback($blnVisible, $this);
                }
            }
        }

        // Update the database
        $this->Database->prepare("UPDATE tl_gallery_creator_albums SET tstamp=" . time() . ", published='" . ($blnVisible ? 1 : '') . "' WHERE id=?")->execute($intId);

        $objVersions->create();
        $this->log('A new version of record "tl_gallery_creator_albums.id=' . $intId . '" has been created.', __METHOD__, TL_GENERAL);
    }

    /**
     * Return the cut-picture-button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function buttonCbCutPicture($row, $href, $label, $title, $icon, $attributes)
    {

        // enable cutting albums to album-owners and admins only
        return (($this->User->id == $row['owner'] || $this->User->isAdmin || $GLOBALS['TL_CONFIG']['gc_disable_backend_edit_protection']) ? ' <a href="' . $this->addToUrl($href . '&id=' . $row['id']) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : ' ' . Image::getHtml(preg_replace('/\.gif$/i', '_.gif', $icon)) . ' ');
    }

    /**
     * Return the delete-button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function buttonCbDelete($row, $href, $label, $title, $icon, $attributes)
    {

        // enable deleting albums to album-owners and admins only
        return ($this->User->isAdmin || $this->User->id == $row['owner'] || $GLOBALS['TL_CONFIG']['gc_disable_backend_edit_protection']) ? '<a href="' . $this->addToUrl($href . '&id=' . $row['id']) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml(preg_replace('/\.gif$/i', '_.gif', $icon)) . ' ';
    }

    /**
     * Return the edit-button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function buttonCbEdit($row, $href, $label, $title, $icon, $attributes)
    {

        return '<a href="' . $this->addToUrl($href . '&id=' . $row['id'], 1) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';
    }

    /**
     * Return the import-images button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function buttonCbImportImages($row, $href, $label, $title, $icon, $attributes)
    {

        $href = $href . 'id=' . $row['id'] . '&act=edit&table=tl_gallery_creator_albums&mode=import_images';

        return '<a href="' . $this->addToUrl($href) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a>';
    }

    /**
     * Return the paste-picture-button
     *
     * @param \Contao\DataContainer $dc
     * @param $row
     * @param $table
     * @param $cr
     * @param bool $arrClipboard
     * @return string
     */
    public function buttonCbPastePicture(\Contao\DataContainer $dc, $row, $table, $cr, $arrClipboard = false)
    {

        $disablePA = false;
        $disablePI = false;
        // Disable all buttons if there is a circular reference
        if ($this->User->isAdmin && $arrClipboard !== false && ($arrClipboard['mode'] == 'cut' && ($cr == 1 || $arrClipboard['id'] == $row['id']) || $arrClipboard['mode'] == 'cutAll' && ($cr == 1 || in_array($row['id'], $arrClipboard['id']))))
        {
            $disablePA = true;
            $disablePI = true;
        }
        // Return the buttons
        $imagePasteAfter = Image::getHtml('pasteafter.gif', sprintf($GLOBALS['TL_LANG'][$table]['pasteafter'][1], $row['id']), 'class="blink"');
        $imagePasteInto = Image::getHtml('pasteinto.gif', sprintf($GLOBALS['TL_LANG'][$table]['pasteinto'][1], $row['id']), 'class="blink"');

        if ($row['id'] > 0)
        {
            $return = $disablePA ? Image::getHtml('pasteafter_.gif', '', 'class="blink"') . ' ' : '<a href="' . $this->addToUrl('act=' . $arrClipboard['mode'] . '&mode=1&pid=' . $row['id'] . (!is_array($arrClipboard['id']) ? '&id=' . $arrClipboard['id'] : '')) . '" title="' . specialchars(sprintf($GLOBALS['TL_LANG'][$table]['pasteafter'][1], $row['id'])) . '" onclick="Backend.getScrollOffset();">' . $imagePasteAfter . '</a> ';
        }

        return $return . ($disablePI ? Image::getHtml('pasteinto_.gif', '', 'class="blink"') . ' ' : '<a href="' . $this->addToUrl('act=' . $arrClipboard['mode'] . '&mode=2&pid=' . $row['id'] . (!is_array($arrClipboard['id']) ? '&id=' . $arrClipboard['id'] : '')) . '" title="' . specialchars(sprintf($GLOBALS['TL_LANG'][$table]['pasteinto'][1], $row['id'])) . '" onclick="Backend.getScrollOffset();">' . $imagePasteInto . '</a> ');
    }

    /**
     * Checks if the current user obtains full rights or only restricted rights on the selected album
     */
    public function checkUserRole($albumId)
    {

        $objAlbum = GalleryCreatorAlbumsModel::findByPk($albumId);
        if ($this->User->isAdmin || $GLOBALS['TL_CONFIG']['gc_disable_backend_edit_protection'])
        {
            $this->restrictedUser = false;
            return;
        }
        if ($objAlbum->owner != $this->User->id)
        {
            $this->restrictedUser = true;

            return;
        }
        // ...so the current user is the album owner
        $this->restrictedUser = false;
    }


    /**
     * return the album upload path
     *
     * @return string
     */
    public static function getUplaodPath()
    {

        return self::uploadPath;
    }

    /**
     * Input-field-callback
     * return the html
     * @return string
     */
    public function inputFieldCbCleanDb()
    {

        $output = '
<div class="revise_tables">
<br><br>
       	<input type="checkbox" name="revise_tables">
		<label for="revise_tables">' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['messages']['revise_database'] . '</label>
</div>
			';

        return $output;
    }

    /**
     * Input-field-callback
     * return the html-table with the album-information for restricted users
     * @return string
     */
    public function inputFieldCbGenerateAlbumInformations()
    {

        $objAlb = GalleryCreatorAlbumsModel::findByPk(Input::get('id'));
        $objUser = \Contao\UserModel::findByPk($objAlb->owner);
        $owner = $objUser === null ? 'no-name' : $objUser->name;
        // check User Role
        $this->checkUserRole(Input::get('id'));
        if (false == $this->restrictedUser)
        {
            $output = '
<div class="widget long album_infos">
<br /><br />
<table cellpadding="0" cellspacing="0" width="100%" summary="">
	<tr class="odd">
		<td style="width:25%"><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['id'][0] . ': </strong></td>
		<td>' . $objAlb->id . '</td>
	</tr>
</table>
</div>
				';

            return $output;
        }
        else
        {
            $output = '
<div class="album_infos">
<table cellpadding="0" cellspacing="0" width="100%" summary="">
	<tr class="odd">
		<td style="width:25%"><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['id'][0] . ': </strong></td>
		<td>' . $objAlb->id . '</td>
	</tr>
	<tr>
		<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['date'][0] . ': </strong></td>
		<td>' . Date::parse("Y-m-d", $objAlb->date) . '</td>
	</tr>
	<tr class="odd">
		<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['owners_name'][0] . ': </strong></td>
		<td>' . $owner . '</td>
	</tr>
	<tr>
		<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['name'][0] . ': </strong></td>
		<td>' . $objAlb->name . '</td>
	</tr>

	<tr class="odd">
		<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['comment'][0] . ': </strong></td>
		<td>' . $objAlb->comment . '</td>
	</tr>
	<tr>
		<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['thumb'][0] . ': </strong></td>
		<td>' . $objAlb->thumb . '</td>
	</tr>
</table>
</div>
		';

            return $output;
        }
    }

    /**
     * Input Field Callback for fileupload
     * return the markup for the fileuploader
     *
     * @return string
     */
    public function inputFieldCbGenerateUploaderMarkup()
    {

        return GcHelpers::generateUploader($this->User->gc_be_uploader_template);
    }

    /**
     * handle ajax requests
     */
    public function isAjaxRequest()
    {

        if (Input::get('isAjaxRequest'))
        {
            // change sorting value
            if (Input::get('pictureSorting'))
            {
                $sorting = 10;
                foreach (explode(',', Input::get('pictureSorting')) as $pictureId)
                {
                    $objPicture = GalleryCreatorPicturesModel::findByPk($pictureId);
                    if ($objPicture !== null)
                    {
                        $objPicture->sorting = $sorting;
                        $objPicture->save();
                        $sorting += 10;
                    }
                }
                exit();
            }

            // revise table in the backend
            if (Input::get('checkTables'))
            {
                if (Input::get('getAlbumIDS'))
                {
                    $arrIds = [];
                    $objDb = $this->Database->execute("SELECT id FROM tl_gallery_creator_albums ORDER BY RAND()");
                    while ($objDb->next())
                    {
                        $arrIds[] = $objDb->id;
                    }

                    echo json_encode(array('albumIDS' => $arrIds));
                    exit();
                }

                if (Input::get('albumId'))
                {
                    $albumId = Input::get('albumId');

                    if (Input::get('reviseTables') && $this->User->isAdmin)
                    {
                        // delete damaged datarecords
                        GcHelpers::reviseTables($albumId, true);
                        $response = true;
                    }
                    else
                    {
                        GcHelpers::reviseTables($albumId, false);
                        $response = true;

                    }
                    if ($response === true)
                    {
                        if (is_array($_SESSION['GC_ERROR']))
                        {
                            if (count($_SESSION['GC_ERROR']) > 0)
                            {
                                $strError = implode('***', $_SESSION['GC_ERROR']);
                                if ($strError != '')
                                {
                                    echo json_encode(array('errors' => $strError));
                                }
                            }
                        }
                    }

                    unset($_SESSION['GC_ERROR']);
                    exit();
                }

            }
        }
    }

    /**
     * check if album has subalbums
     * @param integer
     * @return bool
     */
    private function isNode($id)
    {

        $objAlbums = GalleryCreatorAlbumsModel::findByPid($id);
        if ($objAlbums !== null)
        {
            return true;
        }

        return false;
    }

    /**
     * label-callback for the albumlisting
     * @param array
     * @param string
     * @return string
     */
    public function labelCb($row, $label)
    {

        $mysql = $this->Database->prepare('SELECT count(id) as countImg FROM tl_gallery_creator_pictures WHERE pid=?')->execute($row['id']);
        $label = str_replace('#count_pics#', $mysql->countImg, $label);
        $label = str_replace('#datum#', date('Y-m-d', $row['date']), $label);
        $image = $row['published'] ? 'picture_edit.png' : 'picture_edit_1.png';
        $label = str_replace('#icon#', "bundles/markocupicgallerycreator/images/" . $image, $label);
        $href = sprintf("contao/main.php?do=gallery_creator&table=tl_gallery_creator_albums&id=%s&act=edit&rt=%s&ref=%s", $row['id'], REQUEST_TOKEN, TL_REFERER_ID);
        $label = str_replace('#href#', $href, $label);
        $label = str_replace('#title#', sprintf($GLOBALS['TL_LANG']['tl_gallery_creator_albums']['edit_album'][1], $row['id']), $label);
        $level = GcHelpers::getAlbumLevel($row["pid"]);
        $padding = $this->isNode($row["id"]) ? 3 * $level : 20 + (3 * $level);
        $label = str_replace('#padding-left#', 'padding-left:' . $padding . 'px;', $label);

        return $label;
    }

    /**
     * load-callback for uploader type
     * @return string
     */
    public function loadCbGetUploader()
    {

        return $this->User->gc_be_uploader_template;
    }

    /**
     * load-callback for image-quality
     * @return string
     */
    public function loadCbGetImageQuality()
    {

        return $this->User->gc_img_quality;
    }

    /**
     * load-callback for image-resolution
     * @return string
     */
    public function loadCbGetImageResolution()
    {

        return $this->User->gc_img_resolution;
    }

    /**
     * Parse Backend Template Hook
     * @param string
     * @param string
     * @return string
     */
    public function myParseBackendTemplate($strContent, $strTemplate)
    {

        if (Input::get('mode') == 'revise_tables')
        {
            // remove buttons

            $strContent = preg_replace('/<button type=\"submit\" name=\"saveNclose\"((\r|\n|.)+?)<\/button>/', '', $strContent);
            $strContent = preg_replace('/<button type=\"submit\" name=\"saveNcreate\"((\r|\n|.)+?)<\/button>/', '', $strContent);
            $strContent = preg_replace('/<button type=\"submit\" name=\"save\"((\r|\n|.)+?)>((\r|\n|.)+?)<\/button>/', '<button type="submit" name="save" id="reviseTableBtn" class="tl_submit" accesskey="s">' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reviseTablesBtn'][0] . '</button>', $strContent);


        }

        if (Input::get('act') == 'select')
        {
            // remove buttons
            if (Input::get('table') != 'tl_gallery_creator_pictures')
            {

                //$strContent = preg_replace('/<button type=\"submit\" name=\"delete\"((\r|\n|.)+?)<\/button>/', '', $strContent);
                //$strContent = preg_replace('/<button type=\"submit\" name=\"cut\"((\r|\n|.)+?)<\/button>/', '', $strContent);
                //$strContent = preg_replace('/<button type=\"submit\" name=\"copy\"((\r|\n|.)+?)<\/button>/', '', $strContent);
            }
        }

        if (Input::get('mode') == 'fileupload')
        {
            // form encode
            $strContent = str_replace('application/x-www-form-urlencoded', 'multipart/form-data', $strContent);
            // remove buttons
            $strContent = preg_replace('/<div class=\"split-button((\r|\n|.)+?)<\/div>/', '', $strContent);
            $strContent = preg_replace('/<button type=\"submit\" name=\"save\"((\r|\n|.)+?)<\/button>/', '', $strContent);
        }
        if (Input::get('mode') == 'import_images')
        {


            $strContent = preg_replace('/<button type=\"submit\" name=\"saveNclose\"((\r|\n|.)+?)<\/button>/', '', $strContent);
            $strContent = preg_replace('/<button type=\"submit\" name=\"saveNcreate\"((\r|\n|.)+?)<\/button>/', '', $strContent);
            $strContent = preg_replace('/<button type=\"submit\" name=\"uploadNback\"((\r|\n|.)+?)<\/button>/', '', $strContent);

            // Disable split-button
            $strContent = preg_replace('/<div class="split-button((\r|\n|.)+?)<\/div>/', '', $strContent);


        }

        return $strContent;
    }

    /**
     * on-delete-callback
     */
    public function ondeleteCb(\DataContainer $dc)
    {
        if (Input::get('act') != 'deleteAll')
        {
            $this->checkUserRole($dc->id);
            if ($this->restrictedUser)
            {
                $this->log('Datensatz mit ID ' . Input::get('id') . ' wurde von einem nicht authorisierten Benutzer versucht aus tl_gallery_creator_albums zu loeschen.', __METHOD__, TL_ERROR);
                $this->redirect('contao/main.php?do=error');
            }
            // also delete the child element
            $arrDeletedAlbums = GalleryCreatorAlbumsModel::getChildAlbums(Input::get('id'));
            $arrDeletedAlbums = array_merge(array(Input::get('id')), $arrDeletedAlbums);
            foreach ($arrDeletedAlbums as $idDelAlbum)
            {
                $objAlbumModel = GalleryCreatorAlbumsModel::findByPk($idDelAlbum);
                if ($objAlbumModel === null)
                {
                    continue;
                }
                if ($this->User->isAdmin || $objAlbumModel->owner == $this->User->id || $GLOBALS['TL_CONFIG']['gc_disable_backend_edit_protection'])
                {
                    // remove all pictures from tl_gallery_creator_pictures
                    $objPicturesModel = GalleryCreatorPicturesModel::findByPid($idDelAlbum);
                    if ($objPicturesModel !== null)
                    {
                        while ($objPicturesModel->next())
                        {
                            $fileUuid = $objPicturesModel->uuid;
                            $objPicturesModel->delete();
                            $objPicture = GalleryCreatorPicturesModel::findByUuid($fileUuid);
                            if ($objPicture === null)
                            {
                                $oFile = FilesModel::findByUuid($fileUuid);
                                if ($oFile !== null)
                                {
                                    $file = new File($oFile->path);
                                    $file->delete();
                                }
                            }
                        }
                    }
                    // remove the albums from tl_gallery_creator_albums
                    // remove the directory from the filesystem
                    $oFolder = FilesModel::findByUuid($objAlbumModel->assignedDir);
                    if ($oFolder !== null)
                    {
                        $folder = new Folder($oFolder->path, true);
                        if ($folder->isEmpty())
                        {
                            $folder->delete();
                        }
                    }
                    $objAlbumModel->delete();
                }
                else
                {
                    // do not delete childalbums, which the user does not owns
                    $this->Database->prepare('UPDATE tl_gallery_creator_albums SET pid=? WHERE id=?')->execute('0', $idDelAlbum);
                }
            }
        }
    }

    /**
     * onload_callback
     * checks availability of the upload-folder
     */
    public function onloadCbCheckFolderSettings(Contao\DC_Table $dc)
    {
        // create the upload directory if it doesn't already exists
        $objFolder = new Folder($this->uploadPath);
        $objFolder->unprotect();
        Dbafs::addResource($this->uploadPath, false);
        if (!is_writable(TL_ROOT . '/' . $this->uploadPath))
        {
            $_SESSION['TL_ERROR'][] = sprintf($GLOBALS['TL_LANG']['ERR']['dirNotWriteable'], $this->uploadPath);
        }
    }

    /**
     * onload-callback
     * initiate the fileupload
     */
    public function onloadCbFileupload()
    {

        if (Input::get('mode') != 'fileupload')
        {
            return;
        }

        // Load language file
        $this->loadLanguageFile('tl_files');

        // Album ID
        $intAlbumId = Input::get('id');

        // Save uploaded files in $_FILES['file']
        $strName = 'file';

        // Get the album object
        $blnNoAlbum = false;
        $objAlb = GalleryCreatorAlbumsModel::findById($intAlbumId);
        if ($objAlb === null)
        {
            Message::addError('Album with ID ' . $intAlbumId . ' does not exist.');
            $blnNoAlbum = true;
        }

        // Check for a valid upload directory
        $blnNoUploadDir = false;
        $objUploadDir = FilesModel::findByUuid($objAlb->assignedDir);
        if ($objUploadDir === null || !is_dir(TL_ROOT . '/' . $objUploadDir->path))
        {
            Message::addError('No upload directory defined in the album settings!');
            $blnNoUploadDir = true;
        }

        // Exit if there is no upload or the upload directory is missing
        if (!is_array($_FILES[$strName]) || $blnNoUploadDir || $blnNoAlbum)
        {
            return;
        }
        // Call the uploader script
        $arrUpload = GcHelpers::fileupload($intAlbumId, $strName);

        foreach ($arrUpload as $strFileSrc)
        {
            // Add  new datarecords into tl_gallery_creator_pictures
            GcHelpers::createNewImage($objAlb->id, $strFileSrc);
        }

        // Do not exit script if html5_uploader is selected and Javascript is disabled
        if (!Input::post('submit'))
        {
            exit;
        }

    }


    /**
     * onload-callback
     * import images from an external directory to an existing album
     */
    public function onloadCbImportFromFilesystem()
    {

        if (Input::get('mode') != 'import_images')
        {
            return;
        }
        // load language file
        $this->loadLanguageFile('tl_content');
        if (!$this->Input->post('FORM_SUBMIT'))
        {
            return;
        }
        $intAlbumId = Input::get('id');

        $objAlbum = \GalleryCreatorAlbumsModel::findByPk($intAlbumId);
        if ($objAlbum !== null)
        {
            $objAlbum->preserve_filename = Input::post('preserve_filename');
            $objAlbum->save();
            // comma separated list with folder uuid's => 10585872-5f1f-11e3-858a-0025900957c8,105e9de0-5f1f-11e3-858a-0025900957c8,105e9dd6-5f1f-11e3-858a-0025900957c8
            $strMultiSRC = $this->Input->post('multiSRC');
            if (strlen(trim($strMultiSRC)))
            {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['preserve_filename']['eval']['submitOnChange'] = false;
                // import Images from filesystem and write entries to tl_gallery_creator_pictures
                GcHelpers::importFromFilesystem($intAlbumId, $strMultiSRC);
                $this->redirect('contao/main.php?do=gallery_creator&table=tl_gallery_creator_pictures&id=' . $intAlbumId . '&ref=' . TL_REFERER_ID . '&filesImported=true');
            }
        }
        $this->redirect('contao/main.php?do=gallery_creator');


    }


    /**
     * onload-callback
     * create the palette
     */
    public function onloadCbSetUpPalettes()
    {

        // global_operations for admin only
        if (!$this->User->isAdmin)
        {
            unset($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['list']['global_operations']['all']);
            unset($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['list']['global_operations']['revise_tables']);
        }
        // for security reasons give only readonly rights to these fields
        $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['id']['eval']['style'] = '" readonly="readonly';
        $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['owners_name']['eval']['style'] = '" readonly="readonly';
        // create the jumploader palette
        if (Input::get('mode') == 'fileupload')
        {
            if ($this->User->gc_img_resolution == 'no_scaling')
            {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['fileupload'] = str_replace(',img_quality', '', $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['fileupload']);
            }
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['fileupload'];

            return;
        }
        // create the import_images palette
        if (Input::get('mode') == 'import_images')
        {
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['import_images'];
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['preserve_filename']['eval']['submitOnChange'] = false;

            return;
        }
        // the palette for admins
        if ($this->User->isAdmin)
        {
            $objAlb = $this->Database->prepare('SELECT id FROM tl_gallery_creator_albums')->limit(1)->execute();
            if ($objAlb->next())
            {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['list']['global_operations']['revise_tables']['href'] = 'act=edit&table&mode=revise_tables&id=' . $objAlb->id;
            }
            else
            {
                unset($GLOBALS['TL_DCA']['tl_gallery_creator_albums']['list']['global_operations']['revise_tables']);
            }
            if (Input::get('mode') == 'revise_tables')
            {
                $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['revise_tables'];

                return;
            }
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['owner']['eval']['doNotShow'] = false;
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['protected']['eval']['doNotShow'] = false;
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['fields']['groups']['eval']['doNotShow'] = false;

            return;
        }
        $objAlb = $this->Database->prepare('SELECT id, owner FROM tl_gallery_creator_albums WHERE id=?')->execute(Input::get('id'));
        // only adminstrators and album-owners obtains writing-access for these fields
        $this->checkUserRole(Input::get('id'));
        if ($objAlb->owner != $this->User->id && true == $this->restrictedUser)
        {
            $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_albums']['palettes']['restricted_user'];
        }
    }


    /**
     * Input field callback for the album preview thumb select
     * list each image of the album (and child-albums)
     * @return string
     */
    public function inputFieldCbThumb()
    {

        $objAlbum = GalleryCreatorAlbumsModel::findByPk(Input::get('id'));

        // Save input
        if (Input::post('FORM_SUBMIT') == 'tl_gallery_creator_albums')
        {
            if (Input::post('thumb') == intval(Input::post('thumb')))
            {
                $objAlbum->thumb = Input::post('thumb');
                $objAlbum->save();
            }
        }

        // Generate picture list
        $html = '<div class="widget long preview_thumb">';
        $html .= '<h3><label for="ctrl_thumb">' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['thumb']['0'] . '</label></h3>';
        $html .= '<p>' . $GLOBALS['TL_LANG']['MSC']['dragItemsHint'] . '</p>';

        $html .= '<ul id="previewThumbList">';

        $objPicture = $this->Database->prepare('SELECT * FROM tl_gallery_creator_pictures WHERE pid=? ORDER BY sorting')->execute(Input::get('id'));
        $arrData = [];
        while ($objPicture->next())
        {
            $arrData[] = array('uuid' => $objPicture->uuid, 'id' => $objPicture->id);
        }
        // Get all child albums
        $arrSubalbums = GalleryCreatorAlbumsModel::getChildAlbums(Input::get('id'));
        if (count($arrSubalbums))
        {
            $arrData[] = array('uuid' => 'beginn_childalbums', 'id' => '');
            $objPicture = $this->Database->execute("SELECT * FROM tl_gallery_creator_pictures WHERE pid IN (" . implode(',', $arrSubalbums) . ") ORDER BY id");
            while ($objPicture->next())
            {
                $arrData[] = array('uuid' => $objPicture->uuid, 'id' => $objPicture->id);
            }
        }

        foreach ($arrData as $arrItem)
        {
            $uuid = $arrItem['uuid'];
            $id = $arrItem['id'];

            if ($uuid == 'beginn_childalbums')
            {
                $html .= '</ul><ul id="childAlbumsList">';
                continue;
            }
            $objFileModel = FilesModel::findByUuid($uuid);
            if ($objFileModel !== null)
            {
                if (file_exists(TL_ROOT . '/' . $objFileModel->path))
                {
                    $objFile = new \File($objFileModel->path);
                    $src = 'placeholder.png';
                    if ($objFile->height <= $GLOBALS['TL_CONFIG']['gdMaxImgHeight'] && $objFile->width <= $GLOBALS['TL_CONFIG']['gdMaxImgWidth'])
                    {
                        $src = Image::get($objFile->path, 80, 60, 'center_center');
                    }
                    $checked = $objAlbum->thumb == $id ? ' checked' : '';
                    $class = $checked != '' ? ' class="checked"' : '';
                    $html .= '<li' . $class . ' data-id="' . $id . '" title="' . specialchars($objFile->name) . '"><input type="radio" name="thumb" value="' . $id . '"' . $checked . '>' . \Image::getHtml($src, $objFile->name) . '</li>' . "\r\n";
                }
            }
        }

        $html .= '</ul>';
        $html .= '</div>';

        // Add javascript
        $script = '
<script>
	window.addEvent("domready", function() {
		$$(".preview_thumb input").addEvent("click", function(){
		    $$(".preview_thumb li").removeClass("checked");
		    this.getParent("li").addClass("checked");
		});

		/** sort album with drag and drop */
		new Sortables("#previewThumbList", {
            onComplete: function(){
                var ids = [];
                $$("#previewThumbList > li").each(function(el){
                    ids.push(el.getProperty("data-id"));
                });
                // ajax request
                if(ids.length > 0){
                    var myRequest = new Request({
                    url: document.URL + "&isAjaxRequest=true&pictureSorting=" + ids.join(),
                    method: "get"
                });
                // fire request (resort album)
                myRequest.send();
                }
            }
		});
	});
</script>
';

        // Return html
        return $html . $script;
    }

    /**
     * @param $strPrefix
     * @param \Contao\DataContainer $dc
     * @return string
     */
    public function saveCbValidateFilePrefix($strPrefix, \Contao\DataContainer $dc)
    {
        $i = 0;
        if ($strPrefix != '')
        {
            // >= php ver 5.4
            $transliterator = Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;', Transliterator::FORWARD);
            $strPrefix = $transliterator->transliterate($strPrefix);
            $strPrefix = str_replace('.', '_', $strPrefix);

            $arrOptions = array(
                'column' => array('tl_gallery_creator_pictures.pid=?'),
                'value'  => array($dc->id),
                'order'  => 'sorting ASC',
            );
            $objPicture = Contao\GalleryCreatorPicturesModel::findAll($arrOptions);
            if ($objPicture !== null)
            {
                while ($objPicture->next())
                {
                    $objFile = \FilesModel::findOneByUuid($objPicture->uuid);
                    if ($objFile !== null)
                    {
                        if (is_file(TL_ROOT . '/' . $objFile->path))
                        {
                            $oFile = new File($objFile->path);
                            $i++;
                            while (is_file($oFile->dirname . '/' . $strPrefix . '_' . $i . '.' . strtolower($oFile->extension)))
                            {
                                $i++;
                            }
                            $oldPath = $oFile->dirname . '/' . $strPrefix . '_' . $i . '.' . strtolower($oFile->extension);
                            $newPath = str_replace(TL_ROOT . '/', '', $oldPath);
                            // rename file
                            if ($oFile->renameTo($newPath))
                            {
                                $objPicture->path = $oFile->path;
                                $objPicture->save();
                                \Message::addInfo(sprintf('Picture with ID %s has been renamed to %s.', $objPicture->id, $newPath));
                            }
                        }
                    }
                }
                // Purge Image Cache to
                $objAutomator = new \Automator();
                $objAutomator->purgeImageCache();
            }
        }
        return '';
    }


    /**
     * sortBy  - save_callback
     * @param $varValue
     * @param \Contao\DataContainer $dc
     * @return string
     */
    public function saveCbSortAlbum($varValue, \Contao\DataContainer $dc)
    {

        if ($varValue == '----')
        {
            return $varValue;
        }

        $objPictures = GalleryCreatorPicturesModel::findByPid($dc->id);
        if ($objPictures === null)
        {
            return '----';
        }

        $files = [];
        $auxDate = [];

        while ($objPictures->next())
        {
            $oFile = FilesModel::findByUuid($objPictures->uuid);
            $objFile = new \File($oFile->path, true);
            $files[$oFile->path] = array(
                'id' => $objPictures->id,
            );
            $auxDate[] = $objFile->mtime;
        }

        switch ($varValue)
        {
            case '----':
                break;
            case 'name_asc':
                uksort($files, 'basename_natcasecmp');
                break;
            case 'name_desc':
                uksort($files, 'basename_natcasercmp');
                break;
            case 'date_asc':
                array_multisort($files, SORT_NUMERIC, $auxDate, SORT_ASC);
                break;

            case 'date_desc':
                array_multisort($files, SORT_NUMERIC, $auxDate, SORT_DESC);
                break;
        }

        $sorting = 0;
        foreach ($files as $arrFile)
        {
            $sorting += 10;
            $objPicture = GalleryCreatorPicturesModel::findByPk($arrFile['id']);
            $objPicture->sorting = $sorting;
            $objPicture->save();
        }

        // return default value
        return '----';
    }

    /**
     * generate an albumalias based on the albumname and create a directory of the same name
     * and register the directory in tl files
     * @param $strAlias
     * @param \Contao\DataContainer $dc
     * @return mixed|string
     */
    public function saveCbGenerateAlias($strAlias, \Contao\DataContainer $dc)
    {
        $blnDoNotCreateDir = false;

        // get current row
        $objAlbum = GalleryCreatorAlbumsModel::findByPk($dc->id);
        if ($objAlbum === null)
        {
            return;
        }

        // Save assigned Dir if it was defined.
        if ($this->Input->post('FORM_SUBMIT') && strlen($this->Input->post('assignedDir')))
        {
            $objAlbum->assignedDir = $this->Input->post('assignedDir');
            $objAlbum->save();
            $blnDoNotCreateDir = true;
        }

        $strAlias = standardize($strAlias);
        // if there isn't an existing albumalias generate one from the albumname
        if (!strlen($strAlias))
        {
            $strAlias = standardize($dc->activeRecord->name);
        }

        // limit alias to 50 characters
        $strAlias = substr($strAlias, 0, 43);
        // remove invalid characters
        $strAlias = preg_replace("/[^a-z0-9\_\-]/", "", $strAlias);
        // if alias already exists add the album-id to the alias
        $objAlb = $this->Database->prepare('SELECT * FROM tl_gallery_creator_albums WHERE id!=? AND alias=?')->execute($dc->activeRecord->id, $strAlias);
        if ($objAlb->numRows)
        {
            $strAlias = 'id-' . $dc->id . '-' . $strAlias;
        }

        // Create default upload folder
        if ($blnDoNotCreateDir === false)
        {
            // create the new folder and register it in tl_files
            $objFolder = new Folder ($this->uploadPath . '/' . $strAlias);
            $objFolder->unprotect();
            $oFolder = Dbafs::addResource($objFolder->path, true);
            $objAlbum->assignedDir = $oFolder->uuid;
            $objAlbum->save();
            // Important
            Input::setPost('assignedDir', \StringUtil::binToUuid($objAlbum->assignedDir));

        }

        return $strAlias;
    }


    /**
     * save_callback for the uploader
     * @param $value
     */
    public function saveCbSaveUploader($value)
    {

        $this->Database->prepare('UPDATE tl_user SET gc_be_uploader_template=? WHERE id=?')->execute($value, $this->User->id);
    }

    /**
     * save_callback for the image quality above the jumploader applet
     * @param $value
     */
    public function saveCbSaveImageQuality($value)
    {

        $this->Database->prepare('UPDATE tl_user SET gc_img_quality=? WHERE id=?')->execute($value, $this->User->id);
    }

    /**
     * save_callback for the image resolution above the jumploader applet
     * @param $value
     */
    public function saveCbSaveImageResolution($value)
    {

        $this->Database->prepare('UPDATE tl_user SET gc_img_resolution=? WHERE id=?')->execute($value, $this->User->id);
    }
}