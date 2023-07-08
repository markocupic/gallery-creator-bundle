<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle for Contao CMS.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace Markocupic\GalleryCreatorBundle\DataContainer;

use Contao\Backend;
use Contao\Config;
use Contao\DataContainer;
use Contao\Date;
use Contao\Dbafs;
use Contao\DC_Table;
use Contao\File;
use Contao\FilesModel;
use Contao\Image;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\Validator;
use Contao\Versions;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\GalleryCreatorUtil;

class GalleryCreatorPictures extends Backend
{

    public string|null $uploadPath = null;
    public bool $restrictedUser = false;

    public function __construct()
    {
        parent::__construct();

        $this->import('BackendUser', 'User');
        $this->import('Files');

        $this->uploadPath = GALLERY_CREATOR_UPLOAD_PATH;

        // Register parse backend template hook
        $GLOBALS['TL_HOOKS']['parseBackendTemplate'][] = [self::class, 'removeSubmitButtons'];

        // set the referer when redirecting from import files from the filesystem
        if (Input::get('filesImported')) {
            $this->import('Session');
            $session = $this->Session->get('referer');
            $session[TL_REFERER_ID]['current'] = 'contao?do=gallery_creator';
            $this->Session->set('referer', $session);
        }

        switch (Input::get('mode')) {
            case 'imagerotate':

                $objPic = GalleryCreatorPicturesModel::findById(Input::get('imgId'));
                $objFile = FilesModel::findByUuid($objPic->uuid);

                if (null !== $objFile) {
                    // Rotate image anticlockwise
                    $angle = 270;
                    GalleryCreatorUtil::imageRotate($objFile->path, $angle);
                    Dbafs::addResource($objFile->path, true);
                    $this->redirect('contao?do=gallery_creator&table=tl_gallery_creator_pictures&id='.Input::get('id'));
                }
                break;

            default:
                break;
        }

        switch (Input::get('act')) {
            case 'create':

                // New images can only be realized via an image upload
                $this->redirect('contao?do=gallery_creator&table=tl_gallery_creator_pictures&id='.Input::get('pid'));
                break;

            case 'select':
                if (!$this->User->isAdmin) {
                    // only list pictures where the current logged-in user is owner
                    if (!Config::get('gc_disable_backend_edit_protection')) {
                        $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['list']['sorting']['filter'] = [['owner=?', $this->User->id]];
                    }
                }

                break;

            default:
                break;
        }

        // Get the source album when cutting pictures from one album to another
        if ('paste' === Input::get('act') && 'cut' === Input::get('mode')) {
            $objPicture = GalleryCreatorPicturesModel::findByPk(Input::get('id'));

            if (null !== $objPicture) {
                $_SESSION['gallery_creator']['SOURCE_ALBUM_ID'] = $objPicture->pid;
            }
        }
    }

    /**
     * Return the delete-image-button.
     */
    public function buttonCbDeletePicture(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        $objImg = $this->Database->prepare('SELECT owner FROM tl_gallery_creator_pictures WHERE id=?')->execute($row['id']);

        return $this->User->isAdmin || $this->User->id === $objImg->owner || Config::get('gc_disable_backend_edit_protection') ? '<a href="'.$this->addToUrl($href.'&id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }

    /**
     * Return the edit-image-button.
     */
    public function buttonCbEditImage(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        $objImg = $this->Database->prepare('SELECT owner FROM tl_gallery_creator_pictures WHERE id=?')->execute($row['id']);

        return $this->User->isAdmin || $this->User->id === $objImg->owner || Config::get('gc_disable_backend_edit_protection') ? '<a href="'.$this->addToUrl($href.'&id='.$row['id'], true).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }

    /**
     * Return the cut-image-button.
     */
    public function buttonCbCutImage(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        return '<a href="'.$this->addToUrl($href.'&id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Return the rotate-image-button.
     */
    public function buttonCbRotateImage(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        return $this->User->isAdmin || $this->User->id === $row['owner'] || Config::get('gc_disable_backend_edit_protection') ? '<a href="'.$this->addToUrl($href.'&imgId='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml($icon, $label);
    }

    /**
     * child-record-callback.
     */
    public function childRecordCb(array $arrRow): string
    {
        $key = '1' === $arrRow['published'] ? 'published' : 'unpublished';

        // Nächste Zeile nötig, da be_breadcrumb sonst bei "mehrere bearbeiten" hier einen Fehler produziert
        $oFile = FilesModel::findByUuid($arrRow['uuid']);

        if (!is_file(TL_ROOT.'/'.$oFile->path)) {
            return '';
        }

        $objFile = new File($oFile->path);

        if ($objFile->isGdImage) {
            // if the data record contains a link to movie file...
            $hasMovie = null;
            $src = $objFile->path;
            $src = '' !== trim($arrRow['socialMediaSRC']) ? trim($arrRow['socialMediaSRC']) : $src;
            $lmSRC = null;

            // local media (movies, etc.)
            if (Validator::isBinaryUuid($arrRow['localMediaSRC'])) {
                $lmSRC = FilesModel::findByUuid($arrRow['localMediaSRC']);

                if (null !== $lmSRC) {
                    $src = $lmSRC->path;
                }
            }

            if ('' !== trim($arrRow['socialMediaSRC']) || null !== $lmSRC) {
                $type = '' === trim($arrRow['localMediaSRC']) ? ' embeded local-media: ' : ' embeded social media: ';
                $iconSrc = 'bundles/markocupicgallerycreator/images/film.png';
                $movieIcon = Image::getHtml($iconSrc);
                $hasMovie = sprintf('<div class="block">%s%s<a href="%s" data-lightbox="gc_album_%s">%s</a></div>', $movieIcon, $type, $src, Input::get('id'), $src);
            }
            $blnShowThumb = false;
            $src = '';

            if (Config::get('thumbnails') && null !== $oFile) {
                $src = Image::get($oFile->path, '100', '', 'center_center');
                $blnShowThumb = true;
            }

            $return = sprintf('<div class="cte_type %s"><strong>%s</strong> - %s [%s x %s px, %s]</div>', $key, $arrRow['title'], basename($oFile->path), $objFile->width, $objFile->height, $this->getReadableSize($objFile->filesize));
            $return .= $hasMovie;
            $return .= $blnShowThumb ? '<div class="block"><img src="'.$src.'" width="100" alt=""></div>' : null;
            $return .= sprintf('<div class="limit_height%s block">%s</div>', (Config::get('thumbnails') ? ' h64' : ''), StringUtil::specialchars($arrRow['comment']));

            return $return;
        }

        return '';
    }

    /**
     * Move images in the filesystem, when cutting/pasting images from one album into another.
     */
    public function onCutCb(DC_Table $dc): void
    {
        if (!isset($_SESSION['gallery_creator']['SOURCE_ALBUM_ID'])) {
            return;
        }

        $objSourceAlbum = GalleryCreatorAlbumsModel::findByPk($_SESSION['gallery_creator']['SOURCE_ALBUM_ID']);
        unset($_SESSION['gallery_creator']['SOURCE_ALBUM_ID']);

        $objPictureToMove = GalleryCreatorPicturesModel::findByPk(Input::get('id'));

        if (null === $objSourceAlbum || null === $objPictureToMove) {
            return;
        }

        if ('1' === Input::get('mode')) {
            // Paste after existing file
            $objTargetAlbum = GalleryCreatorPicturesModel::findByPk(Input::get('pid'))->getRelated('pid');
        } elseif ('2' === Input::get('mode')) {
            // Paste on top
            $objTargetAlbum = GalleryCreatorAlbumsModel::findByPk(Input::get('pid'));
        }

        if (empty($objTargetAlbum)) {
            return;
        }

        if ($objSourceAlbum->id === $objTargetAlbum->id) {
            return;
        }

        $objFile = FilesModel::findByUuid($objPictureToMove->uuid);
        $objTargetFolder = FilesModel::findByUuid($objTargetAlbum->assignedDir);
        $objSourceFolder = FilesModel::findByUuid($objSourceAlbum->assignedDir);

        if (null === $objFile || null === $objTargetFolder || null === $objSourceFolder) {
            return;
        }

        // Return if it is an external file
        if (!str_contains($objFile->path, $objSourceFolder->path)) {
            return;
        }

        $strDestination = $objTargetFolder->path.'/'.basename($objFile->path);

        if ($strDestination !== $objFile->path) {
            $oFile = new File($objFile->path);
            // Move file to the target folder
            if ($oFile->renameTo($strDestination)) {
                $objPictureToMove->path = $strDestination;
                $objPictureToMove->save();
            }
        }
    }

    /**
     * input-field-callback generate image
     * Returns the html-img-tag.
     */
    public function inputFieldCbGenerateImage(DataContainer $dc): string
    {
        $objImg = GalleryCreatorPicturesModel::findByPk($dc->id);
        $oFile = FilesModel::findByUuid($objImg->uuid);

        if (null !== $oFile) {
            $src = $oFile->path;
            $basename = basename($oFile->path);

            return '
                     <div class="long widget" style="height:auto;">
                         <h3><label for="ctrl_picture">'.$basename.'</label></h3>
                         <img src="'.Image::get($src, '380', '', 'proportional').'" style="max-width:100%; max-height:300px;" alt="">
                     </div>
		             ';
        }

        return '';
    }

    /**
     * input-field-callback generate image information
     * Returns the html-table-tag containing some picture information.
     */
    public function inputFieldCbGenerateImageInformation(DataContainer $dc): string
    {
        $objImg = GalleryCreatorPicturesModel::findByPk($dc->id);
        $objUser = UserModel::findByPk($objImg->owner);
        $oFile = FilesModel::findByUuid($objImg->uuid);

        $output = '
			<div class="long widget album_infos">
			<br><br>
			<table>

				<tr class="odd">
					<td style="width:20%"><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['pid'][0].': </strong></td>
					<td>'.$objImg->id.'</td>
				</tr>


				<tr>
					<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['path'][0].': </strong></td>
					<td>'.$oFile->path.'</td>
				</tr>

				<tr class="odd">
					<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['filename'][0].': </strong></td>
					<td>'.basename($oFile->path).'</td>
				</tr>';

        if ($this->restrictedUser) {
            $output .= '' !== '
					<tr>
					<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['date'][0].': </strong></td>
					<td>'.Date::parse('Y-m-d', $objImg->date).'</td>
					</tr>

					<tr class="odd">
						<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['owner'][0].': </strong></td>
						<td>'.('' === $objUser->name ? "Couldn't find username with ID ".$objImg->owner.' in the db.' : $objUser->name).'</td>
					</tr>

					<tr>
					<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['title'][0].': </strong></td>
					<td>'.$objImg->title.'</td>
					</tr>

					<tr class="odd">
					<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['video_href_social'][0].': </strong></td>
					<td>'.trim($objImg->video_href_social) ? trim($objImg->video_href_social) : '-'.'</td>
					</tr>

					<tr>
					<td><strong>'.$GLOBALS['TL_LANG']['tl_gallery_creator_pictures']['video_id'][0].': </strong></td>
					<td>'.('' !== trim($objImg->video_href_local) ? trim($objImg->video_href_local) : '-').'</td>
					</tr>';
        }

        $output .= '
			</table>
			</div>
		';

        return $output;
    }

    /**
     * Parse Backend Template Hook.
     */
    public function removeSubmitButtons(string $strContent, string $strTemplate): string
    {
        if ('tl_gallery_creator_pictures' === Input::get('table')) {
            // Since all new images (new data records) are only created via fileupload or importImages, the "Create button" is obsolete
            $pattern = '|<a href="[^"]*tl_gallery_creator_pictures[^"]*mode=create[^"]*"[^>]*></a>|Usi';
            $strContent = preg_replace($pattern, '', $strContent);

            // remove the create button
            $pattern = '|<a href="[^"]*tl_gallery_creator_pictures[^"]*act=create[^"]*"[^>]*><img[^>]*></a>|Usi';
            $strContent = preg_replace($pattern, '', $strContent);

            // With some browsers, the textarea extends beyond the bottom edge of the page, hence another empty clearing box
            $strContent = str_replace('</fieldset>', "<div class=\"clr\" style=\"clear:both\"><p>\u{a0}</p><!-- clearing Box --></div></fieldset>", $strContent);
        }

        if ('tl_gallery_creator_pictures' === Input::get('table') && 'select' === Input::get('act')) {
            // Remove the saveNcreate button
            $strContent = preg_replace('/<input type=\"submit\" name=\"saveNcreate\"((\r|\n|.)+?)>/', '', $strContent);

            // Remove the copy button
            $strContent = preg_replace('/<input type="submit" name="copy"(.*?)>/', '', $strContent);
        }

        return $strContent;
    }

    /**
     * ondelete-callback
     * prevent deletion of images by unauthorised users.
     */
    public function ondeleteCb(DC_Table $dc): void
    {
        $objImg = GalleryCreatorPicturesModel::findByPk($dc->id);
        $pid = $objImg->pid;

        if ($objImg->owner === $this->User->id || $this->User->isAdmin || Config::get('gc_disable_backend_edit_protection')) {
            $uuid = $objImg->uuid;

            $objImg->delete();

            // Check whether the image is still linked to another data record
            $objPictureModel = GalleryCreatorPicturesModel::findByUuid($uuid);

            if (null === $objPictureModel) {
                $oFile = FilesModel::findByUuid($uuid);

                $objAlbum = GalleryCreatorAlbumsModel::findByPk($pid);
                $oFolder = FilesModel::findByUuid($objAlbum->assignedDir);

                // Delete image only if it is in the directory assigned to the album
                if (null !== $oFile && strstr($oFile->path, $oFolder->path)) {
                    // delete file from filesystem
                    $file = new File($oFile->path, true);
                    $file->delete();
                }
            }
        } elseif (!$this->User->isAdmin && $objImg->owner !== $this->User->id) {
            $this->log('Datensatz mit ID '.$dc->id.' wurde vom  Benutzer mit ID '.$this->User->id.' versucht aus tl_gallery_creator_pictures zu loeschen.', __METHOD__, TL_ERROR);
            Message::addError('No permission to delete picture with ID '.$dc->id.'.');
            $this->redirect('contao?do=error');
        }
    }

    /**
     * onload-callback
     */
    public function onloadCbCheckPermission(): void
    {
        if ($this->User->isAdmin) {
            return;
        }

        if ('edit' === Input::get('act')) {
            $objUser = $this->Database->prepare('SELECT owner FROM tl_gallery_creator_pictures WHERE id=?')->execute(Input::get('id'));

            if (Config::get('gc_disable_backend_edit_protection')) {
                return;
            }

            if ($objUser->owner !== $this->User->id) {
                $this->restrictedUser = true;
            }
        }
    }

    /**
     * onload-callback
     */
    public function onloadCbSetUpPalettes(): void
    {
        if ($this->restrictedUser) {
            $this->restrictedUser = true;
            $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['palettes']['restricted_user'];
        }

        if ($this->User->isAdmin) {
            $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['fields']['owner']['eval']['doNotShow'] = false;
        }
    }

    /**
     * Return the "toggle visibility" button.
     */
    public function toggleIcon(array $row, string|null $href, string $label, string $title, string $icon, string $attributes): string
    {
        if (\strlen((string) Input::get('tid'))) {
            $this->toggleVisibility(Input::get('tid'), 1 === Input::get('state'));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->isAdmin && $row['owner'] !== $this->User->id && !Config::get('gc_disable_backend_edit_protection')) {
            return '';
        }

        $href .= '&amp;tid='.$row['id'].'&amp;state='.($row['published'] ? '' : 1);

        if (!$row['published']) {
            $icon = 'invisible.svg';
        }

        if (!$this->User->isAdmin && $row['owner'] !== $this->User->id && !Config::get('gc_disable_backend_edit_protection')) {
            return Image::getHtml($icon).' ';
        }

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * toggle visibility of a certain image.
     */
    public function toggleVisibility(int $intId, bool $blnVisible): void
    {
        $objPicture = GalleryCreatorPicturesModel::findByPk($intId);
        // Check permissions to publish
        if (!$this->User->isAdmin && $objPicture->owner !== $this->User->id && !Config::get('gc_disable_backend_edit_protection')) {
            $this->log('Not enough permissions to publish/unpublish tl_gallery_creator_albums ID "'.$intId.'"', __METHOD__, TL_ERROR);
            $this->redirect('contao?act=error');
        }

        $objVersions = new Versions('tl_gallery_creator_pictures', $intId);
        $objVersions->initialize();

        // Trigger the save_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['fields']['published']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['fields']['published']['save_callback'] as $callback) {
                if (\is_array($callback)) {
                    $this->import($callback[0]);
                    $blnVisible = $this->$callback[0]->$callback[1]($blnVisible, $this);
                } elseif (\is_callable($callback)) {
                    $blnVisible = $callback($blnVisible, $this);
                }
            }
        }

        // Update the database
        $this->Database->prepare('UPDATE tl_gallery_creator_pictures SET tstamp='.time().", published='".($blnVisible ? 1 : '')."' WHERE id=?")->execute($intId);

        $objVersions->create();
        $this->log('A new version of record "tl_gallery_creator_pictures.id='.$intId.'" has been created.', __METHOD__, TL_GENERAL);
    }
}
