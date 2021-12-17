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

namespace Markocupic\GalleryCreatorBundle\Dca;

use Contao\Backend;
use Contao\Config;
use Contao\Database;
use Contao\DataContainer;
use Contao\Date;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\Image;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Contao\Versions;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\FileUtil;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Class tl_gallery_creator_pictures.
 */
class TlGalleryCreatorPictures extends Backend
{
    /**
     * @var bool
     */
    public $restrictedUser = false;
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var FileUtil
     */
    private $fileUtil;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var string
     */
    private $galleryCreatorUploadPath;

    /**
     * @var string
     */
    private $galleryCreatorBackendWriteProtection;

    /**
     * @var Session|null
     */
    private $session;

    /**
     * @var string
     */
    private $uploadPath;

    public function __construct(RequestStack $requestStack, FileUtil $fileUtil, string $projectDir, string $galleryCreatorUploadPath, string $galleryCreatorBackendWriteProtection)
    {
        parent::__construct();

        $this->requestStack = $requestStack;
        $this->fileUtil = $fileUtil;
        $this->projectDir = $projectDir;
        $this->galleryCreatorUploadPath = $galleryCreatorUploadPath;
        $this->galleryCreatorBackendWriteProtection = $galleryCreatorBackendWriteProtection;

        $this->session = $this->requestStack->getCurrentRequest()->getSession();

        $this->import('BackendUser', 'User');

        $this->uploadPath = Config::get('galleryCreatorUploadPath');

        // Set the correct referer when redirecting from "import files from the filesystem"
        if (Input::get('filesImported')) {
            $session = $this->session->get('referer');
            $session[TL_REFERER_ID]['current'] = 'contao?do=gallery_creator';
            $this->session->set('referer', $session);
        }

        switch (Input::get('mode')) {
            case 'imagerotate':

                $objPic = GalleryCreatorPicturesModel::findById(Input::get('imgId'));
                $objFile = FilesModel::findByUuid($objPic->uuid);

                if (null !== $objFile) {
                    $file = new File($objFile->path);
                    // Rotate image anticlockwise
                    $angle = 270;
                    $this->fileUtil->imageRotate($file, $angle);
                    Dbafs::addResource($objFile->path, true);
                    $this->redirect('contao?do=gallery_creator&table=tl_gallery_creator_pictures&id='.Input::get('id'));
                }
                break;

            default:
                break;
        }

        switch (Input::get('act')) {
            case 'create':
                // New images can only be implemented via an image upload
                $this->redirect('contao?do=gallery_creator&table=tl_gallery_creator_pictures&id='.Input::get('pid'));
                break;

            case 'select':
                if (!$this->User->isAdmin) {
                    // Only list pictures where user is owner
                    if ($this->galleryCreatorBackendWriteProtection) {
                        $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['list']['sorting']['filter'] = [['owner=?', $this->User->id]];
                    }
                }

                break;

            default:
                break;
        }

        // Get the source album when copy & pasting pictures from one album into an other
        if ('paste' === Input::get('act') && 'cut' === Input::get('mode')) {
            $objPicture = GalleryCreatorPicturesModel::findByPk(Input::get('id'));

            if (null !== $objPicture) {
                $this->session->set('gc_source_album_id', $objPicture->pid);
            }
        }
    }

    /**
     * Return the delete-image-button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     */
    public function buttonCbDeletePicture($row, $href, $label, $title, $icon, $attributes): string
    {
        $objImg = Database::getInstance()
            ->prepare('SELECT owner FROM tl_gallery_creator_pictures WHERE id=?')
            ->execute($row['id'])
        ;

        return $this->User->isAdmin || (int) $this->User->id === (int) $objImg->owner || !$this->galleryCreatorBackendWriteProtection ? '<a href="'.$this->addToUrl($href.'&id='.$row['id']).'" title="'.specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml(preg_replace('/\.gif$/i', '_.gif', $icon)).' ';
    }

    /**
     * Return the edit image button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     */
    public function buttonCbEditImage($row, $href, $label, $title, $icon, $attributes): string
    {
        $objImg = Database::getInstance()
            ->prepare('SELECT owner FROM tl_gallery_creator_pictures WHERE id=?')
            ->execute($row['id'])
        ;

        return $this->User->isAdmin || (int) $this->User->id === (int) $objImg->owner || !$this->galleryCreatorBackendWriteProtection ? '<a href="'.$this->addToUrl($href.'&id='.$row['id'], true).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml(preg_replace('/\.gif$/i', '_.gif', $icon)).' ';
    }

    /**
     * Return the cut image button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     */
    public function buttonCbCutImage($row, $href, $label, $title, $icon, $attributes): string
    {
        return '<a href="'.$this->addToUrl($href.'&id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Return the rotate-image-button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function buttonCbRotateImage($row, $href, $label, $title, $icon, $attributes)
    {
        return $this->User->isAdmin || (int) $this->User->id === (int) $row['owner'] || !$this->galleryCreatorBackendWriteProtection ? '<a href="'.$this->addToUrl($href.'&imgId='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ' : Image::getHtml($icon, $label);
    }

    /**
     * Child record callback.
     */
    public function childRecordCb(array $arrRow): string
    {
        $key = $arrRow['published'] ? 'published' : 'unpublished';

        $oFile = FilesModel::findByUuid($arrRow['uuid']);

        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        if (!is_file($projectDir.'/'.$oFile->path)) {
            return '';
        }

        $objFile = new File($oFile->path);

        if ($objFile->isGdImage) {
            // If data record contains a link to a movie file...
            $hasMovie = null;
            $src = $objFile->path;
            $src = '' !== trim($arrRow['socialMediaSRC']) ? trim($arrRow['socialMediaSRC']) : $src;

            // Local media (movies, etc.)
            if (Validator::isBinaryUuid($arrRow['localMediaSRC'])) {
                $lmSRC = FilesModel::findByUuid($arrRow['localMediaSRC']);

                if (null !== $lmSRC) {
                    $src = $lmSRC->path;
                }
            }

            if ('' !== trim($arrRow['socialMediaSRC']) || null !== $lmSRC) {
                $type = empty(trim((string) $arrRow['localMediaSRC'])) ? ' embeded local-media: ' : ' embeded social media: ';
                $iconSrc = 'bundles/markocupicgallerycreator/images/film.png';
                $movieIcon = Image::getHtml($iconSrc);
                $hasMovie = sprintf(
                    '<div class="block">%s%s<a href="%s" data-lightbox="gc_album_%s">%s</a></div>',
                    $movieIcon,
                    $type,
                    $src,
                    Input::get('id'),
                    $src,
                );
            }

            $blnShowThumb = false;
            $src = '';

            // Generate icon/thumbnail
            if (Config::get('thumbnails') && null !== $oFile) {
                $src = Image::get($oFile->path, '100', '', 'center_center');
                $blnShowThumb = true;
            }

            $return = sprintf('<div class="cte_type %s"><strong>%s</strong> - %s [%s x %s px, %s]</div>', $key, $arrRow['headline'], basename($oFile->path), $objFile->width, $objFile->height, $this->getReadableSize($objFile->filesize));
            $return .= $hasMovie;
            $return .= $blnShowThumb ? '<div class="block"><img src="'.$src.'" width="100"></div>' : null;
            $return .= sprintf('<div class="limit_height%s block">%s</div>', (Config::get('thumbnails') ? ' h64' : ''), StringUtil::specialchars($arrRow['caption']));

            return $return;
        }

        return '';
    }

    /**
     * Move images in the filesystem too, when cutting/pasting images from one album into another.
     */
    public function onCutCb(DataContainer $dc): void
    {
        if (!$this->session->has('gc_source_album_id')) {
            return;
        }

        // Get sourceAlbumObject
        $objSourceAlbum = GalleryCreatorAlbumsModel::findByPk($this->session->get('gc_source_album_id'));
        $this->session->remove('gc_source_album_id');

        // Get pictureToMoveObject
        $objPictureToMove = GalleryCreatorPicturesModel::findByPk(Input::get('id'));

        if (null === $objSourceAlbum || null === $objPictureToMove) {
            return;
        }

        if (1 === (int) Input::get('mode')) {
            // Paste after existing file
            $objTargetAlbum = GalleryCreatorPicturesModel::findByPk(Input::get('pid'))->getRelated('pid');
        } elseif (2 === (int) Input::get('mode')) {
            // Paste on top
            $objTargetAlbum = GalleryCreatorAlbumsModel::findByPk(Input::get('pid'));
        }

        if (null === $objTargetAlbum) {
            return;
        }

        if ((int) $objSourceAlbum->id === (int) $objTargetAlbum->id) {
            return;
        }

        $objFile = FilesModel::findByUuid($objPictureToMove->uuid);
        $objTargetFolder = FilesModel::findByUuid($objTargetAlbum->assignedDir);
        $objSourceFolder = FilesModel::findByUuid($objSourceAlbum->assignedDir);

        if (null === $objFile || null === $objTargetFolder || null === $objSourceFolder) {
            return;
        }

        // Return if it is an external file
        if (false === strpos($objFile->path, $objSourceFolder->path)) {
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
     * Input field callback generate image
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
                     <img src="'.Image::get($src, '380', '', 'proportional').'" style="max-width:100%; max-height:300px;">
                 </div>
		             ';
        }

        return '';
    }

    /**
     * Generate image information html
     * Returns the html table that contains some picture information.
     */
    public function inputFieldCbGenerateImageInformation(DataContainer $dc): string
    {
        $objImage = GalleryCreatorPicturesModel::findByPk($dc->id);
        $objUser = UserModel::findByPk($objImage->owner);
        $objFile = FilesModel::findByUuid($objImage->uuid);
        $objSocial = FilesModel::findByUuid($objImage->socialMediaSRC);
        $objLocal = FilesModel::findByUuid($objImage->localMediaSRC);

        $objImage->video_href_social = $objSocial ? $objSocial->path : '';
        $objImage->video_href_social = $objLocal ? $objLocal->path : '';
        $objImage->path = $objFile->path;
        $objImage->filename = basename((string) $objFile->path);
        $objImage->date_formatted = Date::parse('Y-m-d', $objImage->date);
        $objImage->owner_name = '' === $objUser->name ? '---' : $objUser->name;

        $translator = System::getContainer()->get('translator');
        $twig = System::getContainer()->get('twig');

        return (new Response(
            $twig->render(
                '@MarkocupicGalleryCreator/Backend/image_information.html.twig',
                [
                    'model' => $objImage->row(),
                    'trans' => [
                        'picture_id' => $translator->trans('tl_gallery_creator_pictures.id.0', [], 'contao_default'),
                        'picture_info' => $translator->trans('tl_gallery_creator_pictures.imageInfo.0', [], 'contao_default'),
                        'picture_path' => $translator->trans('tl_gallery_creator_pictures.path.0', [], 'contao_default'),
                        'picture_filename' => $translator->trans('tl_gallery_creator_pictures.filename.0', [], 'contao_default'),
                        'picture_date' => $translator->trans('tl_gallery_creator_pictures.date.0', [], 'contao_default'),
                        'picture_owner' => $translator->trans('tl_gallery_creator_pictures.owner.0', [], 'contao_default'),
                        'picture_title' => $translator->trans('tl_gallery_creator_pictures.title.0', [], 'contao_default'),
                        'picture_video_href_social' => $translator->trans('tl_gallery_creator_pictures.socialMediaSRC.0', [], 'contao_default'),
                        'picture_video_href_local' => $translator->trans('tl_gallery_creator_pictures.localMediaSRC.0', [], 'contao_default'),
                    ],
                ]
            )
        ))->getContent();
    }

    /**
     * Edit button callback.
     */
    public function editButtonsCallback(array $buttons, DataContainer $dc): array
    {
        // Remove the "Save and close" button
        unset($buttons['saveNcreate'], $buttons['copy']);

        return $buttons;
    }

    /**
     * On delete callback
     * Do not allow deleting images by unauthorised users.
     */
    public function ondeleteCb(DataContainer $dc): void
    {
        $objImg = GalleryCreatorPicturesModel::findByPk($dc->id);
        $pid = $objImg->pid;

        if ((int) $objImg->owner === (int) $this->User->id || $this->User->isAdmin || Config::get('gc_disable_backend_edit_protection')) {
            // Delete data record
            $uuid = $objImg->uuid;

            $objImg->delete();

            // Only images within the gallery_creator_album directory
            // and if they are not used in another data set will be deleted from the server
            $objPictureModel = GalleryCreatorPicturesModel::findByUuid($uuid);

            if (null === $objPictureModel) {
                // Delete if all ok
                $oFile = FilesModel::findByUuid($uuid);

                $objAlbum = GalleryCreatorAlbumsModel::findByPk($pid);
                $oFolder = FilesModel::findByUuid($objAlbum->assignedDir);

                // Only delete an image if it is in the directory assigned to the album
                if (null !== $oFile && strstr($oFile->path, $oFolder->path)) {
                    // delete file from filesystem
                    $file = new File($oFile->path, true);
                    $file->delete();
                }
            }
        } elseif (!$this->User->isAdmin && $objImg->owner !== $this->User->id) {
            Message::addError('No permission to delete picture with ID '.$dc->id.'.');
            $this->redirect('contao');
        }
    }

    public function onloadCbCheckPermission(): void
    {
        $this->restrictedUser = false;

        // Admin have no restrictions
        if ($this->User->isAdmin) {
            return;
        }

        // Only the creator has no restrictions

        if ('edit' === Input::get('act')) {
            $objUser = Database::getInstance()
                ->prepare('SELECT owner FROM tl_gallery_creator_pictures WHERE id=?')
                ->execute(Input::get('id'))
            ;

            if (!$this->galleryCreatorBackendWriteProtection) {
                return;
            }

            if ($objUser->owner !== $this->User->id) {
                $this->restrictedUser = true;
            }
        }
    }

    /**
     * Set up the palette
     * Prevent deleting images by unauthorised users.
     */
    public function onloadCbSetUpPalettes(): void
    {
        if ($this->restrictedUser) {
            $this->restrictedUser = true;
            $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['palettes']['restrictedUser'];
        }

        if ($this->User->isAdmin) {
            $GLOBALS['TL_DCA']['tl_gallery_creator_pictures']['fields']['owner']['eval']['doNotShow'] = false;
        }
    }

    /**
     * Return the "toggle visibility" button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes): string
    {
        if (!empty(Input::get('tid'))) {
            $this->toggleVisibility(Input::get('tid'), 1 === (int) Input::get('state'));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->isAdmin && $row['owner'] !== $this->User->id && $this->galleryCreatorBackendWriteProtection) {
            return '';
        }

        $href .= '&amp;tid='.$row['id'].'&amp;state='.($row['published'] ? '' : 1);

        if (!$row['published']) {
            $icon = 'invisible.gif';
        }

        if (!$this->User->isAdmin && $row['owner'] !== $this->User->id && $this->galleryCreatorBackendWriteProtection) {
            return Image::getHtml($icon).' ';
        }

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Toggle visibility.
     *
     * @param int  $intId
     * @param bool $blnVisible
     */
    public function toggleVisibility($intId, $blnVisible): void
    {
        $objPicture = GalleryCreatorPicturesModel::findByPk($intId);

        // Check if user is allowed to toggle visibility
        if (!$this->User->isAdmin && $objPicture->owner !== $this->User->id && $this->galleryCreatorBackendWriteProtection) {
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
        Database::getInstance()
            ->prepare('UPDATE tl_gallery_creator_pictures SET tstamp='.time().", published='".($blnVisible ? 1 : '')."' WHERE id=?")
            ->execute($intId)
        ;

        $objVersions->create();
        $this->log('A new version of record "tl_gallery_creator_pictures.id='.$intId.'" has been created.', __METHOD__, TL_GENERAL);
    }
}
