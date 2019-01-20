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
 * Class tl_gallery_creator_pictures
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @copyright  Marko Cupic 2005-2010
 * @author     Marko Cupic
 */
class tl_gallery_creator_pictures extends Backend
{

    /**
     *  Pfad ab TL_ROOT ins Bildverzeichnis
     *
     * @var string
     */
    public $uploadPath;

    /**
     * bool
     * bei eingeschränkten Usern wird der Wert auf true gesetzt
     */
    public $restrictedUser = false;

    public function __construct()
    {

        parent::__construct();

        $this->import('BackendUser', 'User');
        $this->import('Files');

        //relativer Pfad zum Upload-Dir fuer safe-mode-hack
        $this->uploadPath = GALLERY_CREATOR_UPLOAD_PATH;

        //parse Backend Template Hook registrieren
        $GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = array('tl_gallery_creator_pictures', 'myParseBackendTemplate');

        // set the referer when redirecting from import files from the filesystem
        if (\Input::get('filesImported'))
        {
            $this->import('Session');
            $session = $this->Session->get('referer');
            $session[TL_REFERER_ID]['current'] = 'contao/main.php?do=gallery_creator';
            $this->Session->set('referer', $session);
        }

        switch (Input::get('mode'))
        {

            case 'imagerotate' :

                $objPic = GalleryCreatorPicturesModel::findById(Input::get('imgId'));
                $objFile = FilesModel::findByUuid($objPic->uuid);
                if ($objFile !== null)
                {
                    // Rotate image anticlockwise
                    $angle = 270;
                    GcHelpers::imageRotate($objFile->path, $angle);
                    Dbafs::addResource($objFile->path, true);
                    $this->redirect('contao/main.php?do=gallery_creator&table=tl_gallery_creator_pictures&id=' . Input::get('id'));
                }
                break;
            default :
                break;
        }//end switch

        switch (Input::get('act'))
        {
            case 'create' :
                //Neue Bilder können ausschliesslich über einen Bildupload realisiert werden
                $this->Redirect('contao/main.php?do=gallery_creator&table=tl_gallery_creator_pictures&id=' . Input::get('pid'));
                break;

            case 'select' :
                if (!$this->User->isAdmin)
                {
                    // only list pictures where user is owner
                    if (!\Config::get('gc_disable_backend_edit_protection'))
                    {
                        $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['list']['sorting']['filter'] = array(array('owner=?', $this->User->id));
                    }
                }

                break;

            default :
                break;
        } //end switch


        // get the source album when cuting pictures from one album to an other
        if (Input::get('act') == 'paste' && Input::get('mode') == 'cut')
        {
            $objPicture = GalleryCreatorPicturesModel::findByPk(Input::get('id'));
            if ($objPicture !== null)
            {
                $_SESSION['gallery_creator']['SOURCE_ALBUM_ID'] = $objPicture->pid;
            }
        }
    }

    /**
     * Return the delete-image-button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function buttonCbDeletePicture($row, $href, $label, $title, $icon, $attributes)
    {

        $objImg = $this->Database->prepare('SELECT owner FROM tl_gallery_creator_pictures WHERE id=?')->execute($row['id']);
        return ($this->User->isAdmin || $this->User->id == $objImg->owner || \Config::get('gc_disable_backend_edit_protection')) ? '<a href="' . $this->addToUrl($href . '&id=' . $row['id']) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml(preg_replace('/\.gif$/i', '_.gif', $icon)) . ' ';
    }

    /**
     * Return the edit-image-button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function buttonCbEditImage($row, $href, $label, $title, $icon, $attributes)
    {

        $objImg = $this->Database->prepare('SELECT owner FROM tl_gallery_creator_pictures WHERE id=?')->execute($row['id']);
        return ($this->User->isAdmin || $this->User->id == $objImg->owner || \Config::get('gc_disable_backend_edit_protection')) ? '<a href="' . $this->addToUrl($href . '&id=' . $row['id'], true) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml(preg_replace('/\.gif$/i', '_.gif', $icon)) . ' ';
    }

    /**
     * Return the cut-image-button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function buttonCbCutImage($row, $href, $label, $title, $icon, $attributes)
    {

        return '<a href="' . $this->addToUrl($href . '&id=' . $row['id']) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';
    }

    /**
     * Return the rotate-image-button
     *
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function buttonCbRotateImage($row, $href, $label, $title, $icon, $attributes)
    {

        return ($this->User->isAdmin || $this->User->id == $row['owner'] || \Config::get('gc_disable_backend_edit_protection')) ? '<a href="' . $this->addToUrl($href . '&imgId=' . $row['id']) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml($icon, $label);
    }

    /**
     * child-record-callback
     *
     * @param array
     * @return string
     */
    public function childRecordCb($arrRow)
    {

        $key = ($arrRow['published'] == '1') ? 'published' : 'unpublished';

        //nächste Zeile nötig, da be_breadcrumb sonst bei "mehrere bearbeiten" hier einen Fehler produziert
        $oFile = FilesModel::findByUuid($arrRow['uuid']);

        if (!is_file(TL_ROOT . "/" . $oFile->path))
        {
            return "";
        }

        $objFile = new File($oFile->path);
        if ($objFile->isGdImage)
        {
            //if dataset contains a link to movie file...
            $hasMovie = null;
            $src = $objFile->path;
            $src = trim($arrRow['socialMediaSRC']) != "" ? trim($arrRow['socialMediaSRC']) : $src;

            // local media (movies, etc.)
            if (Validator::isUuid($arrRow['localMediaSRC']))
            {
                $lmSRC = FilesModel::findByUuid($arrRow['localMediaSRC']);
                if ($lmSRC !== null)
                {
                    $src = $lmSRC->path;
                }
            }

            if (trim($arrRow['socialMediaSRC']) != "" or $lmSRC !== null)
            {
                $type = trim($arrRow['localMediaSRC']) == "" ? ' embeded local-media: ' : ' embeded social media: ';
                $iconSrc = 'bundles/markocupicgallerycreator/images/film.png';
                $movieIcon = Image::getHtml($iconSrc);
                $hasMovie = sprintf('<div class="block">%s%s<a href="%s" data-lightbox="gc_album_%s">%s</a></div>', $movieIcon, $type, $src, Input::get('id'), $src);
            }
            $blnShowThumb = false;
            $src = '';
            //generate icon/thumbnail
            if (\Config::get('thumbnails') && $oFile !== null)
            {
                $src = Image::get($oFile->path, "100", "", "center_center");
                $blnShowThumb = true;
            }
            //return html
            $return = sprintf('<div class="cte_type %s"><strong>%s</strong> - %s [%s x %s px, %s]</div>', $key, $arrRow['headline'], basename($oFile->path), $objFile->width, $objFile->height, $this->getReadableSize($objFile->filesize));
            $return .= $hasMovie;
            $return .= $blnShowThumb ? '<div class="block"><img src="' . $src . '" width="100"></div>' : null;
            $return .= sprintf('<div class="limit_height%s block">%s</div>', (\Config::get('thumbnails') ? ' h64' : ''), specialchars($arrRow['comment']));
            return $return;
        }
        return '';
    }

    /**
     * move images in the filesystem, when cutting/pasting images from one album into another
     *
     * @param DC_Table $dc
     */
    public function onCutCb(DC_Table $dc)
    {


        if (!isset($_SESSION['gallery_creator']['SOURCE_ALBUM_ID']))
        {
            return;
        }

        // Get sourceAlbumObject
        $objSourceAlbum = GalleryCreatorAlbumsModel::findByPk($_SESSION['gallery_creator']['SOURCE_ALBUM_ID']);
        unset($_SESSION['gallery_creator']['SOURCE_ALBUM_ID']);

        // Get pictureToMoveObject
        $objPictureToMove = GalleryCreatorPicturesModel::findByPk(Input::get('id'));
        if ($objSourceAlbum === null || $objPictureToMove === null)
        {
            return;
        }

        if (Input::get('mode') == '1')
        {
            // Paste after existing file
            $objTargetAlbum = GalleryCreatorPicturesModel::findByPk(Input::get('pid'))->getRelated('pid');
        }
        elseif (Input::get('mode') == '2')
        {
            // Paste on top
            $objTargetAlbum = GalleryCreatorAlbumsModel::findByPk(Input::get('pid'));
        }

        if ($objTargetAlbum === null)
        {
            return;
        }

        if ($objSourceAlbum->id == $objTargetAlbum->id)
        {
            return;
        }

        $objFile = FilesModel::findByUuid($objPictureToMove->uuid);
        $objTargetFolder = FilesModel::findByUuid($objTargetAlbum->assignedDir);
        $objSourceFolder = FilesModel::findByUuid($objSourceAlbum->assignedDir);

        if ($objFile === null || $objTargetFolder === null || $objSourceFolder === null)
        {
            return;
        }

        // Return if it is an external file
        if (false === strpos($objFile->path, $objSourceFolder->path))
        {
            return;
        }

        $strDestination = $objTargetFolder->path . '/' . basename($objFile->path);
        if ($strDestination != $objFile->path)
        {
            $oFile = new File($objFile->path);
            // Move file to the target folder
            if ($oFile->renameTo($strDestination))
            {
                $objPictureToMove->path = $strDestination;
                $objPictureToMove->save();
            }
        }
    }

    /**
     * input-field-callback generate image
     * Returns the html-img-tag
     *
     * @return string
     */
    public function inputFieldCbGenerateImage(DataContainer $dc)
    {
        $objImg = GalleryCreatorPicturesModel::findByPk($dc->id);
        $oFile = FilesModel::findByUuid($objImg->uuid);
        if ($oFile !== null)
        {
            $src = $oFile->path;
            $basename = basename($oFile->path);
            return '
                     <div class="long widget" style="height:auto;">
                         <h3><label for="ctrl_picture">' . $basename . '</label></h3>
                         <img src="' . Image::get($src, '380', '', 'proportional') . '" style="max-width:100%; max-height:300px;">
                     </div>
		             ';
        }
        return '';
    }

    /**
     * input-field-callback generate image information
     * Returns the html-table-tag containing some picture informations
     *
     * @param DataContainer $dc
     * @return string
     */
    public function inputFieldCbGenerateImageInformation(DataContainer $dc)
    {

        $objImg = GalleryCreatorPicturesModel::findByPk($dc->id);
        $objUser = UserModel::findByPk($objImg->owner);
        $oFile = FilesModel::findByUuid($objImg->uuid);

        $output = '
			<div class="long widget album_infos">
			<br><br>
			<table cellpadding="0" cellspacing="0" width="100%" summary="">

				<tr class="odd">
					<td style="width:20%"><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['pid'][0] . ': </strong></td>
					<td>' . $objImg->id . '</td>
				</tr>


				<tr>
					<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['path'][0] . ': </strong></td>
					<td>' . $oFile->path . '</td>
				</tr>

				<tr class="odd">
					<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['filename'][0] . ': </strong></td>
					<td>' . basename($oFile->path) . '</td>
				</tr>';

        if ($this->restrictedUser)
        {
            $output .= '
					<tr>
					<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['date'][0] . ': </strong></td>
					<td>' . Date::parse("Y-m-d", $objImg->date) . '</td>
					</tr>
					
					<tr class="odd">
						<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['owner'][0] . ': </strong></td>
						<td>' . ($objUser->name == "" ? "Couldn't find username with ID " . $objImg->owner . " in the db." : $objUser->name) . '</td>
					</tr>

					<tr>
					<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['title'][0] . ': </strong></td>
					<td>' . $objImg->title . '</td>
					</tr>

					<tr class="odd">
					<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['video_href_social'][0] . ': </strong></td>
					<td>' . trim($objImg->video_href_social) != "" ? trim($objImg->video_href_social) : "-" . '</td>
					</tr>
					
					<tr>
					<td><strong>' . $GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['video_id'][0] . ': </strong></td>
					<td>' . (trim($objImg->video_href_local) != '' ? trim($objImg->video_href_local) : '-') . '</td>
					</tr>';
        }

        $output .= '
			</table>
			</div>
		';
        return $output;
    }

    /**
     * Parse Backend Template Hook
     *
     * @param string
     * @param string
     * @return string
     */
    public function myParseBackendTemplate($strContent, $strTemplate)
    {

        if (Input::get('table') == 'tl_gallery_creator_pictures')
        {
            //da alle neuen Bilder (neue Datensaetze) nur über fileupload oder importImages realisiert werden, ist der "Create-Button" obsolet
            //entfernt den Create-Button aus den den global operations
            $pattern = '|<a href="[^"]*tl_gallery_creator_pictures[^"]*mode=create[^"]*"[^>]*></a>|Usi';
            $strContent = preg_replace($pattern, '', $strContent);

            //entfernt den Create-Button aus den den operations
            $pattern = '|<a href="[^"]*tl_gallery_creator_pictures[^"]*act=create[^"]*"[^>]*><img[^>]*></a>|Usi';
            $strContent = preg_replace($pattern, '', $strContent);

            //Bei einigen Browsern überragt die textarea den unteren Seitenrand, deshalb eine weitere leere clearing-box
            $strContent = str_replace('</fieldset>', '<div class="clr" style="clear:both"><p> </p><!-- clearing Box --></div></fieldset>', $strContent);
        }

        if (Input::get('table') == 'tl_gallery_creator_pictures' && Input::get('act') == 'select')
        {
            // saveNcreate button-entfernen
            $strContent = preg_replace('/<input type=\"submit\" name=\"saveNcreate\"((\r|\n|.)+?)>/', '', $strContent);

            // saveNback button-entfernen
            //$strContent=preg_replace('/<input type=\"submit\" name=\"saveNback\"((\r|\n|.)+?)>/','',$strContent);

            // remove cut button
            // $strContent = preg_replace('/<input type="submit" name="cut"(.*?)>/', '', $strContent);

            // remove copy button
            $strContent = preg_replace('/<input type="submit" name="copy"(.*?)>/', '', $strContent);

        }
        return $strContent;
    }

    /**
     * ondelete-callback
     * prevents deleting images by unauthorised users
     */
    public function ondeleteCb(DC_Table $dc)
    {

        $objImg = GalleryCreatorPicturesModel::findByPk($dc->id);
        $pid = $objImg->pid;
        if ($objImg->owner == $this->User->id || $this->User->isAdmin || \Config::get('gc_disable_backend_edit_protection'))
        {
            // Datensatz löschen
            $uuid = $objImg->uuid;

            $objImg->delete();

            //Nur Bilder innerhalb des gallery_creator_albums und wenn sie nicht in einem anderen Datensatz noch Verwendung finden, werden vom Server geloescht

            // Prüfen, ob das Bild noch mit einem anderen Datensatz verknüpft ist
            $objPictureModel = GalleryCreatorPicturesModel::findByUuid($uuid);
            if ($objPictureModel === null)
            {
                // Wenn nein darf gelöscht werden...
                $oFile = FilesModel::findByUuid($uuid);

                $objAlbum = GalleryCreatorAlbumsModel::findByPk($pid);
                $oFolder = FilesModel::findByUuid($objAlbum->assignedDir);

                // Bild nur löschen, wenn es im Verzeichnis liegt, das dem Album zugewiesen ist
                if ($oFile !== null && strstr($oFile->path, $oFolder->path))
                {
                    // delete file from filesystem
                    $file = new File($oFile->path, true);
                    $file->delete();
                }
            }
        }

        elseif (!$this->User->isAdmin && $objImg->owner != $this->User->id)
        {
            $this->log('Datensatz mit ID ' . $dc->id . ' wurde vom  Benutzer mit ID ' . $this->User->id . ' versucht aus tl_gallery_creator_pictures zu loeschen.', __METHOD__, TL_ERROR);
            Message::addError('No permission to delete picture with ID ' . $dc->id . '.');
            $this->redirect('contao/main.php?do=error');
        }
    }

    /**
     * child-record-callback
     *
     * @param array
     * @return string
     */
    public function onloadCbCheckPermission()
    {

        // admin hat keine Einschraenkungen
        if ($this->User->isAdmin)
        {
            return;
        }

        //Nur der Ersteller hat keine Einschraenkungen

        if (Input::get('act') == 'edit')
        {
            $objUser = $this->Database->prepare('SELECT owner FROM tl_gallery_creator_pictures WHERE id=?')->execute(Input::get('id'));

            if (\Config::get('gc_disable_backend_edit_protection'))
            {
                return;
            }

            if ($objUser->owner != $this->User->id)
            {
                $this->restrictedUser = true;
            }
        }
    }

    /**
     * onload-callback
     * set up the palette
     * prevents deleting images by unauthorised users
     */
    public function onloadCbSetUpPalettes()
    {

        if ($this->restrictedUser)
        {
            $this->restrictedUser = true;
            $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['palettes']['restricted_user'];
        }

        if ($this->User->isAdmin)
        {
            $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['fields']['owner']['eval']['doNotShow'] = false;
        }
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


        if (!$this->User->isAdmin && $row['owner'] != $this->User->id && !\Config::get('gc_disable_backend_edit_protection'))
        {
            return Image::getHtml($icon) . ' ';
        }

        return '<a href="' . $this->addToUrl($href) . '" title="' . specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';
    }

    /**
     * toggle visibility of a certain image
     *
     * @param integer
     * @param boolean
     */
    public function toggleVisibility($intId, $blnVisible)
    {

        $objPicture = GalleryCreatorPicturesModel::findByPk($intId);
        // Check permissions to publish
        if (!$this->User->isAdmin && $objPicture->owner != $this->User->id && !\Config::get('gc_disable_backend_edit_protection'))
        {
            $this->log('Not enough permissions to publish/unpublish tl_gallery_creator_albums ID "' . $intId . '"', __METHOD__, TL_ERROR);
            $this->redirect('contao/main.php?act=error');
        }

        $objVersions = new Versions('tl_gallery_creator_pictures', $intId);
        $objVersions->initialize();

        // Trigger the save_callback
        if (is_array($GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['fields']['published']['save_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['fields']['published']['save_callback'] as $callback)
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
        $this->Database->prepare("UPDATE tl_gallery_creator_pictures SET tstamp=" . time() . ", published='" . ($blnVisible ? 1 : '') . "' WHERE id=?")->execute($intId);

        $objVersions->create();
        $this->log('A new version of record "tl_gallery_creator_pictures.id=' . $intId . '" has been created.', __METHOD__, TL_GENERAL);
    }
}
