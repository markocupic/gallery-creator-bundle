<?php

/*
 * This file is part of Gallery Creator Bundle and an extension for the Contao CMS.
 *
 * (c) Marko Cupic
 *
 * @license MIT
 */

use Markocupic\GalleryCreatorBundle\GcHelpers;

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
        if (!$this->User->isAdmin && $row['owner'] != $this->User->id && !\Config::get('gc_disable_backend_edit_protection'))
        {
            return '';
        }

        $href .= '&amp;tid=' . $row['id'] . '&amp;state=' . ($row['published'] ? '' : 1);

        if (!$row['published'])
        {
            $icon = 'invisible.gif';
        }

        $this->Database->prepare("SELECT * FROM tl_gallery_creator_albums WHERE id=?")->limit(1)->execute($row['id']);

        if (!$this->User->isAdmin && $row['owner'] != $this->User->id && !\Config::get('gc_disable_backend_edit_protection'))
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
        if (!$this->User->isAdmin && $objAlbum->owner != $this->User->id && !\Config::get('gc_disable_backend_edit_protection'))
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
        return (($this->User->id == $row['owner'] || $this->User->isAdmin || \Config::get('gc_disable_backend_edit_protection')) ? ' <a href="' . $this->addToUrl($href . '&id=' . $row['id']) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : ' ' . Image::getHtml(preg_replace('/\.gif$/i', '_.gif', $icon)) . ' ');
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
        return ($this->User->isAdmin || $this->User->id == $row['owner'] || \Config::get('gc_disable_backend_edit_protection')) ? '<a href="' . $this->addToUrl($href . '&id=' . $row['id']) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml(preg_replace('/\.gif$/i', '_.gif', $icon)) . ' ';
    }

    /**
     * Return the editheader-button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function buttonCbEditHeader($row, $href, $label, $title, $icon, $attributes)
    {

        return '<a href="' . $this->addToUrl($href . '&id=' . $row['id'], 1) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';
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
        if ($this->User->isAdmin || \Config::get('gc_disable_backend_edit_protection'))
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
<div class="widget revise_tables">
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
        $label = str_replace('#datum#', \Date::parse(\Config::get('dateFormat'), $row['date']), $label);
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
     * buttons_callback buttonsCallback
     * @param $arrButtons
     * @param $dc
     * @return mixed
     */
    public function buttonsCallback($arrButtons, $dc)
    {

        if (Input::get('mode') == 'revise_tables')
        {
            // remove buttons
            unset($arrButtons['saveNcreate']);
            unset($arrButtons['saveNclose']);
            $arrButtons['save'] = '<button type="submit" name="save" id="reviseTableBtn" class="tl_submit" accesskey="s">' . $GLOBALS['TL_LANG']['tl_gallery_creator_albums']['reviseTablesBtn'][0] . '</button>';
        }

        if (Input::get('mode') == 'fileupload')
        {
            // remove buttons
            unset($arrButtons['save']);
            unset($arrButtons['saveNclose']);
            unset($arrButtons['saveNcreate']);
        }

        if (Input::get('mode') == 'import_images')
        {
            // remove buttons
            unset($arrButtons['saveNclose']);
            unset($arrButtons['saveNcreate']);
            unset($arrButtons['uploadNback']);
        }

        return $arrButtons;
    }

    /**
     * Parse Backend Template Hook
     * @param string
     * @param string
     * @return string
     */
    public function myParseBackendTemplate($strContent, $strTemplate)
    {
        if (Input::get('mode') == 'fileupload')
        {
            // form encode
            $strContent = str_replace('application/x-www-form-urlencoded', 'multipart/form-data', $strContent);
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
                if ($this->User->isAdmin || $objAlbumModel->owner == $this->User->id || \Config::get('gc_disable_backend_edit_protection'))
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
                        $folder->protect();
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
                    if ($objFile->height <= \Config::get('gdMaxImgHeight') && $objFile->width <= \Config::get('gdMaxImgWidth'))
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